<?php
require_once(dirname(dirname(__FILE__)) . '/init.php');
Gaer\Monitor::register_monitor(function ($level, $info) {
    switch($level) {
    case Gaer\Monitor::LEVEL_DEBUG:
        Metz\sys\Log::debug($info);
        break;
    case Gaer\Monitor::LEVEL_INFO:
        Metz\sys\Log::info($info);
        break;
    case Gaer\Monitor::LEVEL_WARNING:
        Metz\sys\Log::warn($info);
        break;
    default:
        Metz\sys\Log::error($info);
    }
});
Metz\sys\Router::get_instance()
    ->register_router(
        [
            Gaer\Router::get_instance()->load_configure(Metz\configure\Route::configure()),
            'route'
        ]
    )
    ->register_exception_handler([\Gaer\route\ExceptionHandler::class, 'process'])
    ->dispatch();