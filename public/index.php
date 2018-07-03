<?php
require_once(dirname(dirname(__FILE__)) . '/init.php');
Metz\sys\Router::get_instance()
    ->register_router([Metz\app\metz\Router::get_instance(), 'route'])
    ->dispatch();