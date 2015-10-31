<?php
class CliControllersLoader {
    protected static $scripts_list = false;

    public static function getScriptsList() {
        if (is_array(self::$scripts_list)) {
            return self::$scripts_list;
        }

        self::$scripts_list = array();

        $path = ROOT_PATH . '/cli/';

        if (!is_dir($path)) {
            return false;
        }

        if (!($dir = opendir($path))) {
            return false;
        }

        while ($file = readdir($dir)) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            if (is_dir($path.$file)) {
                continue;
            }

            if (!preg_match('#^([a-z][a-zA-Z0-9]*)\.php$#', $file, $data)) {
                continue;
            }

            $script = strtolower($data[1]);

            if (isset(self::$scripts_list[$script])) {
                continue;
            }

            if (!is_array($methods = self::processScript($file, $script))) {
                continue;
            }

            self::$scripts_list[$script] = $methods;
        }

        return self::$scripts_list;
    }

    protected static function processScript($file, $script_name) {
        include(ROOT_PATH . '/cli/' . $file);

        $class = $script_name . '_cli';

        if (!class_exists($class, false)) {
            return false;
        }

        $ref = new ReflectionClass($class);
        return self::getScriptMethods($ref);
    }

    protected static function getScriptMethods($class_ref) {
        $methods = array();

        foreach ($class_ref->getMethods() as $method) {
            $name = $method->name;

            if (!$method->isPublic()) {
                continue;
            }

            if (!$method->isStatic()) {
                continue;
            }

            $methods[$name] = self::getMethodArguments($method);
        }

        return $methods;
    }

    protected static function getMethodArguments($method) {
        $args = array();

        foreach ($method->getParameters() as $arg) {
            $value = NULL;

            if ($arg->isDefaultValueAvailable()) {
                $value = $arg->getDefaultValue();
            }

            $args[] = array(
                'name' => $arg->name,
                'value' => $value,
            );
        }

        return $args;
    }
}
?>