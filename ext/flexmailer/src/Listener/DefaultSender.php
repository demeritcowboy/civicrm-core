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
namespace Civi\FlexMailer\Listener;

use Civi\Core\Service\AutoService;
use Civi\FlexMailer\Event\SendBatchEvent;

/**
 * @service civi_flexmailer_default_sender
 */
class DefaultSender extends AutoService {

  use IsActiveTrait;

  const BULK_MAIL_INSERT_COUNT = 10;

  public function onSend(SendBatchEvent $e) {
    static $smtpConnectionErrors = 0;

    if (!$this->isActive()) {
      return;
    }

    $e->stopPropagation();

    $job = $e->getJob();
    $mailing = $e->getMailing();
    $useSparkpost = FALSE;
    $hotmailProblems = \Civi::settings()->get('torahinmotion_hotmail_problems') ?? FALSE;
    static $hotmail_domains = [
      'hotmail.fr' => 1,
      'live.com.au' => 1,
      'outlook.com' => 1,
      'live.co.uk' => 1,
      'live.com' => 1,
      'live.ca' => 1,
      'msn.com' => 1,
      'hotmail.ca' => 1,
      'hotmail.co.uk' => 1,
      'hotmail.com' => 1,
    ];
    $taskCount = count($e->getTasks());
    $sparkpost_current_limit = \Civi::settings()->get('torahinmotion_sparkpost_max') ?? 900;
    if ($taskCount < $sparkpost_current_limit) {
      $mailer_settings = \Civi::settings()->get('mailing_backend');
      $ch = curl_init('https://api.sparkpost.com/api/v1/usage');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_POST, FALSE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . \Civi::service('crypto.token')->decrypt($mailer_settings['smtpPassword']),
      ]);
      $response = curl_exec($ch);
      curl_close($ch);
      $usage = json_decode($response);
      if (!empty($usage->results)) {
        $usageDay = $usage->results->messaging->day->used ?? NULL;
        $usageMonth = $usage->results->messaging->month->used ?? NULL;
        if (($usageDay !== NULL && ($usageDay + $taskCount) < $sparkpost_current_limit)
          && ($usageMonth !== NULL && ($usageMonth + $taskCount) < 90000)) {
          $useSparkpost = TRUE;
        }
      }
    }
    $job_date = \CRM_Utils_Date::isoToMysql($job->scheduled_date);
    $mailer = \Civi::service('pear_mail');
    if ($useSparkpost) {
      $mailer_settings['smtpServer'] = 'smtp.sparkpostmail.com';
      $mailer_settings['smtpPort'] = '587';
      $mailer_settings['smtpAuth'] = '1';
      \Civi::settings()->set('mailing_backend', $mailer_settings);
      // We can't use the service because it is cached and has actually
      // been created in MailingJob.php at the beginning.
      $sparkpostmailer = \CRM_Utils_Mail::createMailer();
      $mailer_settings['smtpServer'] = 'mail';
      $mailer_settings['smtpPort'] = '25';
      $mailer_settings['smtpAuth'] = '0';
      \Civi::settings()->set('mailing_backend', $mailer_settings);
    }

    $targetParams = $deliveredParams = [];
    $count = 0;
    $retryBatch = FALSE;

    foreach ($e->getTasks() as $key => $task) {
      /** @var \Civi\FlexMailer\FlexMailerTask $task */
      /** @var \Mail_mime $message */
      if (!$task->hasContent()) {
        continue;
      }

      $params = $task->getMailParams();
      if (isset($params['abortMailSend']) && $params['abortMailSend']) {
        continue;
      }
      $isHotmail = FALSE;
      if ($useSparkpost && $hotmailProblems) {
        $pos = strrpos($params['toEmail'], '@');
        if ($pos !== FALSE) {
          $domain = strtolower(substr($params['toEmail'], $pos + 1));
          if (isset($hotmail_domains[$domain])) {
            $isHotmail = TRUE;
          }
        }
      }
      $message = \Civi\FlexMailer\MailParams::convertMailParamsToMime($params);

      if (empty($message)) {
        // lets keep the message in the queue
        // most likely a permissions related issue with smarty templates
        // or a bad contact id? CRM-9833
        continue;
      }

      // disable error reporting on real mailings (but leave error reporting for tests), CRM-5744
      if ($job_date) {
        $errorScope = \CRM_Core_TemporaryErrorScope::ignoreException();
      }

      $headers = $message->headers();
      if (!$useSparkpost || $isHotmail) {
        $result = $mailer->send($headers['To'], $message->headers(), $message->get());
      }
      else {
        $result = $sparkpostmailer->send($headers['To'], $message->headers(), $message->get());
      }

      if ($job_date) {
        unset($errorScope);
      }

      if (is_a($result, 'PEAR_Error')) {
        /** @var \PEAR_Error $result */
        // CRM-9191
        $message = $result->getMessage();
        if ($this->isTemporaryError($result->getMessage())) {
          // lets log this message and code
          $code = $result->getCode();
          \CRM_Core_Error::debug_log_message("SMTP Socket Error or failed to set sender error. Message: $message, Code: $code");

          // these are socket write errors which most likely means smtp connection errors
          // lets skip them and reconnect.
          $smtpConnectionErrors++;
          if ($smtpConnectionErrors <= 5) {
            $mailer->disconnect();
            $retryBatch = TRUE;
            continue;
          }

          // seems like we have too many of them in a row, we should
          // write stuff to disk and abort the cron job
          $job->writeToDB($deliveredParams, $targetParams, $mailing, $job_date);

          \CRM_Core_Error::debug_log_message("Too many SMTP Socket Errors. Exiting");
          \CRM_Utils_System::civiExit();
        }
        else {
          $this->recordBounce($job, $task, $result->getMessage());
        }
      }
      else {
        // Register the delivery event.
        $deliveredParams[] = $task->getEventQueueId();
        $targetParams[] = $task->getContactId();

        $count++;
        if ($count % self::BULK_MAIL_INSERT_COUNT == 0) {
          $job->writeToDB($deliveredParams, $targetParams, $mailing, $job_date);
          $count = 0;

          // hack to stop mailing job at run time, CRM-4246.
          // to avoid making too many DB calls for this rare case
          // lets do it when we snapshot
          $status = \CRM_Core_DAO::getFieldValue(
            'CRM_Mailing_DAO_MailingJob',
            $job->id,
            'status',
            'id',
            TRUE
          );

          if ($status != 'Running') {
            $e->setCompleted(FALSE);
            return;
          }
        }
      }

      unset($result);

      // seems like a successful delivery or bounce, lets decrement error count
      // only if we have smtp connection errors
      if ($smtpConnectionErrors > 0) {
        $smtpConnectionErrors--;
      }

      // If we have enabled the Throttle option, this is the time to enforce it.
      $mailThrottleTime = \CRM_Core_Config::singleton()->mailThrottleTime;
      if (!empty($mailThrottleTime)) {
        usleep((int) $mailThrottleTime);
      }
    }

    $completed = $job->writeToDB(
      $deliveredParams,
      $targetParams,
      $mailing,
      $job_date
    );
    if ($retryBatch) {
      $completed = FALSE;
    }
    $e->setCompleted($completed);
  }

  /**
   * Determine if an SMTP error is temporary or permanent.
   *
   * @param string $message
   *   PEAR error message.
   * @return bool
   *   TRUE - Temporary/retriable error
   *   FALSE - Permanent/non-retriable error
   */
  protected function isTemporaryError($message) {
    // SMTP response code is buried in the message.
    $code = preg_match('/ \(code: (.+), response: /', $message, $matches) ? $matches[1] : '';

    if (str_contains($message, 'Failed to write to socket')) {
      return TRUE;
    }

    // Register 5xx SMTP response code (permanent failure) as bounce.
    if (isset($code[0]) && $code[0] === '5') {
      return FALSE;
    }

    // Consider SMTP Erorr 450, class 4.1.2 "Domain not found", as permanent failures if the corresponding setting is enabled
    if ($code === '450' && \Civi::settings()->get('smtp_450_is_permanent')) {
      $class = preg_match('/ \(code: (.+), response: ([0-9\.]+) /', $message, $matches) ? $matches[2] : '';
      if ($class === '4.1.2') {
        return FALSE;
      }
    }

    if (str_contains($message, 'Failed to set sender')) {
      return TRUE;
    }

    if (str_contains($message, 'Failed to add recipient')) {
      return TRUE;
    }

    if (str_contains($message, 'Failed to send data')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * @param \CRM_Mailing_BAO_MailingJob $job
   * @param \Civi\FlexMailer\FlexMailerTask $task
   * @param string $errorMessage
   */
  protected function recordBounce($job, $task, $errorMessage) {
    $params = [
      'event_queue_id' => $task->getEventQueueId(),
      'job_id' => $job->id,
      'hash' => $task->getHash(),
    ];
    $params = array_merge($params,
      \CRM_Mailing_BAO_BouncePattern::match($errorMessage)
    );
    \CRM_Mailing_Event_BAO_MailingEventBounce::recordBounce($params);
  }

}
