<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *  Test APIv3 civicrm_profile_* functions
 *
 * @package   CiviCRM
 * @group headless
 */
class api_v3_ProfileTest extends CiviUnitTestCase {
  use CRMTraits_Custom_CustomDataTrait;

  protected $_profileID = 0;

  protected $_membershipTypeID;

  protected $_contactID;

  /**
   * Set up for test.
   */
  public function setUp(): void {
    parent::setUp();
    $countryLimit = Civi::settings()->get('countryLimit');
    $countryLimit[1] = 1013;
    Civi::settings()->set('countryLimit', $countryLimit);

    $this->createLoggedInUser();
    $this->_membershipTypeID = $this->membershipTypeCreate();
  }

  /**
   * Cleanup after test.
   */
  public function tearDown(): void {
    $this->quickCleanUpFinancialEntities();
    $this->quickCleanup([
      'civicrm_contact',
      'civicrm_phone',
      'civicrm_address',
      'civicrm_uf_match',
      'civicrm_im',
      'civicrm_website',
      'civicrm_email',
    ], TRUE);
    CRM_Core_DAO::executeQuery(" DELETE FROM civicrm_uf_group WHERE id = $this->_profileID OR name = 'test_contact_activity_profile'");
    parent::tearDown();
  }

  /**
   * Check Without ProfileId.
   */
  public function testProfileGetWithoutProfileId(): void {
    $this->callAPIFailure('profile', 'get', ['contact_id' => 1],
      'Mandatory key(s) missing from params array: profile_id'
    );
  }

  /**
   * Check with no invalid profile Id.
   */
  public function testProfileGetInvalidProfileId(): void {
    $this->callAPIFailure('profile', 'get', [
      'contact_id' => 1,
      'profile_id' => 1000,
    ]);
  }

  /**
   * Check with success.
   */
  public function testProfileGet(): void {
    $profileFieldValues = $this->createIndividualContact();
    $expected = reset($profileFieldValues);
    $contactId = key($profileFieldValues);
    $params = [
      'profile_id' => $this->_profileID,
      'contact_id' => $contactId,
    ];
    $result = $this->callAPISuccess('profile', 'get', $params)['values'];
    foreach ($expected as $profileField => $value) {
      $this->assertEquals($value, $result[$profileField]);
    }
  }

  /**
   * Test retrieving a profile with an address custom field in it.
   *
   * We are checking that there is no error.
   *
   */
  public function testProfileGetWithAddressCustomData(): void {
    $this->createIndividualContact();
    $this->createCustomGroupWithFieldOfType(['extends' => 'Address']);
    $this->callAPISuccess('UFField', 'create', [
      'uf_group_id' => $this->_profileID,
      'field_name' => $this->getCustomFieldName(),
      'visibility' => 'Public Pages and Listings',
      'label' => 'My custom field',
      'field_type' => 'Contact',
    ]);
    $this->callAPISuccess('Address', 'get', ['contact_id' => $this->_contactID, 'api.Address.create' => [$this->getCustomFieldName() => 'my field']]);
    $this->callAPISuccess('Profile', 'get', ['profile_id' => $this->_profileID, 'contact_id' => $this->_contactID])['values'];
  }

  /**
   * Test getting multiple profiles.
   */
  public function testProfileGetMultiple(): void {
    $profileFieldValues = $this->createIndividualContact();
    $expected = reset($profileFieldValues);
    $contactId = key($profileFieldValues);
    $params = [
      'profile_id' => [$this->_profileID, 1, 'Billing'],
      'contact_id' => $contactId,
    ];

    $result = $this->callAPISuccess('profile', 'get', $params)['values'];
    foreach ($expected as $profileField => $value) {
      $this->assertEquals($value, $result[$this->_profileID][$profileField], ' error message: ' . "missing/mismatching value for $profileField");
    }
    $this->assertEquals('abc1', $result[1]['first_name'], ' error message: ' . 'missing/mismatching value for first name');
    $this->assertArrayNotHasKey('email-Primary', $result[1], 'profile 1 does not include email');
    $this->assertEquals([
      'billing_first_name' => 'abc1',
      'billing_middle_name' => 'J.',
      'billing_last_name' => 'xyz1',
      'billing_street_address-5' => '5 Saint Helier St',
      'billing_city-5' => 'Gotham City',
      'billing_state_province_id-5' => '1021',
      'billing_country_id-5' => '1228',
      'billing_postal_code-5' => '90210',
      'billing-email-5' => 'abc1.xyz1@yahoo.com',
      'email-5' => 'abc1.xyz1@yahoo.com',
    ], $result['Billing']);
  }

  /**
   * Test getting billing profile filled using is_billing.
   */
  public function testProfileGetBillingUseIsBillingLocation(): void {
    $individual = $this->createIndividualContact();
    $contactId = key($individual);
    $this->callAPISuccess('address', 'create', [
      'is_billing' => 1,
      'street_address' => 'is billing st',
      'location_type_id' => 2,
      'contact_id' => $contactId,
    ]);

    $params = [
      'profile_id' => [$this->_profileID, 1, 'Billing'],
      'contact_id' => $contactId,
    ];

    $result = $this->callAPISuccess('profile', 'get', $params)['values'];
    $this->assertEquals('abc1', $result[1]['first_name']);
    $this->assertEquals([
      'billing_first_name' => 'abc1',
      'billing_middle_name' => 'J.',
      'billing_last_name' => 'xyz1',
      'billing_street_address-5' => 'is billing st',
      'billing_city-5' => '',
      'billing_state_province_id-5' => '',
      'billing_country_id-5' => '',
      'billing-email-5' => 'abc1.xyz1@yahoo.com',
      'email-5' => 'abc1.xyz1@yahoo.com',
      'billing_postal_code-5' => '',
    ], $result['Billing']);
  }

  /**
   * Test getting multiple profiles, including billing.
   */
  public function testProfileGetMultipleHasBillingLocation(): void {
    $individual = $this->createIndividualContact();
    $contactId = key($individual);
    $this->callAPISuccess('address', 'create', [
      'contact_id' => $contactId,
      'street_address' => '25 Big Street',
      'city' => 'big city',
      'location_type_id' => 5,
    ]);
    $this->callAPISuccess('email', 'create', [
      'contact_id' => $contactId,
      'email' => 'big@once.com',
      'location_type_id' => 2,
      'is_billing' => 1,
    ]);

    $params = [
      'profile_id' => [$this->_profileID, 1, 'Billing'],
      'contact_id' => $contactId,
    ];

    $result = $this->callAPISuccess('profile', 'get', $params);
    $this->assertEquals('abc1', $result['values'][1]['first_name']);
    $this->assertEquals([
      'billing_first_name' => 'abc1',
      'billing_middle_name' => 'J.',
      'billing_last_name' => 'xyz1',
      'billing_street_address-5' => '25 Big Street',
      'billing_city-5' => 'big city',
      'billing_state_province_id-5' => '',
      'billing_country_id-5' => '',
      'billing-email-5' => 'big@once.com',
      'email-5' => 'big@once.com',
      'billing_postal_code-5' => '',
    ], $result['values']['Billing']);
  }

  /**
   * Get Billing empty contact - this will return generic defaults.
   *
   */
  public function testProfileGetBillingEmptyContact(): void {
    $this->callAPISuccess('Setting', 'create', ['defaultContactCountry' => 1228]);
    $params = [
      'profile_id' => ['Billing'],
    ];

    $result = $this->callAPISuccess('profile', 'get', $params)['values'];
    $this->assertEquals([
      'billing_first_name' => '',
      'billing_middle_name' => '',
      'billing_last_name' => '',
      'billing_street_address-5' => '',
      'billing_city-5' => '',
      'billing_state_province_id-5' => '',
      'billing_country_id-5' => '1228',
      'billing_email-5' => '',
      'email-5' => '',
      'billing_postal_code-5' => '',
    ], $result['Billing']);
  }

  /**
   * Check contact activity profile without activity id.
   */
  public function testContactActivityGetWithoutActivityId(): void {
    [$params] = $this->createContactWithActivity();

    unset($params['activity_id']);
    $this->callAPIFailure('profile', 'get', $params, 'Mandatory key(s) missing from params array: activity_id');
  }

  /**
   * Check contact activity profile wrong activity id.
   */
  public function testContactActivityGetWrongActivityId(): void {
    [$params] = $this->createContactWithActivity();
    $params['activity_id'] = 100001;
    $this->callAPIFailure('profile', 'get', $params, 'Invalid Activity Id (aid).');
  }

  /**
   * Check contact activity profile with wrong activity type.
   *
   * @throws \Exception
   */
  public function testContactActivityGetWrongActivityType(): void {
    $activity = $this->callAPISuccess('activity', 'create', [
      'source_contact_id' => $this->householdCreate(),
      'activity_type_id' => '2',
      'subject' => 'Test activity',
      'activity_date_time' => '20110316',
      'duration' => '120',
      'location' => 'Pennsylvania',
      'details' => 'a test activity',
      'status_id' => '1',
      'priority_id' => '1',
    ])['values'];

    $activityValues = array_pop($activity);

    [$params] = $this->createContactWithActivity();

    $params['activity_id'] = $activityValues['id'];
    $this->callAPIFailure('profile', 'get', $params, 'This activity cannot be edited or viewed via this profile.');
  }

  /**
   * Check contact activity profile with success.
   */
  public function testContactActivityGetSuccess(): void {
    [$params, $expected] = $this->createContactWithActivity();

    $result = $this->callAPISuccess('profile', 'get', $params);

    foreach ($expected as $profileField => $value) {
      $this->assertEquals($value, $result['values'][$profileField], ' error message: ' . "missing/mismatching value for $profileField"
      );
    }
  }

  /**
   * Check getfields works & gives us our fields
   */
  public function testGetFields(): void {
    $ufGroupParams = [
      'group_type' => 'Individual,Contact',
      'title' => 'Flat Coffee',
      'api.uf_field.create' => [
        [
          'field_name' => 'first_name',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Individual',
          'label' => 'First Name',
        ],
        [
          // No location type == Primary
          'field_name' => 'email',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Contact',
          'label' => 'Email',
        ],
        [
          // No location type == Primary
          'field_name' => 'phone',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Contact',
          'phone_type_id' => 1,
          'label' => 'Phone',
        ],
        [
          'field_name' => 'phone_and_ext',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Contact',
          'phone_type_id' => 2,
          'location_type_id' => 1,
          'label' => 'Phone',
        ],
        [
          // No location type == Primary
          'field_name' => 'country',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Contact',
          'label' => 'Country',
        ],
        [
          'field_name' => 'state_province',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Contact',
          'location_type_id' => 1,
          'label' => 'State Province',
        ],
        [
          'field_name' => 'postal_code',
          'is_required' => 0,
          'field_type' => 'Contact',
          'location_type_id' => 1,
          'label' => 'State Province',
        ],
        [
          'field_name' => 'im',
          'is_required' => 0,
          'visibility' => 'User and User Admin Only',
          'label' => 'Messenger',
          'field_type' => 'Contact',
        ],
        [
          'field_name' => 'url',
          'is_required' => 0,
          'visibility' => 'User and User Admin Only',
          'label' => 'Website',
          'website_type_id' => 1,
          'field_type' => 'Contact',
        ],
      ],
    ];
    $profile = $this->callAPISuccess('uf_group', 'create', $ufGroupParams);
    $this->addCustomFieldToProfile($profile['id']);
    // Add some more fields

    $result = $this->callAPISuccess('profile', 'getfields', [
      'action' => 'submit',
      'profile_id' => $profile['id'],
    ]);
    $this->assertArrayKeyExists('first_name', $result['values']);
    $this->assertEquals('2', $result['values']['first_name']['type']);
    $this->assertEquals('Email', $result['values']['email-primary']['title']);
    $this->assertEquals('phone', $result['values']['phone-primary-1']['entity']);
    $this->assertEquals('phone', $result['values']['phone_and_ext-1-2']['entity']);
    $this->assertEquals('civicrm_state_province', $result['values']['state_province-1']['pseudoconstant']['table']);
    $this->assertEquals('defaultValue', $result['values']['custom_1']['default_value']);
    $this->assertArrayNotHasKey('participant_status', $result['values']);
    $this->assertEquals('website', $result['values']['url-1']['entity']);
    $this->assertEquals('im', $result['values']['im-primary']['entity']);
  }

  /**
   * Check getfields works & gives us our fields - participant profile.
   */
  public function testGetFieldsParticipantProfile(): void {
    $result = $this->callAPISuccess('profile', 'getfields', [
      'action' => 'submit',
      'profile_id' => 'participant_status',
      'get_options' => 'all',
    ]);
    $this->assertArrayHasKey('participant_status_id', $result['values']);
    $this->assertEquals('Attended', $result['values']['participant_status_id']['options'][2]);
    $this->assertEquals(['participant_status'], $result['values']['participant_status_id']['api.aliases']);
  }

  /**
   * Check getfields works & gives us our fields - membership_batch_entry
   * (getting to the end with no e-notices is pretty good evidence it's working).
   */
  public function testGetFieldsMembershipBatchProfile(): void {
    $result = $this->callAPISuccess('profile', 'getfields', [
      'action' => 'submit',
      'profile_id' => 'membership_batch_entry',
      'get_options' => 'all',
    ]);
    $this->assertArrayHasKey('total_amount', $result['values']);
    $this->assertArrayHasKey('financial_type_id', $result['values']);
    $this->assertEquals([
      'contribution_type_id',
      'contribution_type',
      'financial_type',
    ], $result['values']['financial_type_id']['api.aliases']);
    $this->assertArrayNotHasKey('financial_type', $result['values']);
    $this->assertEquals(12, $result['values']['receive_date']['type']);
  }

  /**
   * Check getfields works & gives us our fields - do them all
   * (getting to the end with no e-notices is pretty good evidence it's working)
   */
  public function testGetFieldsAllProfiles(): void {
    $result = $this->callAPISuccess('uf_group', 'get', ['return' => 'id'])['values'];
    $profileIDs = array_keys($result);
    foreach ($profileIDs as $profileID) {
      $this->callAPISuccess('profile', 'getfields', [
        'action' => 'submit',
        'profile_id' => $profileID,
        'get_options' => 'all',
      ]);
    }
  }

  /**
   * Check with missing required field in profile.
   */
  public function testProfileSubmitCheckProfileRequired(): void {
    $profileFieldValues = $this->createIndividualContact();
    $contactId = key($profileFieldValues);
    $updateParams = [
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
      'phone-1-1' => '022 321 826',
      'country-1' => '1013',
      'state_province-1' => '1000',
    ];

    $params = array_merge([
      'profile_id' => $this->_profileID,
      'contact_id' => $contactId,
    ],
      $updateParams
    );

    $this->callAPIFailure('profile', 'submit', $params,
      'Mandatory key(s) missing from params array: email-primary'
    );
  }

  /**
   * Check with success.
   */
  public function testProfileSubmit(): void {
    $profileFieldValues = $this->createIndividualContact();
    $contactId = key($profileFieldValues);

    // Add a few more fields
    civicrm_api3('UfGroup', 'get', [
      'id' => $this->_profileID,
      'api.uf_field.create' => [
        [
          'field_name' => 'im',
          'is_required' => 0,
          'visibility' => 'User and User Admin Only',
          'label' => 'Messenger',
          'field_type' => 'Contact',
        ],
        [
          'field_name' => 'url',
          'is_required' => 0,
          'visibility' => 'User and User Admin Only',
          'label' => 'Website',
          'website_type_id' => 1,
          'field_type' => 'Contact',
        ],
        [
          'field_name' => 'city',
          'is_required' => FALSE,
          'visibility' => 'User and User Admin Only',
          'location_type_id' => NULL,
          'phone_type_id' => NULL,
          'website_type_id' => NULL,
          'field_type' => 'Contact',
        ],
      ],
    ]);

    $updateParams = [
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
      'email-primary' => 'abc2.xyz2@gmail.com',
      'phone-1-1' => '022 321 826',
      'country-1' => '1013',
      'state_province-1' => '1000',
      'city-primary' => 'Somewhere',
      'url-1' => 'http://example.com',
      'im-primary' => 'abc2xyz2',
    ];

    $params = array_merge([
      'profile_id' => $this->_profileID,
      'contact_id' => $contactId,
    ], $updateParams);

    $this->callAPISuccess('profile', 'submit', $params);

    $getParams = [
      'profile_id' => $this->_profileID,
      'contact_id' => $contactId,
    ];
    $profileDetails = $this->callAPISuccess('profile', 'get', $getParams);

    foreach ($updateParams as $profileField => $value) {
      $this->assertEquals($value, $profileDetails['values'][$profileField], "missing/mismatching value for $profileField");
    }
    unset($params['email-primary']);
    $params['email-Primary'] = 'my@mail.com';
    $this->callAPISuccess('profile', 'submit', $params);
    $profileDetails = $this->callAPISuccess('profile', 'get', $getParams);
    $this->assertEquals('my@mail.com', $profileDetails['values']['email-Primary']);
  }

  /**
   * Ensure caches are being cleared so we don't get into a debugging trap
   * because of cached metadata First we delete & create to increment the
   * version & then check for caching problems.
   */
  public function testProfileSubmitCheckCaching(): void {
    $this->callAPISuccess('membership_type', 'delete', ['id' => $this->_membershipTypeID]);
    $this->_membershipTypeID = $this->membershipTypeCreate();

    $membershipTypes = $this->callAPISuccess('membership_type', 'get', []);
    $profileFields = $this->callAPISuccess('profile', 'getfields', [
      'get_options' => 'all',
      'action' => 'submit',
      'profile_id' => 'membership_batch_entry',
    ]);
    $getoptions = $this->callAPISuccess('membership', 'getoptions', [
      'field' => 'membership_type',
      'context' => 'validate',
    ]);
    $this->assertEquals(array_keys($membershipTypes['values']), array_keys($getoptions['values']));
    $this->assertEquals(array_keys($membershipTypes['values']), array_keys($profileFields['values']['membership_type_id']['options']));

  }

  /**
   * Test that the fields are returned in the right order despite the faffing
   * around that goes on.
   */
  public function testMembershipGetFieldsOrder(): void {
    $result = $this->callAPISuccess('profile', 'getfields', [
      'action' => 'submit',
      'profile_id' => 'membership_batch_entry',
    ])['values'];
    $weight = 1;
    foreach ($result as $fieldName => $field) {
      if ($fieldName === 'profile_id') {
        continue;
      }
      $this->assertEquals($field['weight'], $weight);
      $weight++;
    }
  }

  /**
   * Check we can submit membership batch profiles (create mode).
   */
  public function testProfileSubmitMembershipBatch(): void {
    // @todo - figure out why this doesn't pass validate financials
    $this->isValidateFinancialsOnPostAssert = FALSE;
    $this->_contactID = $this->individualCreate();
    $this->callAPISuccess('profile', 'submit', [
      'profile_id' => 'membership_batch_entry',
      'financial_type_id' => 1,
      'membership_type' => $this->_membershipTypeID,
      'join_date' => 'now',
      'total_amount' => 10,
      'contribution_status_id' => 1,
      'receive_date' => 'now',
      'contact_id' => $this->_contactID,
    ]);
  }

  /**
   * Check contact activity profile without activity id.
   */
  public function testContactActivitySubmitWithoutActivityId(): void {
    [$params, $expected] = $this->createContactWithActivity();

    $params = array_merge($params, $expected);
    unset($params['activity_id']);
    $this->callAPIFailure('profile', 'submit', $params, 'Mandatory key(s) missing from params array: activity_id');
  }

  /**
   * Check contact activity profile wrong activity id.
   */
  public function testContactActivitySubmitWrongActivityId(): void {
    [$params, $expected] = $this->createContactWithActivity();
    $params = array_merge($params, $expected);
    $params['activity_id'] = 100001;
    $this->callAPIFailure('profile', 'submit', $params, 'Invalid Activity Id (aid).');
  }

  /**
   * Check contact activity profile with wrong activity type.
   *
   * @throws \Exception
   */
  public function testContactActivitySubmitWrongActivityType(): void {

    $sourceContactId = $this->householdCreate();

    $activityParams = [
      'source_contact_id' => $sourceContactId,
      'activity_type_id' => '2',
      'subject' => 'Test activity',
      'activity_date_time' => '20110316',
      'duration' => '120',
      'location' => 'Pennsylvania',
      'details' => 'a test activity',
      'status_id' => '1',
      'priority_id' => '1',
    ];

    $activity = $this->callAPISuccess('activity', 'create', $activityParams);

    $activityValues = array_pop($activity['values']);

    [$params, $expected] = $this->createContactWithActivity();

    $params = array_merge($params, $expected);
    $params['activity_id'] = $activityValues['id'];
    $this->callAPIFailure('profile', 'submit', $params,
      'This activity cannot be edited or viewed via this profile.');
  }

  /**
   * Check contact activity profile with success.
   */
  public function testContactActivitySubmitSuccess(): void {
    [$params] = $this->createContactWithActivity();

    $updateParams = [
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
      'email-Primary' => 'abc2.xyz2@yahoo.com',
      'activity_subject' => 'Test Meeting',
      'activity_details' => 'a test activity details',
      'activity_duration' => '100',
      'activity_date_time' => '2010-03-08 00:00:00',
      'activity_status_id' => '2',
    ];
    $profileParams = array_merge($params, $updateParams);
    $this->callAPISuccess('profile', 'submit', $profileParams);
    $result = $this->callAPISuccess('profile', 'get', $params)['values'];

    foreach ($updateParams as $profileField => $value) {
      $this->assertEquals($value, $result[$profileField], ' error message: ' . "missing/mismatching value for $profileField"
      );
    }
  }

  /**
   * Check profile apply Without ProfileId.
   */
  public function testProfileApplyWithoutProfileId(): void {
    $params = [
      'contact_id' => 1,
    ];
    $this->callAPIFailure('profile', 'apply', $params,
      'Mandatory key(s) missing from params array: profile_id');
  }

  /**
   * Check profile apply with no invalid profile Id.
   */
  public function testProfileApplyInvalidProfileId(): void {
    $params = [
      'contact_id' => 1,
      'profile_id' => 1000,
    ];
    $this->callAPIFailure('profile', 'apply', $params);
  }

  /**
   * Check with success.
   */
  public function testProfileApply(): void {
    $profileFieldValues = $this->createIndividualContact();
    $contactId = key($profileFieldValues);

    $params = [
      'profile_id' => $this->_profileID,
      'contact_id' => $contactId,
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
      'email-Primary' => 'abc2.xyz2@gmail.com',
      'phone-1-1' => '022 321 826',
      'country-1' => '1013',
      'state_province-1' => '1000',
    ];

    $result = $this->callAPISuccess('profile', 'apply', $params);

    // Expected field values
    $expected['contact'] = [
      'contact_id' => $contactId,
      'contact_type' => 'Individual',
      'first_name' => 'abc2',
      'last_name' => 'xyz2',
    ];
    $expected['email'] = [
      'location_type_id' => 1,
      'is_primary' => 1,
      'email' => 'abc2.xyz2@gmail.com',
    ];

    $expected['phone'] = [
      'location_type_id' => 1,
      'is_primary' => 1,
      'phone_type_id' => 1,
      'phone' => '022 321 826',
    ];
    $expected['address'] = [
      'location_type_id' => 1,
      'is_primary' => 1,
      'country_id' => 1013,
      'state_province_id' => 1000,
    ];

    foreach ($expected['contact'] as $field => $value) {
      $this->assertEquals($value, $result['values'][$field], "missing/mismatching value for $field"
      );
    }

    foreach (['email', 'phone', 'address'] as $fieldType) {
      $typeValues = array_pop($result['values'][$fieldType]);
      foreach ($expected[$fieldType] as $field => $value) {
        $this->assertEquals($value, $typeValues[$field], "missing/mismatching value for $field ($fieldType)"
        );
      }
    }
  }

  /**
   * Check success with tags.
   */
  public function testSubmitWithTags(): void {
    $profileFieldValues = $this->createIndividualContact();
    $params = reset($profileFieldValues);
    $contactId = key($profileFieldValues);
    $params['profile_id'] = $this->_profileID;
    $params['contact_id'] = $contactId;

    $this->callAPISuccess('ufField', 'create', [
      'uf_group_id' => $this->_profileID,
      'field_name' => 'tag',
      'visibility' => 'Public Pages and Listings',
      'field_type' => 'Contact',
      'label' => 'Tags',
    ]);

    $tag_1 = $this->callAPISuccess('tag', 'create', ['name' => 'abc'])['id'];
    $tag_2 = $this->callAPISuccess('tag', 'create', ['name' => 'def'])['id'];

    $params['tag'] = "$tag_1,$tag_2";
    $this->callAPISuccess('profile', 'submit', $params);

    $tags = $this->callAPISuccess('entityTag', 'get', ['entity_id' => $contactId]);
    $this->assertEquals(2, $tags['count']);

    $params['tag'] = [$tag_1];
    $this->callAPISuccess('profile', 'submit', $params);

    $tags = $this->callAPISuccess('entityTag', 'get', ['entity_id' => $contactId]);
    $this->assertEquals(1, $tags['count']);

    $params['tag'] = '';
    $this->callAPISuccess('profile', 'submit', $params);

    $tags = $this->callAPISuccess('entityTag', 'get', ['entity_id' => $contactId]);
    $this->assertEquals(0, $tags['count']);

  }

  /**
   * Check success with a note.
   *
   * @throws \Exception
   */
  public function testSubmitWithNote(): void {
    $profileFieldValues = $this->createIndividualContact();
    $params = reset($profileFieldValues);
    $contactId = key($profileFieldValues);
    $params['profile_id'] = $this->_profileID;
    $params['contact_id'] = $contactId;

    $this->callAPISuccess('ufField', 'create', [
      'uf_group_id' => $this->_profileID,
      'field_name' => 'note',
      'visibility' => 'Public Pages and Listings',
      'field_type' => 'Contact',
      'label' => 'Note',
    ]);

    $params['note'] = 'Hello 123';
    $this->callAPISuccess('profile', 'submit', $params);

    $note = $this->callAPISuccessGetSingle('note', ['entity_id' => $contactId]);
    $this->assertEquals('Hello 123', $note['note']);
  }

  /**
   * Check handling a custom greeting.
   *
   * @throws \CRM_Core_Exception
   */
  public function testSubmitGreetingFields(): void {
    $profileFieldValues = $this->createIndividualContact();
    $params = reset($profileFieldValues);
    $contactId = key($profileFieldValues);
    $params['profile_id'] = $this->_profileID;
    $params['contact_id'] = $contactId;

    $this->callAPISuccess('ufField', 'create', [
      'uf_group_id' => $this->_profileID,
      'field_name' => 'email_greeting',
      'visibility' => 'Public Pages and Listings',
      'field_type' => 'Contact',
      'label' => 'Email Greeting',
    ]);

    $emailGreetings = array_column(civicrm_api3('OptionValue', 'get', ['option_group_id' => 'email_greeting'])['values'], NULL, 'name');

    $params['email_greeting'] = $emailGreetings['Customized']['value'];
    // Custom greeting should be required
    $this->callAPIFailure('profile', 'submit', $params);

    $params['email_greeting_custom'] = 'Hello fool!';
    $this->callAPISuccess('profile', 'submit', $params);

    // Api3 will not return custom greeting field so resorting to this
    $greeting = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $contactId, 'email_greeting_custom');

    $this->assertEquals('Hello fool!', $greeting);
  }

  /**
   * Helper function to create an Individual with address/email/phone info.
   * Import UF Group and UF Fields
   *
   * @param array $params
   *
   * @return array
   */
  public function createIndividualContact(array $params = []): array {
    $contactParams = array_merge([
      'first_name' => 'abc1',
      'last_name' => 'xyz1',
      'email' => 'abc1.xyz1@yahoo.com',
      'api.address.create' => [
        'location_type_id' => 1,
        'is_primary' => 1,
        'street_address' => '5 Saint Helier St',
        'county' => 'Marin',
        'country' => 'UNITED STATES',
        'state_province' => 'Michigan',
        'supplemental_address_1' => 'Hallmark Ct',
        'supplemental_address_2' => 'Jersey Village',
        'supplemental_address_3' => 'My Town',
        'postal_code' => '90210',
        'city' => 'Gotham City',
        'is_billing' => 0,
      ],
      'api.phone.create' => [
        'location_type_id' => '1',
        'phone' => '021 512 755',
        'phone_type_id' => '1',
        'is_primary' => '1',
      ],
    ], $params);

    $this->_contactID = $this->individualCreate($contactParams);
    $this->createIndividualProfile();
    // expected result of above created profile with contact Id $contactId
    $profileData[$this->_contactID] = [
      'first_name' => 'abc1',
      'last_name' => 'xyz1',
      'email-primary' => 'abc1.xyz1@yahoo.com',
      'phone-1-1' => '021 512 755',
      'country-1' => '1228',
      'state_province-1' => '1021',
    ];

    return $profileData;
  }

  /**
   * @return array
   */
  public function createContactWithActivity(): array {
    $ufGroupID = $this->callAPISuccess('UFGroup', 'create', [
      'group_type' => 'Individual,Contact,Activity',
      'title' => 'Test Contact-Activity Profile',
      'name' => 'test_contact_activity_profile',
    ])['id'];
    $this->callAPISuccess('UFField', 'create', [
      'uf_group_id' => $ufGroupID,
      'field_name' => 'first_name',
      'is_required' => TRUE,
      'visibility' => 'Public Pages and Listings',
      'label' => 'First Name',
      'field_type' => 'Individual',
    ]);
    $this->callAPISuccess('UFField', 'create', [
      'uf_group_id' => $ufGroupID,
      'field_name' => 'last_name',
      'is_required' => TRUE,
      'visibility' => 'Public Pages and Listings',
      'label' => 'Last Name',
      'field_type' => 'Individual',
    ]);
    $this->callAPISuccess('UFField', 'create', [
      'uf_group_id' => $ufGroupID,
      'field_name' => 'email',
      'is_required' => TRUE,
      'visibility' => 'Public Pages and Listings',
      'label' => 'Email',
      'field_type' => 'Contact',
    ]);
    $this->callAPISuccess('UFField', 'create', [
      'uf_group_id' => $ufGroupID,
      'field_name' => 'activity_subject',
      'is_required' => TRUE,
      'visibility' => 'Public Pages and Listings',
      'label' => 'Activity Subject',
      'is_searchable' => TRUE,
      'field_type' => 'Activity',
    ]);
    $this->callAPISuccess('UFField', 'create', [
      'uf_group_id' => $ufGroupID,
      'field_name' => 'activity_details',
      'is_required' => TRUE,
      'visibility' => 'Public Pages and Listings',
      'label' => 'Activity Details',
      'is_searchable' => TRUE,
      'field_type' => 'Activity',
    ]);
    $this->callAPISuccess('UFField', 'create', [
      'uf_group_id' => $ufGroupID,
      'field_name' => 'activity_duration',
      'is_required' => TRUE,
      'visibility' => 'Public Pages and Listings',
      'label' => 'Activity Duration',
      'is_searchable' => TRUE,
      'field_type' => 'Activity',
    ]);
    $this->callAPISuccess('UFField', 'create', [
      'uf_group_id' => $ufGroupID,
      'field_name' => 'activity_date_time',
      'is_required' => TRUE,
      'visibility' => 'Public Pages and Listings',
      'label' => 'Activity Date',
      'is_searchable' => TRUE,
      'field_type' => 'Activity',
    ]);
    $this->callAPISuccess('UFField', 'create', [
      'uf_group_id' => $ufGroupID,
      'field_name' => 'activity_status_id',
      'is_required' => TRUE,
      'visibility' => 'Public Pages and Listings',
      'label' => 'Activity Status',
      'is_searchable' => TRUE,
      'field_type' => 'Activity',
    ]);

    // hack: xml data set did not accept  (CRM_Core_DAO::VALUE_SEPARATOR) - should be possible
    // to un-hack now we use the api.
    CRM_Core_DAO::setFieldValue('CRM_Core_DAO_UFGroup', $ufGroupID, 'group_type', 'Individual,Contact,Activity' . CRM_Core_DAO::VALUE_SEPARATOR . 'ActivityType:1');

    $sourceContactId = $this->individualCreate();
    $contactParams = [
      'first_name' => 'abc1',
      'last_name' => 'xyz1',
      'contact_type' => 'Individual',
      'email' => 'abc1.xyz1@yahoo.com',
      'api.address.create' => [
        'location_type_id' => 1,
        'is_primary' => 1,
        'name' => 'Saint Helier St',
        'county' => 'Marin',
        'country' => 'UNITED STATES',
        'state_province' => 'Michigan',
        'supplemental_address_1' => 'Hallmark Ct',
        'supplemental_address_2' => 'Jersey Village',
        'supplemental_address_3' => 'My Town',
      ],
    ];

    $contact = $this->callAPISuccess('contact', 'create', $contactParams);

    $keys = array_keys($contact['values']);
    $contactId = array_pop($keys);

    $this->assertEquals(0, $contact['values'][$contactId]['api.address.create']['is_error'], ' error message: ' . ($contact['values'][$contactId]['api.address.create']['error_message'] ?? '')
    );

    $activityParams = [
      'source_contact_id' => $sourceContactId,
      'assignee_contact_id' => $contactId,
      'activity_type_id' => '1',
      'subject' => 'Make-it-Happen Meeting',
      'activity_date_time' => '2011-03-16 00:00:00',
      'duration' => '120',
      'location' => 'Pennsylvania',
      'details' => 'a test activity',
      'status_id' => '1',
      'priority_id' => '1',
    ];
    $activity = $this->callAPISuccess('activity', 'create', $activityParams);

    $activityValues = array_pop($activity['values']);

    // valid parameters for above profile
    $profileParams = [
      'profile_id' => $ufGroupID,
      'contact_id' => $contactId,
      'activity_id' => $activityValues['id'],
    ];

    // expected result of above created profile
    $expected = [
      'first_name' => 'abc1',
      'last_name' => 'xyz1',
      'email-Primary' => 'abc1.xyz1@yahoo.com',
      'activity_subject' => 'Make-it-Happen Meeting',
      'activity_details' => 'a test activity',
      'activity_duration' => '120',
      'activity_date_time' => '2011-03-16 00:00:00',
      'activity_status_id' => '1',
    ];

    return [$profileParams, $expected];
  }

  /**
   * Create a profile.
   */
  public function createIndividualProfile(): void {
    $ufGroupParams = [
      'group_type' => 'Individual,Contact',
      // really we should remove this & test the ufField create sets it
      'name' => 'test_individual_contact_profile',
      'title' => 'Flat Coffee',
      'api.uf_field.create' => [
        [
          'field_name' => 'first_name',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Individual',
          'label' => 'First Name',
        ],
        [
          'field_name' => 'last_name',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Individual',
          'label' => 'Last Name',
        ],
        [
          'field_name' => 'email',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Contact',
          'label' => 'Email',
        ],
        [
          'field_name' => 'phone',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Contact',
          'location_type_id' => 1,
          'phone_type_id' => 1,
          'label' => 'Phone',
        ],
        [
          'field_name' => 'country',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Contact',
          'location_type_id' => 1,
          'label' => 'Country',
        ],
        [
          'field_name' => 'state_province',
          'is_required' => 1,
          'visibility' => 'Public Pages and Listings',
          'field_type' => 'Contact',
          'location_type_id' => 1,
          'label' => 'State Province',
        ],
        [
          'field_name' => 'postal_code',
          'is_required' => 0,
          'field_type' => 'Contact',
          'location_type_id' => 1,
          'label' => 'State Province',
        ],
      ],
    ];
    $profile = $this->callAPISuccess('uf_group', 'create', $ufGroupParams);
    $this->_profileID = $profile['id'];
  }

  /**
   * @param int $profileID
   */
  public function addCustomFieldToProfile(int $profileID): void {
    $ids = $this->entityCustomGroupWithSingleFieldCreate(__FUNCTION__, '');
    $this->uFFieldCreate([
      'uf_group_id' => $profileID,
      'field_name' => 'custom_' . $ids['custom_field_id'],
      'contact_type' => 'Contact',
    ]);
  }

}
