<?php
Class m1478670886_users_groups {
  public static function up() {
    Database::query("ALTER TABLE users ADD user_groups SET('admin') NOT NULL AFTER user_photo");

    foreach (Database::from('users')->get() as $user) {
      $groups = array();

      $res = Database::from(
        'users_access_levels ul',
        'users_access_levels_rel ulr'
      );

      $res->whereAnd(
        'ulr.user_id',
        '=',
        $user->user_id
      );

      $res->whereAnd(
        'ulr.access_level_id',
        '=',
        'ul.access_level_id',
        'field'
      );

      foreach ($res->get() as $group) {
        $groups[] = $group->access_level_name;
      }

      $user->user_groups = implode(',', $groups);
      $user->save();
    }

    Database::query("DROP TABLE users_access_levels");
    Database::query("DROP TABLE users_access_levels_rel");
  }

  public static function down() {
    return false;
  }
}
?>