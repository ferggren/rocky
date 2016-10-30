<?php
class ErrorHandler {
    public static function handleError($errno, $errstr) {
        Logger::log("{$errstr} ({$errno})", 'runtime');

        if ($errno == E_NOTICE) {
            return;
        }
        
        if (Config::get('app.env') == 'dev') {
            $error = "{$errstr} ({$errno})";
        }
        else {
            $error = 'Something went terribly wrong';
        }

        disableBrowserCaching();

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            self::showAjaxError($error);
        }
        else {
            self::showHtmlError($error);
        }

        exit;
    }

    protected static function showHtmlError($error) {
        header('Content-Type: text/html; charset=UTF-8');

        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="utf-8">';
        echo '<title>Woops!</title>';
        echo '</head>';
        echo '<body>';

        echo $error;

        if (Config::get('app.env') == 'dev') {
            echo "<br /><pre>\n";

            self::printBacktrace(self::getBacktrace());

            echo '</pre>';
        }

        echo '</body></html>';
        exit;
    }

    protected static function showAjaxError($error) {
        header('Content-type: application/json; charset=UTF-8');

        if (Config::get('app.env') == 'dev' && count($stack = self::getBacktrace())) {
            $error = $stack[0]['file'] . ' => ' . $error;
        }

        $ret = array(
            'status' => 'error',
            'error' => $error,
        );

        echo json_encode($ret, JSON_UNESCAPED_UNICODE);

        exit;
    }

    public static function handleCliError($errno, $errstr) {
        if ($errno == E_NOTICE) {
            return;
        }

        echo $errstr . " ({$errno})\n\n";

        self::printBacktrace(self::getBacktrace());

        exit;
    }

    protected static function printBacktrace($stack) {
        $maxlen = 0;

        if (!is_array($stack)) {
            return;
        }

        foreach ($stack as $i => $row) {
            $stack[$i]["_len"] = $len = iconv_strlen($row['file']);
            $maxlen = max($maxlen, $len);
        }

        $counter = 0;
        foreach ($stack as $row) {
            printf(
                "%2d %s%s%s\n",
                ++$counter,
                $row['file'],
                str_repeat(' ', $maxlen - $row['_len'] + 1),
                $row['context']
            );
        }
    }

    protected static function getBacktrace() {
        $ret = array();
        $file = '';

        foreach (debug_backtrace() as $error) {
            if (isset($error['file'])) {
                $file = str_replace(ROOT_PATH, '', $error['file']);
            }

            // assume that errorHandler itself does't contain any errors
            if (preg_match('#libs/errorhandler\.class\.php$#i', $file)) {
                continue;
            }

            $context = '';

            if (isset($error['class'])) {
                if (isset($error['type']) && $error['type'] == '::') {
                    $context = $error['class'] . '::';
                }
                else {
                    $context = $error['class'] . '->';
                }
            }

            $context .= $error['function'] . '()';

            $line = '';

            if (isset($error['line'])) {
                $line = '::' . $error['line'];
            }


            $ret[] = array(
                'file' => $file . $line,
                'context' => $context,
            );
        }

        return $ret;
    }
}
?>