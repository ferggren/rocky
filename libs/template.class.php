<?php
class Template {
  protected $view;
  protected $args = array();

  public function __construct($view = false) {
    $this->view = $view;
  }

  public function setView($view) {
    $this->view = $view;

    return $this;
  }

  public function assign($name, $value) {
    $this->args[$name] = $value;

    return $this;
  }

  public function export($list) {
    if (!is_array($list)) {
      return $this;
    }

    foreach ($list as $key => $value) {
      $this->args[$key] = $value;
    }

    return $this;
  }

  public function toString() {
    if (!$this->view) {
      trigger_error('view is not defined');
      exit;
    }

    if (!TemplatesLoader::exists($this->view)) {
      trigger_error('view ' . $this->view . ' is not found!');
      exit;
    }

    ob_start();
    TemplatesLoader::printView($this->view, $this->args);
    $buffer = ob_get_clean();

    return $buffer;
  }

  public function printView() {
    if (!$this->view) {
      trigger_error('view is not defined');
      exit;
    }

    if (!TemplatesLoader::exists($this->view)) {
      trigger_error('view ' . $this->view . ' is not found!');
      exit;
    }

    TemplatesLoader::printView($this->view, $this->args);
  }
}
?>