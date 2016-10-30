<?php
$config = array(
    // production || dev
    'env' => 'production',

    // Controller that will be loaded by default
    // (if empty or incorrect controller was passed in url)
    'default_controller' => 'index',

    // Controller that will be loaded if user needs authentication
    'auth_controller' => 'index',

    // If true, controllers tree will be cached in ./tmp/
    // Cache doesn't rebuild automaticly (use "./cli.php utils/cache rebuild" to update cache)
    'cache_controllers' => false,

    // If true, scripts will be cached in ./tmp/
    // Cache doesn't rebuild automaticly (use "./cli.php utils/cache rebuild" to update cache)
    'cache_scripts' => false,

    // If true, templates will be cached in ./tmp/
    // Cache doesn't rebuild automaticly (use "./cli.php utils/cache rebuild" to update cache)
    'cache_templates' => false,

    // Salt will be used to make and check session stuff
    'session_salt' => 'random_salt_here',

    // How often session will update user's latest activity timestamp
    // Set false to disable
    'session_pulse' => 300,

    // Base domain for cookies
    'cookie_domain' => 'example.com',

    // Access rules for specific urls
    // Also, access can be specified into controller itself (and also that form has a higher priority)
    // each success rule rewrite previous ones, so be careful with rules order
    'url_rules' => array(
        // default rule
        '#^(?:/|index)#' => array('auth' => false, 'access_level' => false, 'type' => 'default'),

        // rule for ajax controllers
        '#^/ajax/#' => array('type' => 'ajax'),

        // rule for admin
        '#^/admin#' => array('auth' => true, 'access_level' => 'admin'),
        '#^/ajax/admin/#' => array('auth' => true, 'access_level' => 'admin'),
    ),

    // Log unauthorized requests
    'log_unauthorized_requests' => false,

    // Log user auth
    'log_users_auth' => false,

    // If user doesn't have a photo,
    // User::get_photo() and $user->get_photo() will return that path
    'user_photo_placeholder' => '/images/path_to_placeholder.png',

    // How often user's latest activity will be updated
    // Set false to disable
    'users_pulse' => 60,

    // dump html performance debug
    'dump_perf_debug' => true,

    // Analytics code
    'footer_counters' => array(
        
    ),
);
?>