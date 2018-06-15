<?php
if (!defined('METZ_INIT')) {
    // 防止重复初始化    
    define("METZ_INIT", 1);
    // 版本检查
    if (PHP_VERSION_ID < 50400) {
        exit("Need PHP-5.4.0 or upper.".PHP_EOL);
    }
    define('METZ_PATH_INIT_FILE', __FILE__);
    define('METZ_PATH_HOME', dirname(METZ_PATH_INIT_FILE));
    // composer autoloader
    require_once(METZ_PATH_HOME . '/vendor/autoload.php');
    // start app
    Metz\Sys\App::bootstrap(DA_PATH_HOME.'/conf/app.ini');
    Metz\Sys\Log::debug('Metz (' . Metz\Constant::version_str() . ') init ok');
}
