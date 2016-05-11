<?php
class App {
    public static $url = false;

    public static function run() {
        self::$url = isset($_SERVER['DOCUMENT_URI']) ? $_SERVER['DOCUMENT_URI'] : '/';

        if (!ControllersLoader::exists(self::$url)) {
            self::$url = Config::get('app.default_controller');

            if (!ControllersLoader::exists(self::$url)) {
                trigger_error('default controller not found');
                exit;
            }
        }

        self::checkUrlAccess();

        ControllersLoader::load(self::$url);
    }

    public static function shutdown() {
        if (Config::get('app.dump_perf_debug')) {
            self::dumpPerfLog();
        }
    }

    protected static function dumpPerfLog() {
        printf(
            "<!--%5.3fs;%5.2fMB-->",
            round(microtime(true) - TIME_START, 5),
            round(memory_get_usage(false) / (1024 * 1024), 2)
        );
    }

    protected static function checkUrlAccess() {
        $access_info = self::getUrlAccessLevel(self::$url);

        $has_access = true;
        $auth_redirect = false;

        if ($access_info['access_level']) {
            if (!User::isAuthenticated()) {
                $auth_redirect = true;
                $has_access = false;
            }
            else {
                if (!is_array($access_info['access_level'])) {
                    $access_info['access_level'] = array($access_info['access_level']);
                }

                foreach ($access_info['access_level'] as $level) {
                    if ($has_access = User::hasAccess($level)) {
                        continue;
                    }

                    break;
                }
            }
        }

        if ($access_info['auth'] && !User::isAuthenticated()) {
            $auth_redirect = true;
            $has_access = false;
        }

        if ($has_access) {
            return;
        }

        if ($access_info['type'] == 'ajax') {
            disableBrowserCaching();

            header('Content-type: application/json; charset=UTF-8');

            $ret = array(
                'status' => 'error',
                'error' => 'access denied',
            );

            echo json_encode($ret, JSON_UNESCAPED_UNICODE);

            if (!$auth_redirect && Config::get('app.log_unauthorized_requests')) {
                UsersLogger::logAction(
                    User::isAuthenticated() ? User::get_user_id() : 0,
                    'unauthorized_request',
                    self::$url
                );
            }

            exit;
        }

        if (!$auth_redirect) {
            if (self::$url == Config::get('app.default_controller')) {
                trigger_error('access denied');
                exit;
            }

            $url = self::$url;

            self::$url = Config::get('app.default_controller');
            self::checkUrlAccess();

            if (Config::get('app.log_unauthorized_requests')) {
                UsersLogger::logAction(
                    User::isAuthenticated() ? User::get_user_id() : 0,
                    'unauthorized_request',
                    $url
                );
            }

            return;
        }

        self::$url = Config::get('app.auth_controller');
    }

    protected static function getUrlAccessLevel($url) {
        $rules = Config::get('app.url_rules');

        $info = array(
            'auth' => false,
            'access_level' => false,
            'type' => 'default',
        );

        foreach ($rules as $route => $access) {
            if (!preg_match($route, $url)) {
                continue;
            }

            if (isset($access['type'])) {
                $info['type'] = $access['type'];
            }

            if (isset($access['auth'])) {
                $info['auth'] = $access['auth'];
            }

            if (isset($access['access_level'])) {
                $info['access_level'] = $access['access_level'];
            }
        }

        $controller_info = ControllersLoader::getAccessInfo($url);

        if (isset($controller_info['auth'])) {
            $info['auth'] = $controller_info['auth'];
        }

        if (isset($controller_info['access_level'])) {
            $info['access_level'] = $controller_info['access_level'];
        }

        if (isset($controller_info['type'])) {
            $info['type'] = $controller_info['type'];
        }

        return $info;
    }
}