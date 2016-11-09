<?php
class Migrations extends Database {
  protected static $table       = '_migrations';
  protected static $primary_key = 'migration_name';
  protected static $timestamps  = false;
}
?>