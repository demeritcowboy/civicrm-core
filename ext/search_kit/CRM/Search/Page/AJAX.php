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
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

/**
 * Ajax endpoints
 */
class CRM_Search_Page_AJAX {

  use \Civi\Api4\Action\SearchDisplay\DownloadTrait;

  public static function download() {
    CRM_Utils_JSON::output($something);
  }

}
