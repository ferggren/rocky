<?php
/**
 * @file Provides session support
 * @name Session
 * @author ferg <me@ferg.in>
 * @copyright 2016 ferg
 */

class Session {
  /**
   *  Session DB entry
   */
  protected static $session = false;

  /**
   *  Returns session ID
   *
   *  @return {string} Session id
   */
  public static function getSessionId() {
    self::__init();

    if (self::$session !== false) {
      return self::$session->session_id;
    }

    if (self::__createSession()) {
      return self::$session->session_id;
    }

    trigger_error('Session cannot be created');
    exit;
  }

  /**
   *  If user is logged in, returns his ID
   *
   *  @return {number} User id
   */
  public static function getUserId() {
    self::__init();

    if (!self::$session) {
      return 0;
    }

    return self::$session->user_id;
  }

  /**
   *  Returns user IP
   *
   *  @return {string} User ip
   */
  public static function getSessionIp() {
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

      if (in_array($ip, array('127.0.0.1', '0.0.0.0'))) {
        continue;
      }

      return $ip = $data[0];
    }

    return $ip = '127.0.0.1';
  }

  /**
   *  Login as specified user
   *
   *  @param {number} user_id User id
   */
  public static function login($user_id) {
    self::__init();

    if (!self::$session) {
      if (!self::__createSession()) {
        trigger_error('Session cannot be created');
        exit;
      }
    }

    $user_id = (int)$user_id;
    self::$session->user_id = $user_id;
    self::$session->save();

    User::setUserTo($user_id);

    return true;
  }

  /**
   *  User logout
   */
  public static function logout() {
    self::__init();

    User::setUserTo(0);

    if (!self::$session) {
      return true;
    }

    self::$session->user_id = 0;
    self::$session->save();

    return true;
  }

  /**
   *  Initializes new session
   */
  protected static function __init() {
    static $init = false;

    if ($init) {
      return true;
    }

    $init = true;

    self::__loadSession();
  }

  /**
   *  Creates new session
   */
  protected static function __createSession() {
    $session_id = false;

    while (true) {
      if (!($session_id = makeRandomString(32))) {
        return false;
      }

      if (!Sessions::find($session_id)) {
        break;
      }
    }

    $sign = self::__makeSign($session_id);

    setcookie(
      '__session_id',
      $session_id . $sign,
      time() + 86400 * 1000,
      '/',
      Config::get('app.cookie_domain')
    );

    $session_ip = ip2decimal(self::getSessionIp());

    $session = new Sessions;
    $session->session_id = $session_id;
    $session->user_id = 0;
    $session->session_ip = $session_ip;
    $session->session_latest_ip = $session_ip;
    $session->save();

    self::$session = $session;

    return true;
  }

  /**
   *  Tries to load an exists sesssion
   */
  protected static function __loadSession() {
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

    if ($sign != self::__makeSign($session_id)) {
      return false;
    }

    if (!($session = Sessions::find($session_id))) {
      return false;
    }

    $session_ip = ip2decimal(self::getSessionIp());

    $changed = false;
    
    if ($session->session_latest_ip != $session_ip) {
      $session->session_latest_ip = $session_ip;
      $changed = true;
    }

    $pulse = Config::get('app.session_pulse');

    if ($pulse !== false && (time() - $session->session_latest_activity) > $pulse) {
      $changed = true;
      $session->session_latest_activity = time();
    }

    if ($changed) {
      $session->save();
    }

    self::$session = $session;

    return true;
  }

  /**
   *  Creates session sign
   */
  protected static function __makeSign($session_id) {
    $salt = Config::get('app.session_salt');

    return md5($salt . $session_id . $salt);
  }
}
?>