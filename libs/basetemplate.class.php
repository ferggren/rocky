<?php
class BaseTemplate {
  protected $args = false;

  public function __construct($args = array()) {
    $this->args = $args;
  }
}
?>