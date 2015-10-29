<?php
class User {
    protected static $user = false;

    public static function isAuthenticated() {
        self::init();

        return !!self::$user;
    }

    public static function __callStatic($name, $fuckoff) {
        if (preg_match('#^get_([0-9a-z_-]++)$#', $name, $data)) {
            if (!self::$user) {
                return false;
            }

            $key = $data[1];

            return self::$user->$key;
        }
    }

    public static function hasAccess($access_level) {
        self::init();

        if (!self::$user) {
            return false;
        }

        return self::$user->hasAccess($access_level);
    }

    public static function loginAs($user_id) {
        self::$user = false;

        if (!$user_id) {
            return;
        }

        if (!($user = Users::find($user_id))) {
            return;
        }

        self::$user = $user;
    }

    public static function logout() {
        self::loginAs(0);
    }

    protected static function init() {
        static $init = false;

        if ($init) {
            return;
        }

        $init = true;

        self::loginAs(Session::getUserId());
    }
}