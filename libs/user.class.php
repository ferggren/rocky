<?php
class User {
    protected static $user = false;

    public static function __callStatic($name, $fuckoff) {
        if (preg_match('#^get_([0-9a-z_-]++)$#', $name, $data)) {
            if (!self::$user) {
                return false;
            }

            $key = $data[1];

            return self::$user->$key;
        }
    }

    public static function isAuthenticated() {
        self::init();

        return !!self::$user;
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

        $ip = ip2decimal(Session::getSessionIp());

        $changed = false;

        if (!$user->user_ip) {
            $user->user_ip = $ip;
            $changed = true;
        }

        if ($user->user_latest_ip != $ip) {
            $user->user_latest_ip = $ip;
            $changed = true;
        }

        $pulse = Config::get('app.users_pulse');

        if ($pulse !== false && (time() - $user->user_latest_activity) > $pulse) {
            $user->user_latest_activity = time();
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }

        self::$user = $user;
    }

    public static function logout() {
        self::loginAs(0);
    }

    public static function getPhoto() {
        self::init();

        if (!self::isAuthenticated()) {
            return Config::get('app.user_photo_placeholder');
        }

        if (!self::$user) {
            return Config::get('app.user_photo_placeholder');
        }

        if (!($photo = self::$user->getPhoto())) {
            return Config::get('app.user_photo_placeholder');
        }

        return $photo;
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