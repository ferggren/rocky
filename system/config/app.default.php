<?php
$config = array(
    // Enables debug output
    'debug' => true,

    // Controller that will be loaded by default
    // (if empty or incorrect controller was passed in url)
    'default_controller' => 'index',

    // If true, controllers tree will be cached in ./tmp/
    // Cache doesn't rebuild automaticly,
    // if changes were made, you need to manually delete ./tmp/controllers*
    'cache_controllers' => false,

    // If true, templates will be cached in ./tmp/
    // Cache doesn't rebuild automaticly,
    // if changes were made, you need to manually delete ./tmp/templates*
    'cache_templates' => false,

    // Salt will be used to make and check session session stuff
    'session_salt' => 'random_salt_here',

    // How often session will update user's latest activity timestamp
    // Set false to disable
    'session_pulse' => 300,

    // Base domain for cookies
    'cookie_domain' => 'example.com',
);
?>