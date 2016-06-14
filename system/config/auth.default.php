<?php
$config = array(
    // Different oauth methods
    // Each method can be disabled by setting 'enabled' to false
    'oauth' => array(
        // Vkontakte
        'vkontakte' => array(
            'enabled' => false,
            'app_id' => '',
            'app_secret' => '',
            'redirect' => '//example.com/oauth/process/vkontakte',
            'app_scope' => 'offline',
        ),
    ),

    // Defines method of saving user's photo
    // false - photo will not be saved
    // url - photo will be saved as url
    // export - photo will be saved from url to local dir (and trimmed)
    'oauth_export_photo' => 'export',

    // If false, photo will be saved as is
    // Otherwise, photo will be trimmed to specified size
    'oauth_export_photo_trim' => '200x200',

    // If photo size is lesser than specified, photo will not be exported
    'oauth_export_photo_min_size' => '100x100',
);
?>