<?php
class BaseController {
  public function __construct() {

    static $headers = false;
    if (!$headers) {
      $headers = true;
      static::sendDefaultHeaders();
    }
  }

  protected static function sendDefaultHeaders() {
    disableBrowserCaching();
    header('Content-Type: text/html; charset=UTF-8');
  }

  /**
   * Calling a controller with specific action
   *
   * @param (action) which action will be called
   * @param (action_args) argruments for an action
   * @param (user_args) arguments that will be passed to actions
   * @param (smart_args_enabled) search arguments values in GET and POST
   */
  public function __callAction($action, $action_args, $user_args, $smart_args_enabled = false) {
    $args = array();
    $args_pos = 0;

    for ($end = count($action_args); $args_pos < $end; ++$args_pos) {
      $arg = $action_args[$args_pos];

      // default value for argument
      $value = $arg['value'];

      if ($smart_args_enabled) {
        if (isset($_GET[$arg['name']])) {
          $value = $_GET[$arg['name']];
        }

        if (isset($_POST[$arg['name']])) {
          $value = $_POST[$arg['name']];
        }
      }

      if (isset($user_args[$args_pos])) {
        $value = $user_args[$args_pos];
      }

      $args[] = $value;
    }

    for ($end = min(20, count($user_args)); $args_pos < $end; ++$args_pos) {
      $args[] = $user_args[$args_pos];
    }

    call_user_func_array(array($this, $action), $args);
  }
}
?>