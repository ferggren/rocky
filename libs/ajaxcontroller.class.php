<?php
class AjaxController extends BaseController {
    public function __construct() {
        parent::__construct();

        if (!self::checkCSRFToken()) {
            $this->jsonError('incorrect CSRF token');
            exit;
        }
    }

    protected static function checkCSRFToken() {
        $token = false;

        if (isset($_POST['__csrf_token'])) {
            $token = $_POST['__csrf_token'];
        }

        if (isset($_GET['__csrf_token'])) {
            $token = $_GET['__csrf_token'];
        }

        if (!$token || !is_string($token)) {
            return false;
        }

        if (!preg_match('#^[0-9a-zA-Z_-]++$#', $token)) {
            return false;
        }

        if (!isset($_COOKIE['token_' . $token])) {
            return false;
        }

        return $_COOKIE['token_' . $token] == $token;
    }

    protected static function sendDefaultHeaders() {
        disableBrowserCaching();
        header('Content-type: application/json; charset=UTF-8');
    }

    public function jsonSuccess($data = false) {
        $ret = array(
            "status" => "success",
        );

        if ($data !== false) {
            $ret["response"] = $data;
        }

        echo json_encode($ret, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function jsonError($data = false) {
        $ret = array(
            "status" => "error",
        );

        if ($data !== false) {
            $ret["error"] = $data;
        }

        echo json_encode($ret, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>