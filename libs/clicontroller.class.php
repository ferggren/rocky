<?php
class CliController {
  public function __construct() {

  }

  public function __callAction($action_method, $action_args, $user_args) {
    $args = array();
    $args_pos = 0;

    for ($end = count($action_args); $args_pos < $end; ++$args_pos) {
      $arg = $action_args[$args_pos];

      // default value for argument
      $value = $arg['value'];

      if (isset($user_args[$args_pos])) {
        $value = $user_args[$args_pos];
      }

      if (isset($user_args[$arg['name']])) {
        $value = $user_args[$arg['name']];
      }

      $args[] = $value;
    }

    for ($end = min(20, count($user_args)); $args_pos < $end; ++$args_pos) {
      if (!isset($user_args[$args_pos])) {
        break;
      }

      $args[] = $user_args[$args_pos];
    }

    call_user_func_array(array($this, $action_method), $args);
  }
}
?>