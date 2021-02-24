<?php
//require_once 'CiviTest/CiviCaseTestCase.php';

/**
 * Class CRM_Case_Form_Activity_ChangeCaseStatusTest
 * @group headless
 */
class CRM_Case_Form_Activity_ChangeCaseStatusTest extends CiviCaseTestCase {

  /**
   * Test endPostProcess
   * @dataProvider endPostProcessProvider
   * @param array $input
   * @param array $expected
   */
  public function testEndPostProcess(array $input, array $expected): void {
    $form = new CRM_Case_Form_Activity_ChangeCaseStatus();
    $form->_caseStatus = CRM_Case_BAO_Case::buildOptions('case_status_id', 'create');
    $caseStatusNames = array_flip(CRM_Case_BAO_Case::buildOptions('case_status_id', 'validate'));
    $form->_oldCaseStatus = [
      $caseStatusNames[$input['old_case_status']],
    ];

    $now_date = date('YmdHis');
    $params = [
      'case_status_id' => $caseStatusNames[$input['case_status']],
      'activity_date_time' => $now_date,
    ];

    $activity = new CRM_Activity_DAO_Activity();
    $activity->subject = $input['activity_subject'];

    $form->endPostProcess($form, $params, $activity);

    // At this point not everything is exactly like it would be in a full
    // situation, e.g. the activity hasn't been connected to the case,
    // we've left out variables we don't care about, etc.

    // activity subject might get changed in a predictable way
    $this->assertEquals($expected['activity_subject'], $activity->subject);

    // check case end date, which is now in the updated $params
    $expectedEndDate = $expected['end_date'] ?? $now_date;
    $this->assertEquals($expectedEndDate, $params['end_date']);
  }

  /**
   * dataProvider for testEndPostProcess
   * @return array
   */
  public function endPostProcessProvider(): array {
    return [
      0 => [
        'input' => [
          'old_case_status' => 'Open',
          'case_status' => 'Closed',
          'activity_subject' => 'bababa',
        ],
        'expected' => [
          'activity_subject' => 'bababa',
        ],
      ],
      1 => [
        'input' => [
          'old_case_status' => 'Open',
          'case_status' => 'Closed',
          // yes, the string 'null'
          'activity_subject' => 'null',
        ],
        'expected' => [
          'activity_subject' => 'Case status changed from Ongoing to Resolved',
        ],
      ],
      2 => [
        'input' => [
          'old_case_status' => 'Closed',
          'case_status' => 'Open',
          'activity_subject' => 'bababa',
        ],
        'expected' => [
          'activity_subject' => 'bababa',
          'end_date' => 'null',
        ],
      ],
      3 => [
        'input' => [
          'old_case_status' => 'Closed',
          'case_status' => 'Open',
          'activity_subject' => 'null',
        ],
        'expected' => [
          'activity_subject' => 'Case status changed from Resolved to Ongoing',
          'end_date' => 'null',
        ],
      ],
      4 => [
        'input' => [
          'old_case_status' => 'Urgent',
          'case_status' => 'Closed',
          'activity_subject' => 'null',
        ],
        'expected' => [
          'activity_subject' => 'Case status changed from Urgent to Resolved',
        ],
      ],
      5 => [
        'input' => [
          'old_case_status' => 'Closed',
          'case_status' => 'Urgent',
          'activity_subject' => 'null',
        ],
        'expected' => [
          'activity_subject' => 'Case status changed from Resolved to Urgent',
          'end_date' => 'null',
        ],
      ],
      6 => [
        'input' => [
          'old_case_status' => 'Urgent',
          'case_status' => 'Closed',
          'activity_subject' => 'bababa',
        ],
        'expected' => [
          'activity_subject' => 'bababa',
        ],
      ],
      7 => [
        'input' => [
          'old_case_status' => 'Closed',
          'case_status' => 'Urgent',
          'activity_subject' => 'bababa',
        ],
        'expected' => [
          'activity_subject' => 'bababa',
          'end_date' => 'null',
        ],
      ],
      8 => [
        'input' => [
          'old_case_status' => 'Urgent',
          'case_status' => 'Open',
          'activity_subject' => 'bababa',
        ],
        'expected' => [
          'activity_subject' => 'bababa',
          'end_date' => 'null',
        ],
      ],
      9 => [
        'input' => [
          'old_case_status' => 'Urgent',
          'case_status' => 'Open',
          'activity_subject' => 'null',
        ],
        'expected' => [
          'activity_subject' => 'Case status changed from Urgent to Ongoing',
          'end_date' => 'null',
        ],
      ],
      10 => [
        'input' => [
          'old_case_status' => 'Open',
          'case_status' => 'Urgent',
          'activity_subject' => 'bababa',
        ],
        'expected' => [
          'activity_subject' => 'bababa',
          'end_date' => 'null',
        ],
      ],
      11 => [
        'input' => [
          'old_case_status' => 'Open',
          'case_status' => 'Urgent',
          'activity_subject' => 'null',
        ],
        'expected' => [
          'activity_subject' => 'Case status changed from Ongoing to Urgent',
          'end_date' => 'null',
        ],
      ],
    ];
  }

}
