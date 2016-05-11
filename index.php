<?php
date_default_timezone_set('UTC');
define('ROOT_PATH', dirname(__FILE__) . '/');
define('TIME_START', microtime(true));
chdir(ROOT_PATH);

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

if (version_compare(PHP_VERSION, '5.6.0', '<=')) {
    ini_set('mbstring.internal_encoding', 'UTF-8');
    ini_set('mbstring.func_overload', 7);
    ini_set('iconv.internal_encoding', 'UTF-8');
}

ini_set('default_charset', 'UTF-8');

function autoload($class_name) {
    $class_name = strtolower($class_name);

    $libs_path = ROOT_PATH . '/libs/' . $class_name . '.class.php';
    $models_path = ROOT_PATH . '/models/' . $class_name . '.php';
    
    if (file_exists($libs_path)) {
        include $libs_path;
        return;
    }

    if (file_exists($models_path)) {
        include $models_path;
        return;
    }

    trigger_error('class ' . $class_name . ' not found');
    exit;
}

include(ROOT_PATH . '/system/common.php');

spl_autoload_register('autoload');
set_error_handler(array('ErrorHandler', 'handleError'), E_ALL | E_STRICT);
register_shutdown_function(array('App', 'shutdown'));

App::run();
?>