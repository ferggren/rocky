<?php
class Logger {
    public static function log($message, $type = "system") {
        if (!preg_match('#^[0-9a-z_.-]++$#', $type)) {
            return false;
        }

        $dir_path = ROOT_PATH . "/logs/";
        $log_path = $dir_path . $type . ".log";

        if (!file_exists($log_path)) {
            if (!is_writable($dir_path)) {
                return false;
            }
        }
        else {
            if (!is_writable($log_path)) {
                return false;
            }
        }

        if (!($file = fopen($log_path, "ab"))) {
            return false;
        }

        $user_ip = 'cli';
        $user_id = 0;

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $user_ip = preg_replace('#[^\d.]#', '', $_SERVER['REMOTE_ADDR']);
        }

        $backtrace = self::getBacktrace();
        
        $message = sprintf(
            "[%s, %s, %s, %d] %s\n",
            date("Y.m.d H:i:s"),
            $backtrace,
            $user_ip,
            $user_id,
            $message
        );

        fwrite($file, $message);
        fclose($file);

        return true;
    }

    protected static function getBacktrace() {
        foreach (debug_backtrace() as $error) {
            if (!isset($error["file"])) {
                continue;
            }

            $file = str_replace(ROOT_PATH, "", $error["file"]);

            // skip errorHandler and logger
            if (preg_match('#libs/(?:errorhandler|logger)\.class\.php$#i', $file)) {
                continue;
            }

            $line = "";

            if (isset($error["line"])) {
                $line = "::" . $error["line"];
            }

            return $file . $line;
        }

        return "no_backtrace";
    }
}
?>