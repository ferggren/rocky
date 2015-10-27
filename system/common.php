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
?>