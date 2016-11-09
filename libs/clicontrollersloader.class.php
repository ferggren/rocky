<?php
class CliControllersLoader {
  protected static $controllers_list = false;

  public static function loadController($controller_name, $controller_action = "default", $controller_args = array()) {
    if (!($controller = self::getController($controller_name))) {
      trigger_error(sprintf(
        "Invalid controller %s\nUse ./cli.php utils/help to show all available scripts",
        $controller_info['controller']
      ));

      exit;
    }

    $controller_action = strtolower($controller_action);

    if (!self::controllerActionExists($controller_name, $controller_action)) {
      trigger_error(sprintf(
        "Action %s:%s doesn't exists\nUse ./cli.php utils/help %s to show all available methods",
        $controller_name,
        $controller_action,
        $controller_name
      ));

      exit;
    }

    if (!class_exists($controller['class'], false)) {
      include(ROOT_PATH . '/cli/' . $controller['file']);

      if (!class_exists($controller['class'], false)) {
        trigger_error(sprintf(
          "Controller %s can't be found in %s",
          $controller['class'], $controller['file']
        ));
        exit;
      }
    }

    $class = new $controller['class'];
    $class->__callAction(
      $controller['actions'][$controller_action]['method'],
      $controller['actions'][$controller_action]['arguments'],
      $controller_args
    );
  }

  public static function controllerExists($controller_name) {
    return !!self::getController($controller_name);
  }

  public static function controllerActionExists($controller_name, $action_name) {
    if (!($controller = self::getController($controller_name))) {
      return false;
    }

    if (!isset($controller['actions'][strtolower($action_name)])) {
      return false;
    }

    return true;
  }

  public static function getController($controller) {
    $controller = strtolower($controller);
    $controllers = self::getControllers();

    if (!isset($controllers[$controller])) {
      return false;
    }

    return $controllers[$controller];
  }

  public static function getControllers() {
    if (is_array(self::$controllers_list)) {
      return self::$controllers_list;
    }

    if (is_array($controllers = self::loadFromFileCache())) {
      return self::$controllers_list = $controllers;
    }

    if (!is_array($controllers = CliControllersParser::parse())) {
      trigger_error('Unable to load controllers list');
      exit;
    }

    self::$controllers_list = $controllers;
    self::saveToFileCache(self::$controllers_list);

    return self::$controllers_list;
  }

  public static function rebuildCache() {
    if (!is_array($controllers = CliControllersParser::parse())) {
      trigger_error('Unable to load controllers list');
      exit;
    }

    self::saveToFileCache($controllers);
  }

  protected static function loadFromFileCache() {
    if (!Config::get('app.cache_scripts')) {
      return false;
    }

    if (!file_exists($tmp_path = ROOT_PATH . '/tmp/scripts.php')) {
      return false;
    }

    include $tmp_path;

    return isset($cache) ? $cache : false;
  }

  protected static function saveToFileCache($cache) {
    if (!Config::get('app.cache_scripts')) {
      return false;
    }

    $code = variable2code($cache);

    if (!($file = fopen(ROOT_PATH . '/tmp/scripts.php.tmp', 'wb'))) {
      return false;
    }

    fwrite($file, '<?php $cache = ' . $code . '; ?>');
    fclose($file);

    rename(
      ROOT_PATH . '/tmp/scripts.php.tmp',
      ROOT_PATH . '/tmp/scripts.php'
    );

    return true;
  }
}
?>