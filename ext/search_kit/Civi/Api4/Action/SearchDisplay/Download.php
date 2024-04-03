<?php

namespace Civi\Api4\Action\SearchDisplay;

/**
 * Get the results of a SearchDisplay as a spreadsheet.
 *
 * @package Civi\Api4\Action\SearchDisplay
 */
class Download extends AbstractRunAction {

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
