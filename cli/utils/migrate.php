<?php
/**
 *  Migrate support
 */
class UtilsMigrate_CliController extends CliController {
  /** migrations cache **/
  static $migrations = false;

  public function actionDefault() {
    return $this->actionShow();
  }

  /**
   *  Create new migration
   *
   *  @param {string} name New migration name
   */
  public function actionMake($name) {
    if (!preg_match('#^[a-zA-Z0-9_]{1,30}$#uis', $name)) {
      echo 'Incorrect migration name';
      exit;
    }

    $name = trim($name);
    $name = preg_replace('#\s++#uis', '_', $name);
    $name = strtolower($name);
    $name = 'm' . time() . '_' . $name;

    $out  = "<?php\n";
    $out .= "Class {$name} {\n";
    $out .= "  public static function up() {\n";
    $out .= "    \n";
    $out .= "  }\n";
    $out .= "\n";
    $out .= "  public static function down() {\n";
    $out .= "    return false;\n";
    $out .= "  }\n";
    $out .= "}\n";
    $out .= "?>";

    $file = fopen(ROOT_PATH . "/migrations/{$name}.php", "wb");
    fwrite($file, $out);
    fclose($file);


    printf(
      "Migration was created and saved into file migrations/%s.php",
      $name
    );
  }

  /**
   *  Apply migration
   *
   *  @param {string} migration Migration name
   */
  public function actionApply($migration) {
    if (!self::_migrationExists($migration)) {
      echo "migration doesn't exists";
      exit;
    }

    if (!self::_applyMigration($migration)) {
      echo "Cannot apply migration";
      exit;
    }

    echo "Migration was successfully applied";
  }

  /**
   *  Abort migration
   *
   *  @param {string} migration Migration name
   */
  public function actionAbort($migration) {
    if (!self::_migrationExists($migration)) {
      echo "migration doesn't exists";
      exit;
    }

    if (!self::_abortMigration($migration)) {
      echo "Cannot abort migration";
      exit;
    }

    echo "Migration was successfully aborted";
  }

  /**
   *  Show all migration
   */
  public function actionShow() {
    if (!count($list = self::_getMigrations())) {
      echo "Not a single migration was found!";
      echo "\n\n";
      echo "To make a new migration use \"./cli.php utils/migrate make %migration_name%\"";
      exit;
    }

    foreach ($list as $name => $date) {
      printf("\n%s", $name);

      if (!$date) {
        ++$new;
      }
      else {
        printf(": %s", $date);
      }
    }

    if ($new) {
      echo "\n\n";
      echo "To apply new migration(s) use \"./cli.php utils/migrate update\"";
      echo "\n";
      echo "To apply a single migration use \"./cli.php utils/migrate apply %migration_name%\"";
      exit;
    }

    echo "\n\n";
    echo "Everything is up to date";
  }

  /**
   *  Apply all new migration
   */
  public function actionUpdate() {
    $count = 0;

    foreach ($this->_getMigrations() as $migration => $date) {
      printf("\n%s: ", $migration);

      if ($date) {
        printf("already applied");
        continue;
      }

      ++$count;

      if (!$this->_applyMigration($migration)) {
        echo 'error';
        exit;
      }

      printf('applied');
    }

    if (!$count) {
      printf("\n\nEverything is up to date");
    }
  }

  /**
   *  Return all migration
   *
   *  @return {object} Migration list
   */
  protected function _getMigrations() {
    if (is_array(self::$migrations)) {
      return self::$migrations;
    }

    $migrations = array();

    if (!($dir = opendir(ROOT_PATH . '/migrations/'))) {
      trigger_error('Unable to open migrations dir');
      exit;
    }

    while ($file = readdir($dir)) {
      if (!preg_match('#^(m[0-9]++_[a-zA-Z0-9_]++)\.php$#', $file, $data)) {
        continue;
      }

      $migrations[$data[1]] = false;
    }

    if (Migrations::tableExists()) {
      foreach(Migrations::get() as $row) {
        if (!isset($migrations[$row->migration_name])) {
          continue;
        }

        $migrations[$row->migration_name] = date(
          "Y-m-d H:i",
          $row->migration_applied
        );
      }
    }

    ksort($migrations);

    return self::$migrations = $migrations;
  }

  /**
   *  Apply migration
   *
   *  @param {string} migration Migration name
   *  @return {boolean} Apply status
   */
  protected function _applyMigration($migration) {
    if (!$this->_migrationExists($migration)) {
      return false;
    }

    if (Migrations::tableExists() && Migrations::find($migration)) {
      return true;
    }

    if (!class_exists($migration, false)) {
      include ROOT_PATH . "/migrations/{$migration}.php";
    }

    if (!class_exists($migration, false)) {
      trigger_error("Invalid migration {$migration}");
      exit;
    }

    if ($migration::up() === false) {
      return false;
    }

    $row = new Migrations;
    $row->migration_name = $migration;
    $row->migration_applied = time();
    $row->save();

    return true;
  }

  /**
   *  Abort migration
   *
   *  @param {string} migration Migration name
   *  @return {boolean} Abort status
   */
  protected function _abortMigration($migration) {
    if (!$this->_migrationExists($migration)) {
      return false;
    }

    if (Migrations::tableExists() && !Migrations::find($migration)) {
      return true;
    }

    if (!class_exists($migration, false)) {
      include ROOT_PATH . "/migrations/{$migration}.php";
    }

    if (!class_exists($migration, false)) {
      trigger_error("Invalid migration {$migration}");
      exit;
    }

    if ($migration::down() === false) {
      return false;
    }

    if (!Migrations::tableExists()) {
      return true;
    }

    if (!($migration = Migrations::find($migration))) {
      return true;
    }

    $migration->delete();

    return true;
  }

  /**
   *  Check if migration exists
   *
   *  @param {string} migration Migration name
   *  @return {boolean} Migration exists
   */
  protected function _migrationExists($migration) {
    return !!isset($this->_getMigrations()[$migration]);
  }
}
?>