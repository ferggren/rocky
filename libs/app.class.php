<?php
class App {
    public static function run() {
        // somehow disable caching in a nice way?

        $url = isset($_SERVER['DOCUMENT_URI']) ? $_SERVER['DOCUMENT_URI'] : '/';

        if (!ControllersLoader::exists($url)) {
            $url = Config::get('app.default_controller');

            if (!ControllersLoader::exists($url)) {
                trigger_error('default controller not found');
                exit;
            }
        }

        ControllersLoader::load($url);
    }
}