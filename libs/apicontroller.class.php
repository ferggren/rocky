<?php
class ApiController extends BaseController {
  public function __construct() {
    parent::__construct();

    if (!self::checkCSRFToken()) {
      $this->jsonError('incorrect CSRF token');
      exit;
    }
  }

  /**
   *  Check if response is valid
   */
  protected static function checkCSRFToken() {
    $token = false;

    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
      return false;
    }

    if (!isset($_COOKIE['__csrf_token'])) {
      return false;
    }

    $header_token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    $cookie_token = $_COOKIE['__csrf_token'];

    if (!preg_match('#^[0-9a-zA-Z_-]{16,32}$#', $header_token)) {
      return false;
    }

    return $header_token == $cookie_token;
  }

  /**
   *  Set correct response type
   */
  protected static function sendDefaultHeaders() {
    disableBrowserCaching();
    header('Content-type: application/json; charset=UTF-8');
  }

  /**
   *  Success API result
   *
   *  @param {object} data Response data
   *  @return {string} JSON string
   */
  public function success($data = false) {
    $this->_makeJsonResponse($data, 'success');
  }

  /**
   *  Error API result
   *
   *  @param {object} data Error data
   *  @return {string} JSON string
   */
  public function error($data = false) {
    $this->_makeJsonResponse($data, 'error');
  }

  /**
   *  Make API response
   *
   *  @param {object} data Response data
   *  @param {object} data Response status
   *  @return {string} JSON string
   */
  public function _makeJsonResponse($data, $status) {
    if (!in_array($status, array('success', 'error'))) {
      $status = 'error';
    }

    $ret = array(
      'status' => $status,
    );

    if ($data !== false) {
      $ret['response'] = $data;
    }

    $ret = json_encode($ret, JSON_UNESCAPED_UNICODE);
    header('Content-Length: ' . strlen($ret));

    echo $ret;
    exit;
  }
}
?>