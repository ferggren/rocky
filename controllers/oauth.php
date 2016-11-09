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

    self::__saveReferer();

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
    if (!($redirect = self::__getReferer())) {
      $redirect = '/';
    }

    self::__clearReferer();

    header('Location: ' . $redirect);
    exit;
  }

  /**
   *  Oauth error
   */
  protected static function __OAuthFailure() {
    // whoops

    self::__clearReferer();

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

  /**
   *  Saves referer for further needs
   */
  protected static function __saveReferer() {
    if (!isset($_SERVER['HTTP_REFERER']) || !$_SERVER['HTTP_REFERER']) {
      return false;
    }

    if (!($domain = self::__getDomain())) {
      return false;
    }

    $referer = $_SERVER['HTTP_REFERER'];

    $regexp  = 'https?://';
    $regexp .= '([0-9a-zA-Z_-]+\.)*';
    $regexp .= preg_quote($domain, '#');
    $regexp .= '(?::\d{1,5})?';

    if (!preg_match('#^' . $regexp . '#', $referer)) {
      return false;
    }

    $referer = base64_encode($referer);

    setcookie(
      '__oauth_referer',
      $referer,
      time() + 86400 * 3,
      '/',
      Config::get('app.cookie_domain')
    );

    return true;
  }

  /**
   *  Clear referer
   */
  protected static function __clearReferer() {
    setcookie(
      '__oauth_referer',
      '',
      time() - 86400 * 3,
      '/',
      Config::get('app.cookie_domain')
    );
  }

  /**
   *  Returns referer link
   *
   *  @return {string} Referer
   */
  protected static function __getReferer() {
    if (!isset($_COOKIE['__oauth_referer'])) {
      return '/';
    }

    $referer = $_COOKIE['__oauth_referer'];

    if (!($referer = base64_decode($referer))) {
      return '/';
    }

    $referer = str_replace(
      array("\r", "\t", "\n", " ", '"', '"'),
      array('', '', '', '', '', ''),
      $referer
    );

    if (!$referer) {
      return '/';
    }

    return $referer;
  }

  /**
   *  Returns current domain
   *
   *  @return {string} Current domain
   */
  protected static function __getDomain() {
    $domain = false;

    if (isset($_SERVER['SERVER_NAME'])) {
      $domain = $_SERVER['SERVER_NAME'];
    }

    else if (isset($_SERVER['HTTP_HOST'])) {
      $domain = $_SERVER['HTTP_HOST'];
    }

    if (!$domain) {
      return false;
    }

    $domain = strtolower($domain);

    if (!preg_match('#^((?:[0-9a-zA-Z_]++\.)++[0-9a-z]{1,5})#', $domain, $data)) {
      return false;
    }

    return $data[1];
  }
}
?>