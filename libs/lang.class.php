<?php
class Lang {
    protected static $lang = false;
    protected static $cache = array();

    /**
    *   Returns processed lang_string
    *   @param (lang_string) lang_string in format prefix.string
    *   @param (variables) list (key => value) of variables to be replaced in lang_string
    */
    public static function get($lang_string, $variables = array()) {
        if (!is_array($variables)) {
            $variables = array();
        }

        if (!($pos = strpos($lang_string, '.', 0))) {
            return $lang_string;
        }

        $prefix = strtolower(substr($lang_string, 0, $pos));
        $lang_string = substr($lang_string, $pos + 1);

        if (!is_array($strings = self::getStrings($prefix))) {
            return $lang_string;
        }

        if (!isset($strings[$lang_string])) {
            return $lang_string;
        }

        $string = $strings[$lang_string];

        if (!count($variables)) {
            return $string;
        }

        return self::stringProcess($string, $variables);
    }

    /**
    *   Change lang for specified
    *   @param (lang) new lang
    */
    public static function setLang($lang) {
        $lang = strtolower((string)$lang);

        if (!preg_match('#^[a-z0-9-]{1,6}$#', $lang)) {
            return false;
        }

        if (!in_array($lang, self::getLangs())) {
            return false;
        }

        if (!is_dir(ROOT_PATH . '/system/lang/' . $lang)) {
            return false;
        }

        self::$lang = $lang;

        return true;
    }

    /**
    *   Returns current lang (or find what current lang is, set and return it)
    */
    public static function getLang() {
        if (self::$lang !== false) {
            return self::$lang;
        }

        if (Config::get('lang.check_data') && ($lang = self::check_data())) {
            if (self::setLang($lang)) {
                return $lang;
            }
        }

        if (Config::get('lang.check_cookie') && ($lang = self::check_cookie())) {
            if (self::setLang($lang)) {
                return $lang;
            }
        }

        if (Config::get('lang.check_headers') && ($lang = self::check_headers())) {
            if (self::setLang($lang)) {
                return $lang;
            }
        }

        if (self::setLang($lang = Config::get('lang.default'))) {
            return $lang;
        }

        return false;
    }

    /**
    *   Returns list of all available langs
    */
    public static function getLangs() {
        return Config::get('lang.list');
    }

    /**
    *   Pluraluze
    */
    public static function pluralize($amount, $one, $many) {
        return $amount == 1 ? $one : $many;
    }

    /**
    *   Russian pluraluze
    */
    public static function ruPluralize($amount, $first, $second, $third) {
        $amount %= 100;

        if ($amount >= 10 && $amount <= 20) {
            return $third;
        }

        $amount %= 10;

        if ($amount == 1) {
            return $first;
        }

        if ($amount > 1 && $amount < 5) {
            return $second;
        }

        return $third;
    }

    /**
    *   Search lang in post & get data
    */
    protected static function check_data() {
        $list = self::getLangs();
        $lang = false;

        if (isset($_GET['USER_LANG'])) {
            $lang = $_GET['USER_LANG'];
        }

        if (isset($_POST['USER_LANG'])) {
            $lang = $_POST['USER_LANG'];
        }

        if (!$lang) {
            return false;
        }

        $lang = strtolower($lang);

        if (!preg_match('#^[0-9a-z-]{1,6}$#', $lang)) {
            return false;
        }

        if (!in_array($lang, $list)) {
            return false;
        }

        return $lang;
    }

    /**
    *   Search for lang in cookie
    */
    protected static function check_cookie() {
        $list = self::getLangs();

        if (!isset($_COOKIE['USER_LANG'])) {
            return false;
        }

        $lang = strtolower($_COOKIE['USER_LANG']);

        if (!preg_match('#^[0-9a-z-]{1,6}$#', $lang)) {
            return false;
        }

        if (!in_array($lang, $list)) {
            return false;
        }

        return $lang;
    }

    /**
    *   Search for lang in http headers
    */
    protected static function check_headers() {
        $list = self::getLangs();

        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return false;
        }

        $header = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $header = str_replace(';', ',', $header);

        foreach (explode(',', $header) as $part) {
            if (!in_array($part, $list)) {
                continue;
            }

            return $part;
        }

        return false;
    }

    /**
    *   Load all strings for given prefix
    *   @param (prefix) strings file name
    */
    public static function getStrings($prefix) {
        if (!($lang = self::getLang())) {
            return false;
        }

        if (!preg_match('#^[0-9a-z_-]{1,16}$#', $prefix)) {
            return false;
        }

        $key = $lang . ':' . $prefix;

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $file = ROOT_PATH . '/system/lang/' . $lang . '/' . $prefix . '.php';

        if (!file_exists($file)) {
            return false;
        }

        include($file);

        if (!isset($strings) || !is_array($strings)) {
            return false;
        }

        return self::$cache[$key] = $strings;
    }

    /**
    *   Make some replacements to a string
    *   @param (string) lang string
    *   @param (replacements) array (key => value) that will be replaced
    */
    protected static function stringProcess($string, $replacements) {
        foreach ($replacements as $key => $value) {
            $string = str_replace('%'.$key.'%', $value, $string);
        }

        if (strpos($string, 'rupluralize') !== false) {
            $matches = preg_match_all(
                "#rupluralize\((\d++(?:\.\d++)?)\s++['\"]([^'\"]++)['\"]\s++['\"]([^'\"]++)['\"]\s++['\"]([^'\"]++)['\"]\)#",
                $string,
                $data,
                PREG_SET_ORDER
            );

            if ($matches) {
                foreach ($data as $match) {
                    $string = str_replace(
                        $match[0],
                        self::ruPluralize($match[1], $match[2], $match[3], $match[4]),
                        $string
                    );
                }
            }
        }

        if (strpos($string, 'pluralize') !== false) {
            $matches = preg_match_all(
                "#pluralize\((\d++(?:\.\d++)?)\s++['\"]([^'\"]++)['\"]\s++['\"]([^'\"]++)['\"]\)#",
                $string,
                $data,
                PREG_SET_ORDER
            );

            if ($matches) {
                foreach ($data as $match) {
                    $string = str_replace(
                        $match[0],
                        self::pluralize($match[1], $match[2], $match[3]),
                        $string
                    );
                }
            }
        }

        return $string;
    }
}
?>