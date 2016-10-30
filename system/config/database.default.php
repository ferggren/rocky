<?php
$config = array(
  /**
  * Default database provider
  */
  'default' => 'mysql',

  /**
  * Configs for different providers
  */
  'connections' => array(
    'mysql' => array(
      'host'      => 'localhost',
      'database'  => '',
      'username'  => '',
      'password'  => '',
      'charset'   => 'utf8mb4',
      'collation' => 'utf8mb4_general_ci',
    ),
  ),
);