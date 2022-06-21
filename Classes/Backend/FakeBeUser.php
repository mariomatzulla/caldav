<?php

namespace TYPO3\CMS\Caldav\Backend;

class FakeBeUser {

  private $pid;

  public function __construct($pid) {

    $this->pid = $pid;
  }

  public function getTSConfigVal($value) {

    return $this->pid;
  }

  public function writeLog() {

    return $this->pid;
  }
}