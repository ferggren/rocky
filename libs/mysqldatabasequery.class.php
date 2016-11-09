<?php
class MySQLDatabaseQuery extends DatabaseQuery {
  protected static $link = false;
  protected static $auto_increment_cache = array();
  protected static $primary_keys_cache = array();

  protected static function getConnection() {
    if (self::isConnected()) {
      return self::$link;
    }

    self::connect();

    return self::$link;
  }

  protected static function isConnected() {
    return !!self::$link;
  }

  protected static function connect() {
    $config = Config::get('database.connections');

    if (!isset($config['mysql'])) {
      trigger_error('mysql config could not be found in database.connections');
      exit;
    }

    $config = $config['mysql'];

    self::$link = mysqli_connect(
      $config['host'],
      $config['username'],
      $config['password'],
      $config['database']
    );

    if (!self::$link) {
      $error = mysqli_connect_errno() . ':' . mysqli_connect_error();
      trigger_error('mysql connection failed: ' . $error);
      exit;
    }

    mysqli_set_charset(self::$link, $config['charset']);
    mysqli_query(self::$link, 'SET collation_connection = ' . $config['collation']);

    return true;
  }

  public function query($query) {
    $link = self::getConnection();

    $result = mysqli_query($link, $query);

    if (!$result) {
      trigger_error('database error: ' . mysqli_error($link));
      exit;
    }

    if ($result === true) {
      return true;
    }

    $results = array();

    while ($row = mysqli_fetch_assoc($result)) {
      $results[] = $row;
    }

    return $results;
  }

  public function escape($string) {
    return mysqli_real_escape_string(
      self::getConnection(),
      $string
    );
  }

  public function getPrimaryKey() {
    if (!$this->current_table) {
      return false;
    }

    if (isset(self::$primary_keys_cache[$this->current_table])) {
      return self::$primary_keys_cache[$this->current_table];
    }

    $query = 'SHOW KEYS FROM ' . $this->escapeTable($this->current_table) . ' WHERE Key_name = "PRIMARY"';
    $result = $this->query($query);

    $keys = array();
    foreach($result as $key) {
      $keys[$key['Seq_in_index']] = $key['Column_name'];
    }

    if (count($keys) > 1) {
      asort($keys);
    }

    return self::$primary_keys_cache[$this->current_table] = $keys;
  }

  public function getAutoIncrementField() {
    if (!$this->current_table) {
      return false;
    }

    if (isset(self::$auto_increment_cache[$this->current_table])) {
      return self::$auto_increment_cache[$this->current_table];
    }

    $fields = $this->query('SHOW COLUMNS FROM ' . $this->escapeTable($this->current_table));
    $auto_increment_field = false;

    foreach ($fields as $field) {
      if (!$field['Extra']) {
        continue;
      }

      $pos = strpos(strtolower($field['Extra']), 'auto_increment');
      if ($pos === false) {
        continue;
      }

      $auto_increment_field = $field['Field'];
      break;
    }

    return self::$auto_increment_cache[$this->current_table] = $auto_increment_field;
  }

  public function getLastAutoIncrementValue() {
    return mysqli_insert_id(self::getConnection());
  }

  public function tableExists() {
    $query = 'SHOW TABLES LIKE "' . $this->escape($this->current_table) . '"';
    return !!count($this->query($query));
  }

  public function escapeFieldName($name) {
    if (($pos = strpos($name, '.')) !== false) {
      $table = substr($name, 0, $pos);
      $name = substr($name, $pos + 1);

      return '`' . $this->escape($table) . '`.`' . $this->escape($name) . '`';
    }

    return '`' . $this->escape($name) . '`';
  }

  public function escapeFieldValue($value) {
    if (is_null($value)) {
      return 'NULL';
    }
    
    return '"' . $this->escape($value) . '"';
  }

  public function escapeTable($table) {
    if (strpos($table, ' ') !== false) {
      if (!preg_match('#^([0-9a-zA-Z_-]++)\s++([0-9a-zA-Z_-_]++)$#', $table, $data)) {
        return '`' . $this->escape($table) . '`';
      }

      return '`' . $this->escape($data[1]) . '` `' . $this->escape($data[2]) . '`';
    }

    return '`' . $this->escape($table) . '`';
  }
}
?>