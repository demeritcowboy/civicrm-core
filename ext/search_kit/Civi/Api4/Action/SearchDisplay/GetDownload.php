<?php

namespace Civi\Api4\Action\SearchDisplay;

/**
 * This is a combination of the run action and the download action, to get
 * the results of the download as a binary string instead of sending to the
 * browser.
 *
 * @package Civi\Api4\Action\SearchDisplay
 */
class GetDownload extends Run {

  use DownloadTrait;

  /**
   * @param \Civi\Api4\Result\SearchDisplayRunResult $result
   * @throws \CRM_Core_Exception
   */
  protected function processResult(\Civi\Api4\Result\SearchDisplayRunResult $result) {
    parent::processResult($result);
    $this->processDownload($result);
  }

}
