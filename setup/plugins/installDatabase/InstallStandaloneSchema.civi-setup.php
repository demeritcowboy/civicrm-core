<?php
/**
 * @file
 *
 * Populate the database schema.
 */

if (!defined('CIVI_SETUP')) {
  exit("Installation plugins must only be loaded by the installer.\n");
}

class InstallStandaloneSchemaPlugin implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      'civi.setup.installDatabase' => [
        ['installDatabase', 0],
      ],
    ];
  }

  public function installDatabase(\Civi\Setup\Event\InstallDatabaseEvent $e) {
    if ($e->getModel()->cms !== 'Standalone') {
      return;
    }

    \Civi\Setup::log()->info(sprintf('[%s] Install database schema', basename(__FILE__)));

    $model = $e->getModel();
    $spec = $this->loadSpecification($model->srcPath);

    \Civi\Setup::log()->info(sprintf('[%s] Load basic tables', basename(__FILE__)));
    \Civi\Setup\DbUtil::sourceSQL($model->db, \Civi\Setup\SchemaGenerator::generateCreateSql($model->srcPath, $spec->database, $spec->tables));
  }

  /**
   * @param string $srcPath
   * @return \CRM_Core_CodeGen_Specification
   */
  protected function loadSpecification($srcPath) {
    // @todo this feels hacky
    $schemaFile = implode(DIRECTORY_SEPARATOR, [$srcPath, 'xml', 'schema', 'Standalone', 'Schema.xml']);
    $versionFile = implode(DIRECTORY_SEPARATOR, [$srcPath, 'xml', 'version.xml']);
    $xmlBuilt = \CRM_Core_CodeGen_Util_Xml::parse($versionFile);
    $buildVersion = preg_replace('/^(\d{1,2}\.\d{1,2})\.(\d{1,2}|\w{4,7})$/i', '$1', $xmlBuilt->version_no);
    $specification = new \CRM_Core_CodeGen_Specification();
    $specification->parse($schemaFile, $buildVersion, FALSE);
    return $specification;
  }

}

\Civi\Setup::dispatcher()->addSubscriber(new InstallStandaloneSchemaPlugin());
