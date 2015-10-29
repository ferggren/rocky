<?php
class AjaxController extends BaseController {
    public function __construct() {
        parent::__construct();
    }

    protected static function sendDefaultHeaders() {
        disableBrowserCaching();
        header('Content-type: application/json; charset=UTF-8');
    }
}
?>