<?php
class ControllersLoader {
    protected static $cache = false;
    protected static $stack = array();

    public static function load($url) {
        if (!($info = self::getController($url))) {
            return false;
        }

        if (!class_exists($info['controller']['class'], false)) {
            include(ROOT_PAHT . '/controllers/' . $info['controller']['file']);

            if (!class_exists($info['controller']['class'], false)) {
                trigger_error('Incorrect controller ' . $info['controller']['controller']);
                exit;
            }
        }

        exit;
    }

    public static function exists($url) {
        return !!self::getController($url);
    }

    protected static function getController($url) {
        $list = self::getControllers();

        print_r($list);

        exit;
    }

    protected static function getControllers() {
        if (is_array(self::$cache)) {
            return self::$cache;
        }

        if (is_array(self::$cache = self::loadFromFileCache())) {
            return self::$cache;
        }

        if (!is_array(self::$cache = ControllersParser::parse())) {
            trigger_error('Unable to load controllers list');
            exit;
        }

        self::saveToFileCache(self::$cache);

        return self::$cache;
    }

    protected static function loadFromFileCache() {
        return false;
    }

    protected static function saveToFileCache($cache) {
        return false;
    }
}
?>