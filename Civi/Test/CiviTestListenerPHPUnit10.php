<?php

namespace Civi\Test;

use PHPUnit\Event\TestSuite\StartedSubscriber as TestSuiteStartedSubscriber;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\Runner\Extension\Extension as ExtensionInterface;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;

final class CiviTestListenerPHPUnit10 implements ExtensionInterface {

  /**
   * @var \CRM_Core_Transaction|null
   */
  private $tx;

  /**
   * @var \CRM_Core_TemporaryErrorScope|null
   */
  public $errorScope;

  public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void {

    $facade->registerSubscriber(new class($this) implements TestSuiteStartedSubscriber {
      private $ext;
      public function __construct(CiviTestListenerPHPUnit10 $ext) {
        $this->ext = $ext;
      }

      public function notify(\PHPUnit\Event\TestSuite\Started $event): void {
        // Bingbot says: No test objects here, but you could use bootstrap.php for pre-validation/boot logic
        // Dave says: This might be useful https://github.com/sebastianbergmann/phpunit/issues/5769#issuecomment-2024531149
        // $this->ext->validateGroups(...);
        // $this->ext->autoboot(...);
        if (defined('CIVICRM_UF')) {
          // OK, nothing we can do. System has booted already.
        }
        else {
          // @todo this only makes sense for headless but just trying to get running first - see original autoboot()
          putenv('CIVICRM_UF=UnitTests');
          eval($this->cv('php:boot --level=full', 'phpcode'));
        }
      }
    });

    $facade->registerSubscriber(new class($this) implements PreparedSubscriber {
      private $ext;
      public function __construct(CiviTestListenerPHPUnit10 $ext) {
        $this->ext = $ext;
      }

      public function notify(\PHPUnit\Event\Test\Prepared $event): void {
        $testMetadata = $event->test();
        $testClass = $testMetadata->className();
        $testInstance = $this->ext->instantiateTest($testClass);

        if ($this->ext->isCiviTest($testInstance)) {
          error_reporting(E_ALL);
          $GLOBALS['CIVICRM_TEST_CASE'] = $testInstance;
        }

        if ($testInstance instanceof HeadlessInterface) {
          $this->ext->bootHeadless($testInstance);
        }

        if ($testInstance instanceof TransactionalInterface) {
          $this->ext->setTx(new \CRM_Core_Transaction(TRUE));
          $this->ext->getTx()->rollback();
        } else {
          $this->ext->setTx(NULL);
        }

        if ($this->ext->isCiviTest($testInstance)) {
          \Civi\Test::eventChecker()->start($testInstance);
        }
      }
    });

    $facade->registerSubscriber(new class($this) implements FinishedSubscriber {
      private $ext;
      public function __construct(CiviTestListenerPHPUnit10 $ext) {
        $this->ext = $ext;
      }

      public function notify(\PHPUnit\Event\Test\Finished $event): void {
        $testMetadata = $event->test();
        $testClass = $testMetadata->className();
        $testInstance = $this->ext->instantiateTest($testClass);

        $exception = NULL;

        try {
          if ($this->ext->isCiviTest($testInstance)) {
            \Civi\Test::eventChecker()->stop($testInstance);
          }
        } catch (\Exception $e) {
          $exception = $e;
        }

        if ($testInstance instanceof TransactionalInterface) {
          $this->ext->getTx()->rollback()->commit();
          $this->ext->setTx(NULL);
        }

        if ($testInstance instanceof HookInterface) {
          \CRM_Utils_Hook::singleton()->reset();
        }

        \CRM_Utils_Time::resetTime();

        if ($this->ext->isCiviTest($testInstance)) {
          unset($GLOBALS['CIVICRM_TEST_CASE']);
          // Several tests neglect to clean this up...
          unset($_SERVER['HTTP_X_REQUESTED_WITH']);
          error_reporting(E_ALL & ~E_NOTICE);
          $this->ext->errorScope = null;
        }

        if ($exception) {
          throw $exception;
        }
      }
    });
  }

  public function instantiateTest(string $className): object {
    // Bingbot says: Assumes your test has a parameterless constructor
    // Dave says: That assumption isn't true here so this doesn't work. There doesn't seem to be any way to get at the actual test instance. We can use metadata, but there's nothing already in place to use.
    return new $className('dontcare');
  }

  public function isCiviTest($test): bool {
    return $test instanceof HookInterface || $test instanceof HeadlessInterface || $test instanceof \CiviUnitTestCase;
  }

  /**
   * @param HeadlessInterface|\PHPUnit\Framework\Test $test
   */
  public function bootHeadless($test): void {
    if (CIVICRM_UF !== 'UnitTests') {
      throw new \RuntimeException('HeadlessInterface requires CIVICRM_UF=UnitTests');
    }

    // Hrm, this seems wrong. Shouldn't we be resetting the entire session?
    \CRM_Core_Session::singleton()->set('userID', null);

    $test->setUpHeadless();

    \Civi::rebuild(['system' => TRUE])->execute();
    \Civi::reset();
    \CRM_Core_Session::singleton()->set('userID', null);
    // ugh, performance
    $config = \CRM_Core_Config::singleton(true, true);
    $config->userSystem->setMySQLTimeZone();

    if (property_exists($config->userPermissionClass, 'permissions')) {
      $config->userPermissionClass->permissions = null;
    }
  }

  /**
   * Call the "cv" command.
   *
   * This duplicates the standalone `cv()` wrapper that is recommended in bootstrap.php.
   * This duplication is necessary because `cv()` is optional, and downstream implementers
   * may alter, rename, or omit the wrapper, and (by virtue of its role in bootstrap) there
   * it is impossible to define it centrally.
   *
   * @param string $cmd
   *   The rest of the command to send.
   * @param string $decode
   *   Ex: 'json' or 'phpcode'.
   * @return string
   *   Response output (if the command executed normally).
   * @throws \RuntimeException
   *   If the command terminates abnormally.
   */
  protected function cv($cmd, $decode = 'json') {
    $cmd = 'cv ' . $cmd;
    $descriptorSpec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => STDERR];
    $oldOutput = getenv('CV_OUTPUT');
    putenv("CV_OUTPUT=json");
    $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__);
    putenv("CV_OUTPUT=$oldOutput");
    fclose($pipes[0]);
    $result = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
      throw new \RuntimeException("Command failed ($cmd):\n$result");
    }
    switch ($decode) {
    case 'raw':
      return $result;

    case 'phpcode':
      // If the last output is /*PHPCODE*/, then we managed to complete execution.
      if (substr(trim($result), 0, 12) !== "/*BEGINPHP*/" || substr(trim($result), -10) !== "/*ENDPHP*/") {
        throw new \RuntimeException("Command failed ($cmd):\n$result");
      }
      return $result;

    case 'json':
      return json_decode($result, 1);

    default:
      throw new \RuntimeException("Bad decoder format ($decode)");
    }
  }

  /**
   * @return \CRM_Core_Transaction|null
   */
  public function getTx(): ?\CRM_Core_Transaction {
    return $this->tx;
  }

  /**
   * @var \CRM_Core_Transaction|null
   */
  public function setTx(?\CRM_Core_Transaction $tx): void {
    $this->tx = $tx;
  }

}
