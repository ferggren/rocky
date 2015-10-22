<?php
class Config {
    protected static $cache = array();

    public static function get($name) {
        $name = strtolower($name);

        if (!preg_match('#^([a-z0-9_-]++)\.([a-z0-9_-]++)$#', $name, $data)) {
            user_error("incorrect config variable $name");
            exit;
        }

        $prefix = $data[1];
        $name = $data[2];

        $config = self::loadConfig($prefix);

        if (!isset($config[$name])) {
            user_error("variable {$prefix}.{$name} not found!");
            exit;
        }

        return $config[$name];
    }

    protected static function loadConfig($prefix) {
        if (isset(self::$cache[$prefix])) {
            return self::$cache[$prefix];
        }

        $file = ROOT_PATH . "/config/" . $prefix . ".php";

        if (!file_exists($file)) {
            user_error("config {$prefix} not found");
            exit;
        }

        include $file;

        if (!isset($config)) {
            user_error("incorrect config file {$config}");
            exit;
        }

        return self::$cache[$prefix] = $config;
    }
}