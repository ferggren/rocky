<?php
abstract class DatabaseQuery {
    protected $group_by = array();
    protected $order_by = array();
    protected $where = array();
    protected $where_raw = '';
    protected $limit = array();
    protected $tables = array();

    protected $current_table = false;
    protected $primary_key = false;
    protected $model = false;

    public function __construct($table, $primary_key, $model) {
        $this->model = $model;

        $this->setTable($table);

        $this->primary_key = array();

        if ($primary_key) {
            $primary_key = is_array($primary_key) ? $primary_key : array($primary_key);

            foreach ($primary_key as $key) {
                if (!preg_match('#^[a-zA-Z0-9_-]++$#', $key)) {
                    trigger_error('Primary key is not correct: '. $key);
                    exit;
                }

                $this->primary_key[] = $key;
            }
        }
    }

    public function setTable() {
        $args = func_get_args();
        $tables = array();

        foreach ($args as $arg) {
            if (!$arg) {
                continue;
            }

            if (!is_array($arg)) {
                $tables[] = $arg;
                continue;
            }

            foreach ($arg as $_arg) {
                if (is_array($_arg)) {
                    continue;
                }

                $tables[] = $_arg;
            }
        }

        $this->tables = array();
        $this->current_table = false;

        if (!count($tables)) {
            return $this;
        }

        foreach ($tables as $t) {
            if (!preg_match('#^([a-zA-Z0-9_-]+)(?:\s++[a-zA-Z0-9_-]++)?$#', $t, $data)) {
                trigger_error('incorrect table name: ' . $t);
                exit;
            }

            $this->current_table = $data[0];
            $this->tables[] = $t;
        }

        if (count($tables) > 1) {
            $this->current_table = false;
        }

        return $this;
    }

    public function from() {
        return call_user_func_array(
            array($this, 'setTable'),
            func_get_args()
        );
    }

    public function find() {
        $args = func_get_args();
        $ids = array();

        foreach ($args as $arg) {
            if (!is_array($arg)) {
                $ids[] = $arg;
                continue;
            }

            foreach ($arg as $_a) {
                if (is_array($_a)) {
                    // omg
                    continue;
                }

                $ids[] = $_a;
            }
        }

        if (!count($this->primary_key)) {
            return false;
        }

        if (count($this->primary_key) != count($ids)) {
            trigger_error('all the primary keys need to be specified');
            exit;
        }

        for ($i = 0, $end = count($this->primary_key); $i < $end; ++$i) {
            $this->whereAnd($this->primary_key[$i], '=', $ids[$i]);
        }

        if (!$this->current_table) {
            trigger_error('table must be specified');
            exit;
        }

        $query = 'SELECT * FROM ' . $this->escapeTable($this->current_table) . ' ' . $this->makeQuery();
        $rows = $this->query($query);

        if (!is_array($rows)) {
            return false;
        }

        if (count($rows) != 1) {
            return false;
        }

        if ($this->model) {
            $model = new $this->model;
        }
        else {
            $model = new Database($this->current_table);
        }

        if (!$model->initRow($rows[0])) {
            trigger_error('model initialization error');
            exit;
        }

        return $model;
    }

    public function get() {
        if (!($from = $this->makeFromQuery())) {
            return false;
        }

        $query = 'SELECT * FROM ' . $from . ' ' . $this->makeQuery();
        $rows = $this->query($query);

        if (!is_array($rows)) {
            return false;
        }

        $ret = array();

        foreach ($rows as $row) {
            if ($this->model) {
                $model = new $this->model;
            }
            else {
                $model = new Database($this->current_table);
            }

            if (!$model->initRow($row)) {
                trigger_error('model initialization error');
                exit;
            }

            $ret[] = $model;
        }

        return $ret;
    }

    public function all() {
        return $this->get();
    }

    public function count() {
        if (!($from = $this->makeFromQuery())) {
            return false;
        }

        $query = 'SELECT COUNT(*) as count FROM ' . $from . ' ' . $this->makeQuery();
        $rows = $this->query($query);

        if (!is_array($rows)) {
            return false;
        }

        if (!isset($rows[0]['count'])) {
            return false;
        }

        return (int)($rows[0]['count']);
    }

    public function update($attributes) {
        if (!is_array($attributes) || !count($attributes)) {
            return false;
        }

        if (!$this->current_table) {
            trigger_error('table must be specified');
            exit;
        }

        $query = '';

        foreach ($attributes as $key => $value) {
            if ($query) {
                $query .= ', ';
            }

            $query .= $this->escapeFieldName($key);
            $query .= ' = ' . $this->escapeFieldValue($value);
        }

        $query = 'UPDATE ' . $this->escapeTable($this->current_table) . ' SET ' . $query . ' ' . $this->makeQuery();

        return !!$this->query($query);
    }

    public function insert($attributes) {
        if (!is_array($attributes) || !count($attributes)) {
            return false;
        }

        if (!$this->current_table) {
            trigger_error('table must be specified');
            exit;
        }

        $query = '';

        foreach ($attributes as $key => $value) {
            if ($query) {
                $query .= ', ';
            }

            $query .= $this->escapeFieldName($key);
            $query .= ' = ' . $this->escapeFieldValue($value);
        }

        $query = 'INSERT INTO ' . $this->escapeTable($this->current_table) . ' SET ' . $query;

        return !!$this->query($query);
    }

    public function delete() {
        if (!$this->current_table) {
            trigger_error('table must be specified');
            exit;
        }

        $query = 'DELETE FROM ' . $this->escapeTable($this->current_table) . ' ' . $this->makeQuery();
        return !!$this->query($query);
    }

    public function where($field, $comparison_operator, $arg, $logical_operator = 'AND', $arg_type = 'variable') {
        $this->where_raw = '';

        if (!in_array($comparison_operator, array('<', '<=', '=', '>=', '>', '<>', '!=', 'LIKE'))) {
            trigger_error('incorrect operator: ' . $comparison_operator);
            exit;
        }

        $this->where[] = array(
            'field' => $field,
            'comparison_operator' => $comparison_operator,
            'logical_operator' => $logical_operator,
            'arg' => $arg,
            'arg_type' => $arg_type == 'variable' ? 'variable' : 'field',
        );

        return $this;
    }

    public function whereOr($field, $operator, $arg, $arg_type = 'variable') {
        return $this->where($field, $operator, $arg, 'OR', $arg_type);
    }

    public function whereAnd($field, $operator, $arg, $arg_type = 'variable') {
        return $this->where($field, $operator, $arg, 'AND', $arg_type);
    }

    public function whereRaw($query, $args) {
        $this->where = array();

        $args_count = count($args);
        $ph_count = 0;

        for ($i = 0, $len = strlen($query); $i < $len; ++$i) {
            if ($query[$i] == '?') {
                ++$ph_count;
            }
        }
        
        if ($args_count != $ph_count) {
            trigger_error('placeholders amount must mutch arguments amount');
            exit;
        }

        if ($ph_count == 0) {
            $this->where_raw = $query;
            return $this;
        }

        $sprintf_args = array(
            str_replace(
                array('%', '?'),
                array('%%', '%s'),
                $query
            )
        );

        foreach ($args as $arg) {
            $sprintf_args[] = $this->escapeFieldValue($value);
        }

        $this->where_raw = call_user_func_array(
            'sprintf',
            $sprintf_args
        );

        return $this;
    }

    public function orderBy($order_field, $order_type = 'asc') {
        $order_type = strtolower($order_type);

        if (!in_array($order_type, array('asc', 'desc'))) {
            trigger_error('incorrect order type: ' . $order_type);
            exit;
        }

        $this->order_by[] = array(
            'field' => $order_field,
            'type' => $order_type,
        );

        return $this;
    }

    public function groupBy($group_field) {
        $this->group_by[] = $group_field;

        return $this;
    }

    public function limit($count, $start = 0) {
        $count = (int)$count;
        $start = (int)$start;

        $this->limit = array(
            'count' => $count,
            'start' => $start,
        );

        return $this;
    }

    protected function makeQuery() {
        $query  = '';
        $query .= ' ' . $this->makeWhere() . ' ';
        $query .= ' ' . $this->makeGroupBy() . ' ';
        $query .= ' ' . $this->makeOrderBy() . ' ';
        $query .= ' ' . $this->makeLimit() . ' ';

        return $query;
    }

    protected function makeFromQuery() {
        if (!count($this->tables)) {
            return false;
        }

        $query = '';

        foreach($this->tables as $table) {
            if ($query) {
                $query .= ', ';
            }

            $query .= $this->escapeTable($table);
        }

        return $query;
    }

    protected function makeWhere() {
        if ($this->where_raw) {
            return 'WHERE ' . $this->where_raw;
        }

        if (!count($this->where)) {
            return '';
        }

        $query = '';

        foreach ($this->where as $where) {
            if ($query) {
                $query .= ' ' . ($where['logical_operator'] == 'OR' ? 'OR' : 'AND') . ' ';
            }

            $query .= $this->escapeFieldName($where['field']);
            $query .= ' ' . $where['comparison_operator'] . ' ';

            if ($where['arg_type'] == 'variable') {
                $query .= $this->escapeFieldValue($where['arg']);
            }
            else {
                $query .= $this->escapeFieldName($where['arg']);
            }
        }

        return 'WHERE ' . $query;
    }

    protected function makeGroupBy() {
        if (!count($this->group_by)) {
            return '';
        }

        $query = '';

        foreach ($this->group_by as $group_by) {
            if ($query) {
                $query .= ', ';
            }

            $query .= $this->escapeFieldName($group_by);
        }

        return 'GROUP BY ' . $query;
    }

    protected function makeOrderBy() {
        if (!count($this->order_by)) {
            return '';
        }

        $query = '';

        foreach ($this->order_by as $order_by) {
            if ($query) {
                $query .= ', ';
            }

            $query .= $this->escapeFieldName($order_by['field']) . ' ' . $order_by['type'];
        }

        return 'ORDER BY ' . $query;
    }

    protected function makeLimit() {
        if (!isset($this->limit['start']) || !isset($this->limit['count'])) {
            return '';
        }

        return 'LIMIT ' . $this->limit['start'] . ', ' . $this->limit['count'];
    }

    public abstract function tableExists();
    public abstract function query($query);
    public abstract function escape($data);
    public abstract function getAutoIncrementField();
    public abstract function getLastAutoIncrementValue();
    public abstract function escapeFieldName($name);
    public abstract function escapeFieldValue($value);
    public abstract function escapeTable($table);
}
?>