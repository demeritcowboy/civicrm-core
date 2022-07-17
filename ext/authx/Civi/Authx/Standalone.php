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

namespace Civi\Authx;

class Standalone implements AuthxInterface {

  /**
   * @inheritDoc
   */
  public function checkPassword(string $username, string $password) {
    // @todo It sounded like we want to allow multiple login services active at the same time, i.e. one user might be a local user, another might log in via google. How this gets done might change.
    // \Civi::service('standalone.auth')->authenticate($username, $password);
    $uid = \Civi\Standalone\LoginService::singleton()->authenticate($username, $password);
    return $uid ? $uid : NULL;
  }

  /**
   * @inheritDoc
   */
  public function loginSession($userId) {
    // don't care
  }

  /**
   * @inheritDoc
   */
  public function logoutSession() {
    // do we need to deal with php session?
    \CRM_Core_Session::singleton()->reset();
  }

  /**
   * @inheritDoc
   */
  public function loginStateless($userId) {
    // don't think this matters
  }

  /**
   * @inheritDoc
   */
  public function getCurrentUserId() {
    $ufmatch = new \CRM_Core_DAO_UFMatch();
    $ufmatch->contact_id = \CRM_Core_Session::getLoggedInContactID();
    $ufmatch->domain_id = \CRM_Core_Config::domainID();
    if ($ufmatch->find(TRUE)) {
      return $ufmatch->uf_id;
    }
    return 0;
  }

}
