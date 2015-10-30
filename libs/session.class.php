<?php
class Session {
    protected static $session = false;

    public static function init() {
        static $init = false;

        if ($init) {
            return true;
        }

        $init = true;

        self::loadSession();
    }

    public static function getSessionId() {
        self::init();

        if (self::$session !== false) {
            return self::$session->session_id;
        }

        if (self::createSession()) {
            return self::$session->session_id;
        }

        trigger_error('error while creating new session');
        exit;
    }

    public static function getUserId() {
        self::init();

        if (!self::$session) {
            return 0;
        }

        return self::$session->user_id;
    }

    public static function getUserIp() {
        static $ip = false;

        if ($ip !== false) {
            return $ip;
        }

        $headers = array(
            'REMOTE_ADDR',
            'HTTP_X_COMING_FROM',
            'HTTP_VIA',
            'HTTP_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_FORWARDED_FOR',
        );

        foreach($headers as $header) {
            $ip = false;
            
            if(isset($_SERVER) && isset($_SERVER[$header])) {
                $ip = $_SERVER[$header];
            }
            
            else if(isset($_ENV) && isset($_ENV[$header])) {
                $ip = $_ENV[$header];
            }

            if($ip == false) {
                continue;
            }
            
            if(!preg_match('#(?<!\d)\d{1,3}(?:\.\d{1,3}){3}(?!\d)#', $ip, $data)) {
                continue;
            }

            return $ip = $data[0];
        }

        return $ip = false;
    }

    public static function login($user_id) {
        self::init();

        if (!self::$session) {
            if (!self::createSession()) {
                trigger_error('error while creating new session');
                exit;
            }
        }

        $user_id = (int)$user_id;
        self::$session->user_id = $user_id;
        self::$session->save();

        User::loginAs($user_id);

        return true;
    }

    public static function logout() {
        self::init();

        User::logout();

        if (!self::$session) {
            return true;
        }

        self::$session->user_id = 0;
        self::$session->save();

        return true;
    }

    protected static function createSession() {
        $salt = Config::get('app.session_salt');
        $session_id = false;

        while (true) {
            $str = implode(
                ':',
                array(
                    $salt,
                    microtime(),
                    self::getUserIp(),
                    $salt
                )
            );

            $session_id = md5($str);

            if (!Sessions::find($session_id)) {
                break;
            }
        }

        $sign = self::makeSign($session_id);

        setcookie(
            '__session_id',
            $session_id . $sign,
            time() + 86400 * 1000,
            '/',
            Config::get('app.cookie_domain')
        );

        $user_ip = ip2decimal(self::getUserIp());

        $session = new Sessions;
        $session->session_id = $session_id;
        $session->user_id = 0;
        $session->user_ip = $user_ip;
        $session->user_latest_ip = $user_ip;
        $session->save();

        self::$session = $session;

        return true;
    }

    protected static function loadSession() {
        if (!isset($_COOKIE['__session_id'])) {
            return false;
        }

        $session_id = $_COOKIE['__session_id'];
        if (!is_string($session_id)) {
            return false;
        }

        if (!preg_match('#^[a-zA-Z0-9_-]{64}$#', $session_id)) {
            return false;
        }

        $sign = substr($session_id, 32, 32);
        $session_id = substr($session_id, 0, 32);

        if ($sign != self::makeSign($session_id)) {
            return false;
        }

        if (!($session = Sessions::find($session_id))) {
            return false;
        }

        $user_ip = ip2decimal(self::getUserIp());
        
        if ($session->user_latest_ip != $user_ip) {
            $session->user_latest_ip = $user_ip;
            $session->save();
        }

        $pulse = Config::get('app.session_pulse');

        if ($pulse !== false && (time() - $session->updated_at) > $pulse) {
            $session->save();
        }

        self::$session = $session;

        return true;
    }

    protected static function makeSign($session_id) {
        $salt = Config::get('app.session_salt');

        return md5($salt . $session_id . $salt);
    }
}
?>