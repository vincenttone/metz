<?php
require_once(dirname(dirname(__FILE__)) . '/init.php');
Metz\sys\Router::get_instance()
    ->register_router(
        [
            Gaer\Router::get_instance()->load_configure(Metz\configure\Route::configure()),
            'route'
        ]
    )
    ->dispatch();