<?php
if (!defined('METZ_INIT')) {
    define("METZ_INIT", 1);
    // php version checking
    if (PHP_VERSION_ID < 50400) {
        exit('Need PHP-5.4.0 or upper.' . PHP_EOL);
    }
    define('METZ_PATH_INIT_FILE', __FILE__);
    define('METZ_PATH_HOME', dirname(METZ_PATH_INIT_FILE));
    // composer autoloader
    require_once(METZ_PATH_HOME . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
    // start app
    Metz\sys\App::application()->bootstrap(METZ_PATH_HOME. DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'app.ini');
    Metz\sys\Log::debug(
        'Metz (%s) runing at [%s] mode, path [%s]',
        [
            'Metz' => Metz\sys\Constant::version_str(),
            'mode' => Metz\sys\app::run_mode(),
            'dir' => METZ_PATH_HOME,
        ]
    );
}
