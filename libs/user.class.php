<?php
/**
 * @file Provides user support
 * @name User
 * @author ferg <me@ferg.in>
 * @copyright 2016 ferg
 */

class User {
  /**
   *  User DB entry
   */
  protected static $user = false;

  /**
   *  Static methods like User::get_user_name() OR  User::get_user_id()
   */
  public static function __callStatic($name, $fuckoff) {
    self::__init();

    if (preg_match('#^get_([0-9a-z_-]++)$#', $name, $data)) {
      if (!self::$user) {
        return false;
      }

      $key = $data[1];

      return self::$user->$key;
    }
  }

  /**
   *  Get User object
   *
   *  @return {object} User object
   */
  public static function getUser() {
    return self::$user;
  }

  /**
   *  Checks if user is authenticated
   *
   *  @return {boolean} Auth check
   */
  public static function isAuthenticated() {
    self::__init();
    return !!self::$user;
  }

  /**
   *  Checks if user has access to specified group
   *
   *  @param {string} group Group name
   *  @return {boolean} Check status
   */
  public static function hasAccess($group) {
    self::__init();
    return self::$user ? self::$user->hasAccess($group) : false;
  }

  /**
   *  Set current user to specified user
   *
   *  @param {number} user_id User id
   *  @return {boolean} Access check status
   */
  public static function setUserTo($user_id) {
    self::__init();

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

  /**
   *  Return user photo
   *
   *  @return {string} Link to photo
   */
  public static function getPhoto() {
    self::__init();

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

  /**
   *  Init user
   */
  protected static function __init() {
    static $init = false;

    if ($init) {
      return;
    }

    $init = true;

    if (self::$user !== false) {
      return;
    }

    self::setUserTo(Session::getUserId());
  }
}