<?php
class ErrorHandler {
    public static function handleError($errno, $errstr) {
        // check debug mode
        if (1) {
            $error = "{$errstr} ({$errno})";
        }
        else {
            $error = "Something went terribly wrong";
        }

        //disable caching?

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            self::showAjaxError($error);
        }
        else {
            self::showHtmlError($error);
        }

        exit;
    }

    protected static function showHtmlError($error) {
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="utf-8">';
        echo '<title>Woops!</title>';
        echo '</head>';
        echo '<body>';

        echo $error;

        // check debug mode
        if (1) {
            echo "<br /><pre>\n";

            self::printBacktrace(self::getBacktrace());

            echo "</pre>";
        }

        echo "</body></html>";
        exit;
    }

    protected static function showAjaxError($error) {
        $ret = array(
            "status" => "error",
            "error" => $error,
        );

        echo json_encode($ret, JSON_UNESCAPED_UNICODE);

        exit;
    }

    public static function handleCliError($errno, $errstr) {
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
            $stack[$i]["_len"] = $len = iconv_strlen($row["context"]);
            $maxlen = max($maxlen, $len);
        }

        $counter = 0;
        foreach ($stack as $row) {
            printf(
                "%2d %s%s%s\n",
                ++$counter,
                $row["context"],
                str_repeat(" ", $maxlen - $row["_len"] + 1),
                $row["file"]
            );
        }
    }

    protected static function getBacktrace() {
        $ret = array();
        $file = "";

        foreach (debug_backtrace() as $error) {
            if (isset($error["file"])) {
                $file = str_replace(ROOT_PATH, "", $error["file"]);
            }

            $context = "";

            if (isset($error["class"])) {
                if (isset($error["type"]) && $error["type"] == "::") {
                    $context = $error["class"] . "::";
                }
                else {
                    $context = $error["class"] . "->";
                }
            }

            $context .= $error["function"] . "()";

            $line = "";

            if (isset($error["line"])) {
                $line = "::" . $error["line"];
            }


            $ret[] = array(
                "file" => $file . $line,
                "context" => $context,
            );
        }

        return $ret;
    }
}
?>