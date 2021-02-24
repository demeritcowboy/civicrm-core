{* file to handle db changes in 5.36.alpha1 during upgrade *}

UPDATE civicrm_relationship r INNER JOIN civicrm_case c ON c.id = r.case_id SET r.end_date = NULL WHERE c.end_date IS NOT NULL AND r.is_active = 1 AND r.end_date IS NOT NULL;
