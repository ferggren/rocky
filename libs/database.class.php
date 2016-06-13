<?php
class Database {
    protected static $table = false;
    protected static $primary_key = false;
    protected static $timestamps = false;

    // timestamp or date
    protected static $timestamps_type = 'timestamp';

    protected $fields_current = false;
    protected $fields_new = false;
    protected $current_table = false;

    public function __construct($arg = false) {
        $this->fields_new = array();
        $this->fields_current = array();
        $this->current_table = false;

        if (self::__is_model()) {
            if (is_array($arg)) {
                $this->fields_new = $arg;
            }

            $this->current_table = static::$table;
        }
        else if (is_string($arg)) {
            $this->current_table = $arg;
        }
    }

    public function __get($name) {
        if (isset($this->fields_current[$name])) {
            return $this->fields_current[$name];
        }

        if (isset($this->fields_new[$name])) {
            return $this->fields_new[$name];
        }

        return NULL;
    }

    public function __set($name, $value) {
        $this->fields_new[$name] = $value;
    }

    public function initRow($fields) {
        $this->fields_current = array();
        $this->fields_new = array();

        if (!is_array($fields)) {
            return false;
        }

        if (count($fields) <= 0) {
            return false;
        }

        $this->fields_current = $fields;

        return true;
    }

    /**
    * Create's a new row for current model
    */
    public static function create($fields = array()) {
        if (!is_array($fields) || !count($fields)) {
            return false;
        }

        if (!self::__is_model()) {
            return false;
        }

        $class = get_called_class();
        $model = new $class($fields);
        if (!$model->save()) {
            return false;
        }

        return $model;
    }

    /**
    * Returns the first row with passed fields, 
    * or create a new one, if nothing was found
    */
    public static function firstOrCreate($fields) {
        if (!($model = self::firstOrNew($fields))) {
            return false;
        }

        if ($model->exists()) {
            return $model;
        }

        if (!$model->save()) {
            return false;
        }

        return $model;
    }

    /**
    * Return's the first row with passed fields,
    * or init (without saving) a new model, if nothing was found
    */
    public static function firstOrNew($fields) {
        if (!is_array($fields) || !count($fields)) {
            return false;
        }

        if (!self::__is_model()) {
            return false;
        }

        $query = self::getDatabaseQuery();

        foreach ($fields as $key => $value) {
            $query->whereAnd($key, '=', $value);
        }

        $result = $query->get();

        if (isset($result[0])) {
            return $result[0];
        }

        $class = get_called_class();
        $class = new $class($result[0]);

        return $class;
    }

    /**
    * Save changes to model
    */
    public function save() {
        if ($this->exists()) {
            if (!is_array($this->fields_current)) {
                return false;
            }

            return $this->updateRow();
        }

        if (!is_array($this->fields_new)) {
            return false;
        }

        return $this->insertRow();
    }

    /**
    * Remove row from a table
    */
    public function delete() {
        if (!$this->exists()) {
            return false;
        }

        if (!($query = $this->__makeWhereQuery())) {
            return false;
        }

        $query->delete();

        $this->fields_current = array();
        $this->fields_new = array();

        return true;
    }

    /**
    * Returns true if row exists in table
    */
    public function exists() {
        $table = $this->current_table;

        if (!is_array($table) && !$table) {
            return false;
        }

        if (is_array($table) && count($table) != 1) {
            return false;
        }

        return !!(is_array($this->fields_current) && count($this->fields_current));
    }

    public static function where($field, $operator, $condition, $cond_type = 'variable') {
        return self::getDatabaseQuery()->where(
            $field,
            $operator,
            $condition,
            false,
            $cond_type
        );
    }

    public static function whereAnd($field, $operator, $condition, $cond_type = 'variable') {
        return self::getDatabaseQuery()->whereAnd(
            $field,
            $operator,
            $condition,
            $cond_type
        );
    }

    public static function whereOr($field, $operator, $condition, $cond_type = 'variable') {
        return self::getDatabaseQuery()->whereOr(
            $field,
            $operator,
            $condition,
            $cond_type
        );
    }

    public static function whereRaw($query, $args = array()) {
        return self::getDatabaseQuery()->whereRaw($query, $args);
    }

    public static function find() {
        return call_user_func_array(
            array(self::getDatabaseQuery(), 'find'),
            func_get_args()
        );
    }

    public static function from() {
        return call_user_func_array(
            array(self::getDatabaseQuery(), 'from'),
            func_get_args()
        );
    }

    public static function get() {
        return self::getDatabaseQuery()->get();
    }

    public static function all() {
        return self::getDatabaseQuery()->all();
    }

    public static function count() {
        return self::getDatabaseQuery()->count();
    }

    public static function update($attributes = array()) {
        return self::getDatabaseQuery()->update($attributes);
    }

    public static function destroy() {
        if (!self::__is_model()) {
            return false;
        }

        $primary_keys = array();

        if (!is_array($primary_keys)) {
            $primary_keys = $primary_keys ? array($primary_keys) : array();
        }

        if (!count($primary_keys)) {
            return false;
        }

        $keys_count = count($primary_keys);

        if ($keys_count > 1) {
            foreach(func_get_args() as $destroy_keys) {
                if (!is_array($destroy_keys)) {
                    $destroy_keys = $destroy_keys ? array($destroy_keys) : array();
                }

                if ($keys_count != count($destroy_keys)) {
                    continue; // or return, or error?
                }

                $query = self::getDatabaseQuery();

                for ($i = 0; $i < $keys_count; ++$i) {
                    $query->whereAnd($primary_keys[$i], '=', $destroy_keys[$i]);
                }

                $query->delete();
            }
        }
        else {
            $query = self::getDatabaseQuery();
            $count = 0;

            foreach(func_get_args() as $destroy_key) {
                if (is_array($destroy_key)) {
                    continue; // return, error?
                }

                ++$count;
                $query->whereOr($primary_keys[0], '=', $destroy_key);
            }

            if ($count) {
                $query->delete();
            }
        }

        return;
    }

    public static function tableExists() {
        return self::getDatabaseQuery()->tableExists();
    }

    public static function orderBy($order_field, $order_type = 'asc') {
        return self::getDatabaseQuery()->orderBy(
            $order_field,
            $order_type
        );
    }

    public static function groupBy($group_field) {
        return self::getDatabaseQuery()->groupBy($group_field);
    }

    public function limit($count, $start = 0) {
        return self::getDatabaseQuery()->limit($count, $start);
    }

    public static function query($query) {
        return self::getDatabaseQuery()->query($query);
    }

    public static function escape($value) {
        return self::getDatabaseQuery()->escape($value);
    }

    protected static function getDatabaseQuery() {
        $database_type = Config::get('database.default');

        $table = false;
        $primary_key = false;
        $view = false;

        $class = get_called_class();

        if (strtolower($class) != 'database') {
            $table = static::$table;
            $primary_key = static::$primary_key;
            $view = $class;
        }

        if ($database_type == 'mysql') {
            return new MySQLDatabaseQuery($table, $primary_key, $view);
        }

        user_error('Incorrect default DB:' . $database_type);
    }

    /**
    * Updates row from a table
    */
    protected function insertRow() {
        if (!count($this->fields_new)) {
            return false;
        }

        $table = $this->current_table;

        if (!is_array($table) && !$table) {
            return false;
        }

        if (is_array($table)) {
            if (count($table) != 1) {
                return false;
            }

            $table = $table[0];
        }

        $insert_fields = $this->fields_new;

        if (static::$timestamps) {
            if (self::$timestamps_type == 'timestamp') {
                $insert_fields['created_at'] = time();
            }
            else {
                $insert_fields['created_at'] = date('Y.m.d H:i:s');
            }

            $insert_fields['updated_at'] = $insert_fields['created_at'];
        }

        if (!count($insert_fields)) {
            return false;
        }

        $query = self::getDatabaseQuery();
        $query->setTable($table);

        if (!$query->insert($insert_fields)) {
            return false;
        }

        $this->fields_current = $insert_fields;
        $this->fields_new = array();

        $field = $query->getAutoIncrementField();
        if ($field) {
            $this->fields_current[$field] = $query->getLastAutoIncrementValue();
        }

        return true;
    }

    /**
    * Insert row into a table
    */
    protected function updateRow() {
        if (!count($this->fields_current)) {
            return false;
        }

        $update_fields = array();

        if (static::$timestamps) {
            if (self::$timestamps_type == 'timestamp') {
                $update_fields['updated_at'] = time();
            }
            else {
                $update_fields['updated_at'] = date('Y.m.d H:i:s');
            }
        }

        $fields_current = $this->fields_current;

        foreach ($this->fields_new as $field => $value) {
            if (isset($fields_current[$field]) && $fields_current[$field] == $value) {
                continue;
            }

            $update_fields[$field] = $value;
            $fields_current[$field] = $value;
        }

        if (!count($update_fields)) {
            return false;
        }

        if (!($query = $this->__makeWhereQuery())) {
            return false;
        }

        $query->update($update_fields);

        $this->fields_new = array();
        $this->fields_current = $fields_current;

        return true;
    }

    /**
    * Makes where query for delete and update functions
    */
    protected function __makeWhereQuery() {
        $table = $this->current_table;
        $keys = static::$primary_key;

        if (!is_array($table) && !$table) {
            return false;
        }

        if (is_array($table)) {
            if (count($table) != 1) {
                return false;
            }

            $table = $table[0];
        }

        $query = Database::getDatabaseQuery();
        $query->setTable($table);

        if (!$keys) {
            $keys = $query->getPrimaryKey();
        }

        if (!is_array($keys)) {
            $keys = $keys ? array($keys) : array();
        }

        if (count($keys)) {
            foreach ($keys as $key) {
                if (!isset($this->fields_current[$key])) {
                    trigger_error('incorrect primary key: ' . $key);
                    exit;
                }

                $query->whereAnd(
                    $key,
                    '=',
                    $this->fields_current[$key]
                );
            }
        }
        else {
            if (!count($this->fields_current)) {
                return false;
            }

            foreach ($this->fields_current as $field => $value) {
                $query->whereAnd($field, '=', $value);
            }
        }

        return $query;
    }

    /**
    * Returns true if class is a model
    * Returns false if class is a database
    */
    protected static function __is_model() {
        return strtolower(get_called_class()) != 'database';
    }
}
?>