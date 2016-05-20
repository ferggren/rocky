<?php
class OAuth_Controller extends BaseController {
    public function actionIndex() {
        header('Location: /');
        exit;
    }

    public function actionInit($prefix) {
        $oauth = self::getOAuthObject($prefix);

        if (!$oauth) {
            self::OAuthFailure();
            exit;
        }

        $link = $oauth->getRedirectLink();

        if (!$link) {
            self::OAuthFailure();
            exit;
        }

        header('Location: ' . $link);
    }

    public function actionProcess($prefix) {
        $oauth = self::getOAuthObject($prefix);

        if (!$oauth) {
            self::OAuthFailure();
            exit;
        }

        if (!$oauth->processAuth()) {
            self::OAuthFailure();
            exit;
        }

        if (!($info = $oauth->getUserInfo())) {
            self::OAuthFailure();
            exit;
        }

        if (($user_id = $oauth->getLinkedUser()) > 0) {
            if (!User::isAuthenticated()) {
                Session::login($user_id);

                if (Config::get('app.log_users_auth')) {
                    UsersLogger::logAction(
                        $user_id,
                        'oauth',
                        $prefix.':'.$info['oauth_id']
                    );
                }

                return self::OAuthSuccess();
            }

            if (User::get_user_id() == $user_id) {
                return self::OAuthSuccess();
            }

            // WTF?
            Session::logout();
            Session::login($user_id);

            return $this->actionSuccess();
        }

        if (User::isAuthenticated()) {
            if (!$oauth->linkAccount(User::get_user_id())) {
                self::OAuthFailure();
                exit;
            }

            if (!User::get_user_photo() && ($user = Users::find(User::get_user_id()))) {
                $photo = $oauth->exportPhoto();
                $user->photo = $photo ? $photo : '';
                $user->save();
            }

            if (Config::get('app.log_users_auth')) {
                UsersLogger::logAction(
                    User::get_user_id(),
                    'oauth',
                    $prefix.':'.$info['oauth_id']
                );
            }

            self::OAuthSuccess();
            exit;
        }

        $photo = $oauth->exportPhoto();

        $user = new Users;

        $user->user_name = $info['name'];
        $user->user_login = md5(microtime(true));
        $user->user_password = '';
        $user->user_photo = $photo ? $photo : '';

        $user->save();

        $user->user_login = 'id' . $user->user_id;
        $user->save();

        if(!$oauth->linkAccount($user->user_id)) {
            self::OAuthFailure();
            exit;
        }

        Session::login($user->user_id);

        if (Config::get('app.log_users_auth')) {
            UsersLogger::logAction(
                $user->user_id,
                'oauth',
                $prefix.':'.$info['oauth_id']
            );
        }

        self::OAuthSuccess();
    }

    protected static function OAuthSuccess() {
        header('Location: /');
        exit;
    }

    protected static function OAuthFailure() {
        header('Location: /');
        exit;
    }

    protected static function getOAuthObject($prefix) {
        if (!$prefix) {
            return false;
        }

        if (!preg_match('#^[0-9a-zA-Z_-]++$#', $prefix)) {
            return false;
        }

        $config = Config::get('auth.oauth');
        if (!isset($config[$prefix])) {
            return false;
        }

        if (!isset($config[$prefix]['enabled']) || !$config[$prefix]['enabled']) {
            return false;
        }

        $class = $prefix . 'oauth';

        return new $class;
    }
}
?>