<?php
class ControllersParser {
    public static function parse() {
        $lits = array();

        self::loadControllersList($list);

        return $list;
    }

    protected static function loadControllersList(&$list, $prefix = '/') {
        $path = ROOT_PATH . '/controllers/' . $prefix;

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
                self::loadControllersList($list, $prefix . $file . '/');
                continue;
            }

            if (!preg_match('#^([a-z][a-z0-9]*)\.php$#', $file, $data)) {
                continue;
            }

            $name = $data[1];

            $uri = preg_replace('#/'.$name.'/#', '/', $prefix) . $name;
            $uri = trim($uri, '/');

            $list[$uri] = self::processController($uri, $prefix . $file);
        }
    }

    protected static function processController($name, $file) {
        $info = array(
            'file' => $file,
            'uri' => $name,
            'class' => str_replace('/', '', $name) . '_controller',
        );

        include(ROOT_PATH . '/controllers/' . $file);

        if (!class_exists($info['class'], false)) {
            trigger_error("file {$file} doesn't contains class {$info['class']}");
            exit;
        }

        $ref = new ReflectionClass($info['class']);
        if (!$ref->isSubclassOf('BaseController')) {
            trigger_error("class {$info['class']} must extends class BaseController");
            exit;
        }

        $info['actions'] = self::getActionsList($info['class'], $ref);

        if (!isset($info['actions']['index'])) {
            trigger_error("controller {$info['class']} must provide actionIndex");
            exit;
        }

        return $info;
    }

    protected static function getActionsList($class, $class_ref) {
        $actions = array();

        foreach ($class_ref->getMethods() as $method) {
            $name = $method->name;

            if (!preg_match('#^action_?([A-Za-z0-9_]+)$#', $name, $data)) {
                continue;
            }

            $action = strtolower($data[1]);

            if (!$method->isPublic()) {
                trigger_error("action {$method->class}:{$method->name} must be public!");
                exit;
            }

            if ($method->isStatic()) {
                trigger_error("action {$method->class}:{$method->name} cannot be static!");
                exit;
            }

            $actions[$action] = array(
                'method' => $name,
                'action' => $action,
                'argumets' => self::getMethodParameters($method),
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