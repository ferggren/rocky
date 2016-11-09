<?php
class Cli {
  public static function run() {
    global $argv;

    if (!($controller = self::parseCliInput())) {
      self::loadDefaultController();
      exit;
    }

    if (!(CliControllersLoader::controllerExists($controller['controller']))) {
      trigger_error(sprintf(
        "Controller %s is not found\nUse ./cli.php utils/help to show all controllers",
        $controller['controller']
      ));
      exit;
    }

    CliControllersLoader::loadController(
      $controller['controller'],
      $controller['action'],
      $controller['args']
    );
  }

  protected static function loadDefaultController() {
    CliControllersLoader::loadController('utils/help');
  }

  protected static function parseCliInput() {
    global $argv;

    $controller_info = array(
      'controller' => false,
      'action' => 'default',
      'args' => array(),
    );

    array_shift($argv);

    if (count($argv) < 1) {
      return false;
    }

    $controller = strtolower(array_shift($argv));

    if (!preg_match('#^[a-z][a-z0-9_/]*+$#', $controller)) {
      return false;
    }

    $controller_info['controller'] = $controller;

    // controller is fine, time to parse action
    if (count($argv) < 1) {
      return $controller_info;
    }

    if (preg_match('#^[a-zA-Z][a-zA-Z0-9_]*+$#', $argv[0])) {
      $action = strtolower($argv[0]);

      if (CliControllersLoader::controllerActionExists($controller_info['controller'], $action)) {
        $controller_info['action'] = $action;
        array_shift($argv);
      }
    }

    // and now time to parse arguments
    $argument_name = false;
    $arguments_counter = 0;

    for ($i = 0, $end = count($argv); $i < $end; ++$i) {
      $arg = $argv[$i];

      if (preg_match('#^--([a-zA-Z][0-9a-zA-Z_]*+)$#', $arg, $data)) {
        if ($argument_name) {
          $controller_info['args'][$argument_name] = true;
        }

        $argument_name = $data[1];
        continue;
      }

      if ($argument_name) {
        $controller_info['args'][$argument_name] = $arg;
        $argument_name = false;
        continue;
      }

      $controller_info['args'][$arguments_counter++] = $arg;
    }

    if ($argument_name) {
      $controller_info['args'][$argument_name] = true;
    }

    return $controller_info;
  }
}
?>