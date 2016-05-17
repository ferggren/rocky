<?php
/**
*   Export lang strings specified in lang.export
*/
class UtilsLang_CliController extends CliController {
    public function action_export() {
        printf("Exporting lang... ");
        $langs = Lang::getLangs();

        if (!is_array($langs) || !count($langs)) {
            printf('langs list is not specified');
            return false;
        }

        foreach ($langs as $lang) {
            self::exportLang($lang);
        }
    }

    /**
    *   Export specified language
    *   @param ($lang) language to export
    */
    protected static function exportLang($lang) {
        printf("\n%s: ", $lang);

        if (!Lang::setLang($lang)) {
            printf('lang cannot be changed');
            return false;
        }

        $export = Config::get('lang.export');

        if (!is_array($export) || !count($export)) {
            printf('export list is not specified');
            return false;
        }

        printf('ok');

        foreach ($export as $file_prefix => $list) {
            printf("\n- %s -> ", $file_prefix);

            $buffer  = 'if(!window.LangStrings){var LangStrings={};}';
            $buffer .= 'LangStrings.' . $lang . '={';
            $buffer .= self::makeStringsBuffer($list);
            $buffer .= '};';

            $file = ROOT_PATH . '/public/lang/' . $file_prefix . '_' . $lang . '.js';
            $file_tmp = ROOT_PATH . '/tmp/lang.tmp';

            if (!($fd = fopen($file_tmp, 'wb'))) {
                printf('file cannot be created');
                continue;
            }

            fwrite($fd, $buffer);
            fclose($fd);

            if (!(rename($file_tmp, $file))) {
                printf('file cannot be renamed');
                continue;
            }

            printf(' ok');
        }
    }

    /**
    *   Makes js code for listed strings files (for currently selected language)
    *   @param ($strings_list) list of strings files to be exported
    */
    protected static function makeStringsBuffer($strings_list) {
        $buffer = '';

        foreach ($strings_list as $strings_prefix) {
            if ($buffer) { $buffer .= ','; }

            $buffer .= "'{$strings_prefix}':{";

            $strings = Lang::getStrings($strings_prefix);

            if (is_array($strings)) {
                echo '+';

                $first = true;
                foreach ($strings as $key => $string) {
                    if (!$first) { $buffer .= ','; }
                    $first = false;

                    $buffer .= "'".htmlspecialchars($key)."':";
                    $buffer .= json_encode($string);
                }
            }
            else {
                echo '-';
            }

            $buffer .= '}';
        }

        return $buffer;
    }
}
?>