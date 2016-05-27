<?php
/**
* Translates variable into text variable declaration 
*/
function variable2code($var) {
    $type = gettype($var);

    switch ($type) {
        case 'boolean': {
            return $var ? 'true' : 'false';
        }

        case 'integer': {
            return $var;
        }

        case 'double': {
            return $var;
        }

        case 'string': {
            return "'" . str_replace(array("\\", "'"), array("\\\\", "\\'"), $var) . "'";
        }

        case 'array': {
            $array = array();

            foreach ($var as $key => $value) {
                $array[] = variable2code($key) . '=>' . variable2code($value);
            }

            return 'array(' . implode(',', $array) . ')';
        }

        default: {
            return 'NULL';
        }
    }

    return 'NULL';
}

/**
* Converts ip from string to decimal form
*/

function ip2decimal($ip) {
    if (!preg_match('#^((?:\d{1,3}\.){3}\d{1,3})#', trim($ip), $data)) {
        return 0;
    }

    $ip = explode('.', $data[1]);

    return $ip[0] + ($ip[1] << 8) + ($ip[2] << 16) + ($ip[3] << 24);
}

/**
* Converts ip from decimal form to string
*/
function decimal2ip($ip) {
    $ip = (int)$ip;

    return implode(
        '.',
        array(
            ($ip & 0x000000FF),
            ($ip & 0x0000FF00) >> 8,
            ($ip & 0x00FF0000) >> 16,
            ($ip & 0xFF000000) >> 24,
        )
    );
}

/**
* Send headers to disable caching
*/
function disableBrowserCaching() {
    header('Expires: Thu, 19 Feb 1998 13:24:18 GMT');
    header('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
    header('Cache-Control: no-store, no-cache, must-revelidate');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Cache-Control: max-age=0', false);
    header('Pragma: no-cache');
}

/**
 * Generate random string
 */
function makeRandomString($str_len) {
    if ($str_len < 8) {
        $str_len = 8;
    }

    $bytes_len = ceil($str_len * 3 / 4);

    $str = false;

    if (!$str && function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $str = openssl_random_pseudo_bytes($bytes_len, $strong);

        if (!$strong) {
            $str = false;
        }
    }

    if (!$str && function_exists('mcrypt_create_iv')) {
        $str = mcrypt_create_iv($bytes_len, MCRYPT_DEV_URANDOM);
    }

    if (!$str) {
        return false;
    }

    $str = base64_encode($str);
    $str = substr($str, 0, $str_len);
    $str = strtr($str, '+/', '-_');

    return $str;
}
?>