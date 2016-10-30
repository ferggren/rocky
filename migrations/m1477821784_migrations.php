<?php
Class m1477821784_migrations {
  public static function up() {
    Database::query(
      "CREATE TABLE _migrations (
        migration_name varchar(100) CHARACTER SET latin1 NOT NULL,
        migration_applied int(10) unsigned NOT NULL,
        PRIMARY KEY (migration_name)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;"
    );
  }

  public static function down() {
    Database::query("DROP TABLE _migrations");
  }
}
?>