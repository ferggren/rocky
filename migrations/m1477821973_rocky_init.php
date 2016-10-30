<?php
Class m1477821973_rocky_init {
  public static function up() {
    Database::query(
      "CREATE TABLE IF NOT EXISTS sessions (
        session_id char(32) NOT NULL,
        user_id int(11) NOT NULL,
        session_ip int(11) NOT NULL,
        session_latest_ip int(10) unsigned NOT NULL,
        session_latest_activity int(10) unsigned NOT NULL,
        created_at int(10) unsigned NOT NULL,
        updated_at int(10) unsigned NOT NULL,
        PRIMARY KEY (session_id),
        KEY updated_at (updated_at)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );

    Database::query(
      "CREATE TABLE IF NOT EXISTS users (
        user_id int(11) unsigned NOT NULL AUTO_INCREMENT,
        user_name char(30) NOT NULL,
        user_password char(32) NOT NULL,
        user_photo char(200) NOT NULL,
        user_salt char(16) NOT NULL,
        user_ip int(10) unsigned NOT NULL,
        user_latest_ip int(10) unsigned NOT NULL,
        user_latest_activity int(10) unsigned NOT NULL,
        user_deleted tinyint(1) NOT NULL DEFAULT '0',
        created_at int(10) unsigned NOT NULL,
        updated_at int(10) unsigned NOT NULL,
        PRIMARY KEY (user_id)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );

    Database::query(
      "CREATE TABLE IF NOT EXISTS users_access_levels (
        access_level_id int(11) unsigned NOT NULL AUTO_INCREMENT,
        access_level_name char(20) NOT NULL,
        PRIMARY KEY (access_level_id),
        UNIQUE KEY `name` (access_level_name)
      ) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4;"
    );

    $row = Database::from('users_access_levels');
    $row->where('access_level_name', '=', 'admin');
    
    if (!$row->count()) {
      $row = new Database('users_access_levels');
      $row->access_level_name = 'admin';
      $row->save();
    }

    Database::query(
      "CREATE TABLE IF NOT EXISTS users_access_levels_rel (
        user_id int(10) unsigned NOT NULL,
        access_level_id int(10) unsigned NOT NULL,
        PRIMARY KEY (user_id,access_level_id),
        KEY type_id (access_level_id)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );

    Database::query(
      "CREATE TABLE IF NOT EXISTS users_oauth_facebook (
        user_id int(10) unsigned NOT NULL,
        facebook_id char(30) NOT NULL,
        facebook_photo varchar(200) NOT NULL,
        facebook_name varchar(100) NOT NULL,
        access_token varchar(255) NOT NULL,
        last_login int(10) unsigned NOT NULL,
        PRIMARY KEY (facebook_id),
        KEY user_id (user_id)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );

    Database::query(
      "CREATE TABLE IF NOT EXISTS users_oauth_google (
        user_id int(10) unsigned NOT NULL,
        google_id char(30) NOT NULL,
        google_photo varchar(200) NOT NULL,
        google_name varchar(100) NOT NULL,
        access_token varchar(255) NOT NULL,
        last_login int(10) unsigned NOT NULL,
        PRIMARY KEY (google_id),
        KEY user_id (user_id)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );

    Database::query(
      "CREATE TABLE IF NOT EXISTS users_oauth_twitter (
        user_id int(10) unsigned NOT NULL,
        twitter_id char(30) NOT NULL,
        twitter_photo varchar(200) NOT NULL,
        twitter_name varchar(100) NOT NULL,
        access_token varchar(255) NOT NULL,
        last_login int(10) unsigned NOT NULL,
        PRIMARY KEY (twitter_id),
        KEY user_id (user_id)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );

    Database::query(
      "CREATE TABLE IF NOT EXISTS users_oauth_vkontakte (
        user_id int(10) unsigned NOT NULL,
        vkontakte_id int(10) unsigned NOT NULL,
        vkontakte_photo varchar(200) NOT NULL,
        vkontakte_name varchar(100) NOT NULL,
        access_token varchar(255) NOT NULL,
        last_login int(10) unsigned NOT NULL,
        PRIMARY KEY (vkontakte_id),
        KEY user_id (user_id)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );
  }

  public static function down() {
    return false;
  }
}
?>