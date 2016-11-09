<?php
class Users extends Database {
  protected static $table           = 'users';
  protected static $primary_key     = 'user_id';
  protected static $timestamps      = true;
  protected static $timestamps_type = 'timestamp';

  public function __construct($fields_new = array()) {
    parent::__construct($fields_new);
    $this->user_salt = makeRandomString(16);
  }

  public function export($detailed = false) {
    $info = array(
      'id'    => (int)$this->user_id,
      'name'  => $this->user_name,
      'photo' => $this->user_photo,

    );

    if ($detailed) {
      $info['groups'] = explode(',', $this->user_groups);
    }

    return $info;
  }

  public function checkPassword($password) {
    return $this->user_password == $this->makePassword($password);
  }

  public function makePassword($password) {
    return md5($this->user_salt . $password . $this->user_salt);
  }

  public function setPassword($password) {
    $this->password = $this->makePassword($password);
  }

  public function hasAccess($access_level) {
    if (!count($groups = explode(',', $this->user_groups))) {
      return false;
    }

    return in_array($access_level, $groups);
  }

  public function getPhoto() {
    if ($this->user_photo) {
      return $this->user_photo;
    }

    return Config::get('app.user_photo_placeholder');
  }
}