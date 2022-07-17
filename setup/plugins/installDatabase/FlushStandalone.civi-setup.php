<?php
/**
 * @file
 *
 * Finalize any extra CMS changes in Standalone.
 */

if (!defined('CIVI_SETUP')) {
  exit("Installation plugins must only be loaded by the installer.\n");
}

\Civi\Setup::dispatcher()
  ->addListener('civi.setup.installDatabase', function (\Civi\Setup\Event\InstallDatabaseEvent $e) {
    if ($e->getModel()->cms !== 'Standalone') {
      return;
    }
    \Civi\Setup::log()->info(sprintf('[%s] Flush CMS metadata', basename(__FILE__)));

    civicrm_install_set_standalone_perms();

  }, \Civi\Setup::PRIORITY_LATE - 50);

function civicrm_install_set_standalone_perms() {
  $perms = array(
    'access all custom data',
    'access uploaded files',
    'make online contributions',
    'profile create',
    'profile edit',
    'profile view',
    'register for events',
    'view event info',
    'view event participants',
    'access CiviMail subscribe/unsubscribe pages',
  );

  // @todo Not sure how permissions are physically managed yet.
  $allPerms = [];
  foreach (array_diff($perms, $allPerms) as $perm) {
    \Civi::log()->error('Cannot grant the %perm permission because it does not yet exist.', [
      '%perm' => $perm,
    ]);
  }
  $perms = array_intersect($perms, $allPerms);
  // todo: Grant $perms to both authenticated and anonymous roles. There may not be an equivalent of "authenticated".
}
