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
 * Class CRM_Logging_ActivityReportSummary
 */
class CRM_Logging_ActivityReportSummary extends CRM_Logging_ReportSummary {

  /**
   * @inheritdoc
   */
  protected function setLogTables(): void {
    $this->_logTables = [
      'log_civicrm_activity' => [
        'fk' => 'id',
      ],
      'log_civicrm_entity_tag' => [
        'fk' => 'entity_id',
        'bracket_info' => [
          'entity_column' => 'tag_id',
          'table' => 'log_civicrm_tag',
          'column' => 'name',
        ],
        'entity_table' => TRUE,
      ],
      'log_civicrm_activity_contact' => [
        'fk' => 'activity_id',
        'table_name' => 'log_civicrm_activity_contact',
        'log_type' => 'Activity Contact',
        'field' => 'contact_id',
        'extra_joins' => [
          'table' => 'log_civicrm_contact',
          'join' => 'extra_table.id = entity_log_civireport.contact_id',
        ],
        'bracket_info' => [
          'entity_column' => 'display_name',
          'lookup_table' => 'log_civicrm_contact',
        ],
      ],
    ];

    $logging = new CRM_Logging_Schema();

    // build _logTables for activity custom tables
    $customTables = $logging->entityCustomDataLogTables('Activity');
    foreach ($customTables as $table) {
      $this->_logTables[$table] = [
        'fk' => 'entity_id',
        'log_type' => 'Activity',
      ];
    }
  }

  /**
   * @inheritdoc
   */
  public function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    if ($this->currentLogTable == 'log_civicrm_activity_contact' && $fieldName == 'id') {
      $alias = "{$tableName}_{$fieldName}";
      $select[] = "{$tableName}.activity_id as $alias";
      $this->_selectAliases[] = $alias;
      return "activity_id";
    }
    if ($fieldName == 'log_grouping') {
      return 1;
    }
  }

  /**
   * Build the report query.
   *
   * We override this in order to be able to run from the api.
   *
   * @param bool $applyLimit
   *
   * @return string
   */
  public function buildQuery($applyLimit = TRUE) {
    if (!$this->logTypeTableClause) {
      return parent::buildQuery($applyLimit);
    }
    // note the group by columns are same as that used in alterDisplay as $newRows - $key
    $this->limit();
    $this->orderBy();
    $sql = "{$this->_select}
FROM {$this->temporaryTableName} entity_log_civireport
WHERE {$this->logTypeTableClause}
GROUP BY log_civicrm_entity_log_date, log_civicrm_entity_log_type_label, log_civicrm_entity_log_conn_id, log_civicrm_entity_log_user_id, log_civicrm_entity_altered_contact_id, log_civicrm_entity_log_grouping
{$this->_orderBy}
{$this->_limit} ";
    $sql = str_replace('modified_activity_civireport.subject', 'entity_log_civireport.altered_contact', $sql);
    $sql = str_replace('modified_activity_civireport.id', 'entity_log_civireport.altered_contact_id', $sql);
    $sql = str_replace([
      'modified_activity_civireport.',
      'altered_by_contact_civireport.',
    ], 'entity_log_civireport.', $sql);
    return $sql;
  }

}
