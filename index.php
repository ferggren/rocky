<?php
// some default stuff
date_default_timezone_set('UTC');
define('ROOT_PATH', dirname(__FILE__) . '/');
chdir(ROOT_PATH);

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

if (version_compare(PHP_VERSION, '5.6.0', '<=')) {
    ini_set('mbstring.internal_encoding', 'UTF-8');
    ini_set('mbstring.func_overload', 7);
}

ini_set('iconv.internal_encoding', 'UTF-8');
ini_set('default_charset', 'UTF-8');

// ?? use other way to disable caching
header('Content-Type: text/html; charset=UTF-8');
header('Expires: Thu, 19 Feb 1998 13:24:18 GMT');
header('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header('Cache-Control: no-cache, must-revelidate');
header('Cache-Control: post-check=0,pre-check=0');
header('Cache-Control: max-age=0');
header('Pragma: no-cache');

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

    user_error('class ' . $class_name . ' not found');
    exit;
}

spl_autoload_register('autoload');
set_error_handler(array('ErrorHandler', 'handleError'), E_ALL | E_STRICT);