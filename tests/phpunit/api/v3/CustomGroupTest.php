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
 *  Test APIv3 civicrm_custom_group* functions
 *
 * @package CiviCRM_APIv3
 * @subpackage API_CustomGroup
 * @group headless
 */
class api_v3_CustomGroupTest extends CiviUnitTestCase {
  protected $_apiversion = 3;
  protected $_entity;
  protected $_params;

  public $DBResetRequired = TRUE;

  public function setUp(): void {
    $this->_entity = 'CustomGroup';
    $this->_params = [
      'title' => 'Test_Group_1',
      'name' => 'test_group_1',
      'extends' => 'Individual',
      'weight' => 4,
      'collapse_display' => 1,
      'style' => 'Inline',
      'help_pre' => 'This is Pre Help For Test Group 1',
      'help_post' => 'This is Post Help For Test Group 1',
      'is_active' => 1,
    ];
    parent::setUp();
  }

  public function tearDown(): void {
    $tablesToTruncate = ['civicrm_custom_group', 'civicrm_custom_field'];
    // true tells quickCleanup to drop any tables that might have been created in the test
    $this->quickCleanup($tablesToTruncate, TRUE);
  }

  ///////////////// civicrm_custom_group_create methods

  /**
   * Check with empty array.
   * note that these tests are of marginal value so should not be included in copy & paste
   * code. The SyntaxConformance is capable of testing this for all entities on create
   * & delete (& it would be easy to add if not there)
   */
  public function testCustomGroupCreateNoParam(): void {
    $customGroup = $this->callAPIFailure('custom_group', 'create', [],
      'Mandatory key(s) missing from params array: title, extends'
    );
  }

  /**
   * Check with empty array.
   */
  public function testCustomGroupCreateNoExtends(): void {
    $params = [
      'domain_id' => 1,
      'title' => 'Test_Group_1',
      'name' => 'test_group_1',
      'weight' => 4,
      'collapse_display' => 1,
      'style' => 'Tab',
      'help_pre' => 'This is Pre Help For Test Group 1',
      'help_post' => 'This is Post Help For Test Group 1',
      'is_active' => 1,
    ];

    $customGroup = $this->callAPIFailure('custom_group', 'create', $params);
    $this->assertEquals($customGroup['error_message'], 'Mandatory key(s) missing from params array: extends');
    $this->assertAPIFailure($customGroup);
  }

  /**
   * Check with empty array.
   */
  public function testCustomGroupCreateInvalidExtends(): void {
    $params = [
      'domain_id' => 1,
      'title' => 'Test_Group_1',
      'name' => 'test_group_1',
      'weight' => 4,
      'collapse_display' => 1,
      'style' => 'Tab',
      'help_pre' => 'This is Pre Help For Test Group 1',
      'help_post' => 'This is Post Help For Test Group 1',
      'is_active' => 1,
      'extends' => [],
    ];

    $customGroup = $this->callAPIFailure('custom_group', 'create', $params);
    $this->assertEquals($customGroup['error_message'], 'Mandatory key(s) missing from params array: extends');
  }

  /**
   * Check with a string instead of array for extends.
   */
  public function testCustomGroupCreateExtendsString(): void {
    $params = [
      'domain_id' => 1,
      'title' => 'Test_Group_1',
      'name' => 'test_group_1',
      'weight' => 4,
      'collapse_display' => 1,
      'style' => 'Tab',
      'help_pre' => 'This is Pre Help For Test Group 1',
      'help_post' => 'This is Post Help For Test Group 1',
      'is_active' => 1,
      'extends' => 'Individual',
    ];

    $customGroup = $this->callAPISuccess('custom_group', 'create', $params);
  }

  /**
   * Check with valid array.
   */
  public function testCustomGroupCreate(): void {
    $params = [
      'title' => 'Test_Group_1',
      'name' => 'test_group_1',
      'extends' => ['Individual'],
      'weight' => 4,
      'collapse_display' => 1,
      'style' => 'Inline',
      'help_pre' => 'This is Pre Help For Test Group 1',
      'help_post' => 'This is Post Help For Test Group 1',
      'is_active' => 1,
    ];

    $result = $this->callAPISuccess('custom_group', 'create', $params);
    $this->assertNotNull($result['id']);
    $this->assertEquals($result['values'][$result['id']]['extends'], 'Individual');
  }

  /**
   * Check with valid array.
   */
  public function testCustomGroupGetFields(): void {
    $params = [
      'options' => ['get_options' => 'style'],
    ];

    $result = $this->callAPISuccess('custom_group', 'getfields', $params);
    $expected = [
      'Tab' => 'Tab',
      'Inline' => 'Inline',
      'Tab with table' => 'Tab with table',
    ];
    $this->assertEquals($expected, $result['values']['style']['options']);
  }

  /**
   * Check with style missing from params array.
   */
  public function testCustomGroupCreateNoStyle(): void {
    $params = [
      'title' => 'Test_Group_1',
      'name' => 'test_group_1',
      'extends' => ['Individual'],
      'weight' => 4,
      'collapse_display' => 1,
      'help_pre' => 'This is Pre Help For Test Group 1',
      'help_post' => 'This is Post Help For Test Group 1',
      'is_active' => 1,
    ];

    $customGroup = $this->callAPISuccess('custom_group', 'create', $params);
    $this->assertNotNull($customGroup['id']);
    $this->assertEquals($customGroup['values'][$customGroup['id']]['style'], 'Inline');
  }

  /**
   * Check without title.
   */
  public function testCustomGroupCreateNoTitle(): void {
    $params = [
      'extends' => ['Contact'],
      'weight' => 5,
      'collapse_display' => 1,
      'style' => 'Tab',
      'help_pre' => 'This is Pre Help For Test Group 2',
      'help_post' => 'This is Post Help For Test Group 2',
    ];

    $customGroup = $this->callAPIFailure('custom_group', 'create', $params,
      'Mandatory key(s) missing from params array: title');
  }

  /**
   * Check for household without weight.
   */
  public function testCustomGroupCreateHouseholdNoWeight(): void {
    $params = [
      'title' => 'Test_Group_3',
      'name' => 'test_group_3',
      'extends' => ['Household'],
      'collapse_display' => 1,
      'style' => 'Tab',
      'help_pre' => 'This is Pre Help For Test Group 3',
      'help_post' => 'This is Post Help For Test Group 3',
      'is_active' => 1,
    ];

    $customGroup = $this->callAPISuccess('custom_group', 'create', $params);
    $this->assertNotNull($customGroup['id']);
    $this->assertEquals($customGroup['values'][$customGroup['id']]['extends'], 'Household');
    $this->assertEquals($customGroup['values'][$customGroup['id']]['style'], 'Tab');
  }

  /**
   * Check for Contribution Donation.
   */
  public function testCustomGroupCreateContributionDonation(): void {
    $params = [
      'title' => 'Test_Group_6',
      'name' => 'test_group_6',
      'extends' => ['Contribution', [1]],
      'weight' => 6,
      'collapse_display' => 1,
      'style' => 'Inline',
      'help_pre' => 'This is Pre Help For Test Group 6',
      'help_post' => 'This is Post Help For Test Group 6',
      'is_active' => 1,
    ];

    $customGroup = $this->callAPISuccess('custom_group', 'create', $params);
    $this->assertNotNull($customGroup['id']);
    $this->assertEquals($customGroup['values'][$customGroup['id']]['extends'], 'Contribution');
  }

  /**
   * Check with valid array.
   */
  public function testCustomGroupCreateGroup(): void {
    $params = [
      'domain_id' => 1,
      'title' => 'Test_Group_8',
      'name' => 'test_group_8',
      'extends' => ['Group'],
      'weight' => 7,
      'collapse_display' => 1,
      'is_active' => 1,
      'style' => 'Inline',
      'help_pre' => 'This is Pre Help For Test Group 8',
      'help_post' => 'This is Post Help For Test Group 8',
    ];

    $customGroup = $this->callAPISuccess('CustomGroup', 'create', $params);
    $this->assertNotNull($customGroup['id']);
    $this->assertEquals($customGroup['values'][$customGroup['id']]['extends'], 'Group');
  }

  /**
   * Test an empty update does not trigger e-notices when is_multiple has been set.
   */
  public function testCustomGroupEmptyUpdate(): void {
    $customGroup = $this->callAPISuccess('CustomGroup', 'create', array_merge($this->_params, ['is_multiple' => 1]));
    $this->callAPISuccess('CustomGroup', 'create', ['id' => $customGroup['id']]);
  }

  /**
   * Test an update when is_multiple is an emtpy string this can occur in form submissions for custom groups that extend activites.
   * dev/core#227.
   */
  public function testCustomGroupEmptyisMultipleUpdate(): void {
    $customGroup = $this->callAPISuccess('CustomGroup', 'create', array_merge($this->_params, ['is_multiple' => 0]));
    $this->callAPISuccess('CustomGroup', 'create', ['id' => $customGroup['id'], 'is_multiple' => '']);
  }

  /**
   * Check with Activity - Meeting Type
   */
  public function testCustomGroupCreateActivityMeeting(): void {
    $params = [
      'title' => 'Test_Group_10',
      'name' => 'test_group_10',
      'extends' => ['Activity', [1]],
      'weight' => 8,
      'collapse_display' => 1,
      'style' => 'Inline',
      'help_pre' => 'This is Pre Help For Test Group 10',
      'help_post' => 'This is Post Help For Test Group 10',
    ];

    $customGroup = $this->callAPISuccess('custom_group', 'create', $params);
    $this->assertNotNull($customGroup['id']);
    $this->assertEquals($customGroup['values'][$customGroup['id']]['extends'], 'Activity');
  }

  ///////////////// civicrm_custom_group_delete methods

  /**
   * Check without GroupID.
   */
  public function testCustomGroupDeleteWithoutGroupID(): void {
    $customGroup = $this->callAPIFailure('custom_group', 'delete', []);
    $this->assertEquals($customGroup['error_message'], 'Mandatory key(s) missing from params array: id');
  }

  /**
   * Check with valid custom group id.
   */
  public function testCustomGroupDelete(): void {
    $customGroup = $this->customGroupCreate(['extends' => 'Individual', 'title' => 'test_group']);
    $params = [
      'id' => $customGroup['id'],
    ];
    $result = $this->callAPISuccess('custom_group', 'delete', $params);
    $this->assertAPISuccess($result);
  }

  /**
   * Main success get function.
   */
  public function testGetCustomGroupSuccess(): void {

    $this->callAPISuccess($this->_entity, 'create', $this->_params);
    $params = [];
    $result = $this->callAPISuccess($this->_entity, 'get', $params);
    $values = $result['values'][$result['id']];
    foreach ($this->_params as $key => $value) {
      if ($key == 'weight') {
        continue;
      }
      $this->assertEquals($value, $values[$key], $key . " doesn't match " . print_r($values, TRUE) . 'in line' . __LINE__);
    }
  }

  public function testUpdateCustomGroup(): void {
    $customGroup = $this->customGroupCreate();
    $customGroupId = $customGroup['id'];

    //update is_active
    $params = ['id' => $customGroupId, 'is_active' => 0];
    $result = $this->callAPISuccess('CustomGroup', 'create', $params);
    $result = array_shift($result['values']);

    $this->assertEquals(0, $result['is_active']);
    $this->customGroupDelete($customGroupId);
  }

  /**
   * Test that as per the form that if the extends column is passed as
   * - ['ParticipantEventType', [4]] Where 4 = Meeting Event Type that we can create a custom group correctly
   */
  public function testParticipantEntityCustomGroup(): void {
    $customGroup = $this->callAPISuccess($this->_entity, 'create', array_merge($this->_params, ['extends' => ['ParticipantEventType', [4]]]));
    $result = array_shift($customGroup['values']);
    $this->assertEquals(3, $result['extends_entity_column_id']);
    $this->assertEquals('Participant', $result['extends']);
    $this->customGroupDelete($result['id']);
  }

  /**
   * Test that without any fields we can change the entity type of the custom group and fields are correctly updated
   */
  public function testChangeEntityCustomGroup(): void {
    $customGroup = $this->callAPISuccess($this->_entity, 'create', array_merge($this->_params, ['extends' => ['ParticipantEventType', [4]]]));
    $result = array_shift($customGroup['values']);
    $this->assertEquals(3, $result['extends_entity_column_id']);
    $this->assertEquals('Participant', $result['extends']);
    $customGroup = $this->callAPISuccess($this->_entity, 'create', ['id' => $customGroup['id'], 'extends' => ['Individual', []]]);
    $result = array_shift($customGroup['values']);
    $this->assertTrue(empty($result['extends_entity_column_id']));
    $this->assertTrue(empty($result['extends_entity_column_value']));
    $this->assertEquals('Individual', $result['extends']);
    $this->customGroupDelete($result['id']);
  }

}
