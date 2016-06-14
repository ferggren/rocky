<?php
class Users extends Database {
    protected static $table = 'users';
    protected static $primary_key = 'user_id';
    protected static $timestamps = true;
    protected static $timestamps_type = 'timestamp';

    public function __construct($fields_new = array()) {
        parent::__construct($fields_new);
        $this->user_salt = makeRandomString(16);
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
        $query = Database::from(
            'users_access_levels ul',
            'users_access_levels_rel ulr'
        );

        $query->whereAnd(
            'ulr.user_id',
            '=',
            $this->user_id
        );

        $query->whereAnd(
            'ulr.access_level_id',
            '=',
            'ul.access_level_id',
            'field'
        );

        $query->whereAnd(
            'ul.access_level_name',
            'LIKE',
            $access_level
        );

        return !!$query->count();
    }

    public function getPhoto() {
        if ($this->user_photo) {
            return $this->user_photo;
        }

        return Config::get('app.user_photo_placeholder');
    }
}