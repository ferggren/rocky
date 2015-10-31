<?php
class Cli {
    public static function run() {
        global $argv;

        $srcript_name = isset($argv[0]) ? $argv[0] : 'cli.php';
        $scripts_list = CliControllersLoader::getScriptsList();

        print_r($scripts_list);
        exit;
    }

    protected static function processCliArgs() {
        
    }
}
?>