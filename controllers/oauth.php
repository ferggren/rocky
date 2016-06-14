<?php
class OAuth_Controller extends BaseController {
    /**
     *  No default action
     */
    public function actionIndex() {
        header('Location: /');
        exit;
    }

    /**
     *  Initialize oauth
     *
     *  @param {string} prefix Oauth type
     */
    public function actionInit($prefix) {
        $oauth = self::__getOAuthObject($prefix);

        if (!$oauth) {
            self::__OAuthFailure();
            exit;
        }

        $link = $oauth->getRedirectLink();

        if (!$link) {
            self::__OAuthFailure();
            exit;
        }

        // redirect link?

        header('Location: ' . $link);
    }

    /**
     *  Process oauth
     *
     *  @param {string} prefix Oauth type
     */
    public function actionProcess($prefix) {
        $oauth = self::__getOAuthObject($prefix);

        if (!$oauth) {
            self::__OAuthFailure();
            exit;
        }

        if (!$oauth->processAuth()) {
            self::__OAuthFailure();
            exit;
        }

        if (!($info = $oauth->getUserInfo())) {
            self::__OAuthFailure();
            exit;
        }

        // If OAuth is already linked to some account
        if (($user_id = $oauth->getLinkedUser()) > 0) {
            self::__logOAuth($user_id, $prefix, $info['oauth_id']);

            if (!User::isAuthenticated()) {
                Session::login($user_id);
                return self::__OAuthSuccess();
            }

            if (User::get_user_id() == $user_id) {
                return self::__OAuthSuccess();
            }

            Session::logout();
            Session::login($user_id);

            return $this->actionSuccess();
        }

        // Account is not linked & user is authenticated
        if (User::isAuthenticated()) {
            if (!$oauth->linkAccount(User::get_user_id())) {
                self::__OAuthFailure();
                exit;
            }

            if ($user = Users::find(User::get_user_id())) {
                $changed = false;

                if (!$user->user_name) {
                    $user->user_name = $info['name'];
                    $changed = true;
                }

                if (!$user->user_photo && ($photo = $oauth->exportPhoto())) {
                    $user->user_photo = $photo;
                    $changed = true;
                }

                if ($changed) {
                    $user->save();
                }
            }
            
            self::__logOAuth(User::get_user_id(), $prefix, $info['oauth_id']);
            self::__OAuthSuccess();

            exit;
        }

        // Account is not linked & user is not authenticated
        $photo = $oauth->exportPhoto();

        $user = new Users;

        $user->user_name = $info['name'];
        $user->user_login = md5(microtime(true));
        $user->user_photo = $photo ? $photo : '';

        $user->save();

        $user->user_login = 'id' . $user->user_id;
        $user->save();

        if(!$oauth->linkAccount($user->user_id)) {
            $user->delete();
            self::__OAuthFailure();
            exit;
        }

        Session::login($user->user_id);

        self::__logOAuth($user->user_id, $prefix, $info['oauth_id']);
        self::__OAuthSuccess();
    }

    /**
     *  Log oauth attempt
     *
     *  @param {number} user_id User id
     *  @param {string} prefix Oauth prefix
     */
    protected static function __logOAuth($user_id, $prefix, $oauth_id) {
        if (!Config::get('app.log_users_auth')) {
            return false;
        }

        UsersLogger::logAction(
            $user_id,
            'oauth',
            $prefix.':'.$oauth_id
        );
    }

    /**
     *  Oauth success
     */
    protected static function __OAuthSuccess() {
        // redirect link?
        header('Location: /');
        exit;
    }

    /**
     *  Oauth error
     */
    protected static function __OAuthFailure() {
        header('Location: /');
        exit;
    }

    /**
     *  Returns oauth object related to prefix
     *
     *  @param {string} prefix Oauth type
     *  @return {object} Oauth object
     */
    protected static function __getOAuthObject($prefix) {
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