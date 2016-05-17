<?php
$config = array(
    // Default language
    'default' => 'en',

    // Check post & get data
    'check_data' => true,

    // Check cookies for language cookie
    'check_cookie' => false,

    // Check HTTP_ACCEPT_LANGUAGE for language
    'check_headers' => true,

    // Available languages
    'list' => array(
        'en',
        'ru',
    ),

    // List of string files that will be exported into js lang module.
    // Each prefix will include all listed strings files and
    // will be saved in /public/lang/$prefix_$lang.js.
    'export' => array(
        // site prefix (site_en.js & site_ru.js)
        'site' => array(
            // list of string files
        ),
    ),
);
?>