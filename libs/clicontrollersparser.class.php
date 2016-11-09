<?php
class CliControllersParser {
  public static function parse() {
    $list = array();

    self::parseControllers($list);

    return $list;
  }

  protected static function parseControllers(&$list, $prefix = '/') {
    $path = ROOT_PATH . '/cli/' . $prefix;

    if (!is_dir($path)) {
      return;
    }

    if (!($dir = opendir($path))) {
      return;
    }

    while ($file = readdir($dir)) {
      if ($file == '.' || $file == '..') {
        continue;
      }

      if (is_dir($path . $file)) {
        self::parseControllers($list, $prefix . $file . '/');
        continue;
      }

      if (!preg_match('#^([a-z][a-zA-Z0-9]*)\.php$#', $file, $data)) {
        continue;
      }

      $name = strtolower($data[1]);

      $script = preg_replace(
        '#/'.$name.'/$#i',
        '/',
        strtolower($prefix)
      );

      $script = trim($script . $name, '/');

      if (isset($list[$script])) {
        trigger_error(sprintf(
          "Duplicate controller %s:\n- %s\n- %s",
          $script,
          $prefix . $file,
          $list[$script]['file']
        ));
        exit;
      }

      $list[$script] = self::processController($script, $prefix . $file);
    }
  }

  protected static function processController($script, $file) {
    $info = array(
      'file' => $file,
      'script' => $script,
      'class' => str_replace('/', '', $script) . '_clicontroller',
    );

    include_once(ROOT_PATH . '/cli/' . $info['file']);

    if (!class_exists($info['class'], false)) {
      trigger_error(sprintf(
        "File %s doesn't contains class %s",
        $info['file'],
        $info['class']
      ));
      exit;
    }

    $ref = new ReflectionClass($info['class']);
    if (!$ref->isSubclassOf('CliController')) {
      trigger_error(sprintf(
        "Class %s must extend class CliController",
        $info['class']
      ));
      exit;
    }

    $info['actions'] = self::getActionsList($info['class'], $ref);

    return $info;
  }

  protected static function getActionsList($class_name, $class_ref) {
    $actions = array();

    foreach ($class_ref->getMethods() as $method) {
      $name = $method->name;

      if (!preg_match('#^action_?([a-zA-Z0-9_]+)$#', $name, $data)) {
        continue;
      }

      $action = strtolower($data[1]);

      if (!$method->isPublic()) {
        trigger_error(sprintf(
          "Action %s:%s must be public",
          $method->class, $method->name
        ));
        exit;
      }

      if ($method->isStatic()) {
        trigger_error(sprintf(
          "Action %s:%s cannot be static",
          $method->class, $method->name
        ));
        exit;
      }

      if (isset($actions[$action])) {
        trigger_error(sprintf(
          "Duplicate methods\n- %s:%s\n- %s:%s",
          $method->class, $method->name,
          $method->class, $actions[$action]['method']
        ));
        exit;
      }

      $actions[$action] = array(
        'method' => $name,
        'action' => $action,
        'arguments' => self::getMethodParameters($method),
      );
    }

    return $actions;
  }

  protected static function getMethodParameters($method) {
    $params = array();

    foreach ($method->getParameters() as $param) {
      $value = NULL;

      if ($param->isDefaultValueAvailable()) {
        $value = $param->getDefaultValue();
      }

      $params[] = array(
        'name' => $param->name,
        'value' => $value,
      );
    }

    return $params;
  }
}
?>