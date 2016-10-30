<?php
/**
 *  Show info about available controllers
 */
class UtilsHelp_CliController extends CliController {
  public function action_default($script, $method) {
    if ($script && $method) {
      return self::_show_script_method($script, $method);
    }

    if ($script) {
      return self::_show_script_methods($script);
    }

    return self::_show_scripts();
  }

  /**
   *  Show script method info
   *
   *  @param {string} script Script name
   *  @param {string} method Script method name
   */
  protected static function _show_script_method($script, $method) {
    $script = strtolower($script);
    $method = strtolower($method);

    if (!is_array($scripts = CliControllersLoader::getControllers())) {
      trigger_error("Unable to load scripts list");
      exit;
    }

    if (!isset($scripts[$script])) {
      printf(
        "Script %s doesn't exists",
        $script
      );
      exit;
    }

    $script = $scripts[$script];

    if (!isset($script['actions'][$method])) {
      printf(
        "Method %s:%s doesn't exists",
        $script['script'], $method
      );
      exit;
    }

    $method = $script['actions'][$method];

    printf(
      "%s:%s argument(s) [%d]:",
      $script['script'],
      $method['action'],
      count($method['arguments'])
    );

    foreach ($method['arguments'] as $argument) {
      printf("\n --%s", $argument['name']);

      if (!is_null($argument['value'])) {
        printf(" [default = %s]", $argument['value']);
      }
    }
  }

  /**
   *  Show script methods list
   *
   *  @param {string} script Script name
   */
  protected static function _show_script_methods($script) {
    $script = strtolower($script);

    if (!is_array($scripts = CliControllersLoader::getControllers())) {
      trigger_error("Unable to load scripts list");
      exit;
    }

    if (!isset($scripts[$script])) {
      printf(
        "Script %s doesn't exists",
        $script
      );
      exit;
    }

    $script = $scripts[$script];

    ksort($script['actions']);

    printf("%s medhod(s) [%d]:", $script['script'], count($script['actions']));

    foreach ($script['actions'] as $action) {
      printf("\n %s", $action['action']);
    }
  }

  /**
   *  Show scripts list
   *
   *  @param {string} script Script name
   */
  protected static function _show_scripts() {
    if (!is_array($scripts = CliControllersLoader::getControllers())) {
      trigger_error("Unable to load scripts list");
      exit;
    }

    ksort($scripts);

    printf("Scripts list [%d]:", count($scripts));

    foreach ($scripts as $script) {
      printf("\n %s", $script['script']);
    }
  }
}
?>