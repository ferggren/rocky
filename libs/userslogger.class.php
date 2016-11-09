<?php
class UsersLogger {
  protected static $type2id = false;
  protected static $id2type = false;

  public static function logAction($user_id, $action_type, $action_desc = '') {
    // todo: add spam protection

    $row = new Database('users_logs');
    $row->user_id = $user_id;
    $row->user_ip = ip2decimal(Session::getSessionIp());
    $row->log_type_id = self::type2id($action_type);
    $row->log_desc = iconv_substr($action_desc, 0, 200);
    $row->log_time = time();
    $row->log_backtrace = self::getBacktrace();
    $row->save();

    return true;
  }

  public static function getActions($filter = array(), $start = 0, $amount = 20, $order = 'desc') {
    $start = (int)$start;
    $amount = (int)$amount;

    $query = Database::from('users_logs');

    if (isset($filter['user_id']) && preg_match('#^\d++$#', $filter['user_id'])) {
      $query->whereAnd('user_id', '=', $filter['user_id']);
    }

    if (isset($filter['action_type'])) {
      self::loadActionTypes();

      if (isset(self::$type2id[$filter['action_type']])) {
        $action_id = self::$type2id[$filter['action_type']];

        $query->whereAnd('log_type_id', '=', $action_id);
      }
    }

    if (isset($filter['action_type_id'])) {
      self::loadActionTypes();

      if (isset(self::$id2type[$filter['action_type_id']])) {

        $query->whereAnd('log_type_id', '=', $filter['action_type_id']);
      }
    }

    if (isset($filter['user_ip']) && preg_match('#^(?:\d{1,3}\.){3}\d{1,3}$#', $filter['user_ip'])) {
      $query->whereAnd('user_ip', '=', ip2decimal($filter['user_ip']));
    }

    $query->orderBy('log_time', $order == 'desc' ? 'desc' : 'asc');

    $ret = array(
      'count' => $query->count(),
      'actions' => array(),
    );

    $query->limit($amount, $start);

    foreach ($query->get() as $action) {
      $ret['actions'][] = array(
        'user_id' => $action->user_id,
        'user_ip' => decimal2ip($action->user_ip),
        'action_type_id' => $action->log_type_id,
        'action_type' => self::id2type($action->log_type_id),
        'action_desc' => $action->log_desc,
        'action_time' => $action->log_time,
        'action_backtrace' => $action->log_backtrace,
      );
    }

    return $ret;
  }

  public static function getActionsTypes() {
    self::loadActionTypes();

    return array_keys(self::$type2id);
  }

  protected static function loadActionTypes() {
    static $init = false;

    if ($init) {
      return;
    }

    $init = true;

    self::$type2id = array();
    self::$id2type = array();

    $query = Database::from('users_logs_types');
    foreach ($query->get() as $type) {
      self::$type2id[$type->type_name] = $type->type_id;
      self::$id2type[$type->type_id] = $type->type_name;
    }
  }

  protected static function type2id($action_type) {
    self::loadActionTypes();
    $action_type = strtolower($action_type);

    if (isset(self::$type2id[$action_type])) {
      return self::$type2id[$action_type];
    }

    $row = new Database('users_logs_types');
    $row->type_name = $action_type;
    $row->save();

    $action_id = $row->type_id;

    self::$type2id[$action_type] = $action_id;
    self::$id2type[$action_id] = $action_type;

    return $action_id;
  }

  protected static function id2type($action_id) {
    self::loadActionTypes();

    if (!isset(self::$id2type[$action_id])) {
      return false;
    }

    return self::$id2type[$action_id];
  }

  protected static function getBacktrace() {
    foreach (debug_backtrace() as $error) {
      if (!isset($error['file'])) {
        continue;
      }

      $file = str_replace(ROOT_PATH, '', $error['file']);

      // skip errorHandler and logger
      if (preg_match('#libs/(?:errorhandler|logger|userslogger)\.class\.php$#i', $file)) {
        continue;
      }

      $line = '';

      if (isset($error['line'])) {
        $line = '::' . $error['line'];
      }

      return $file . $line;
    }

    return 'no_backtrace';
  }
}