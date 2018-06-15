<?php
namespace Metz\Sys;

class App
{
    private static $_instance = null;

    private $_init_file = null;
    private $_configure = null;

    private function __construct()
    {
    }
    /**
     * Forbid to clone the object
     */
    private function __clone()
    {
        throw new \Exception("Could not clone the object from class: ".__CLASS__);
    }
    /**
     * @param string the config file of data access system
     * @return array the instance of this class
     */
    static public function bootstrap($init_file = 'app.ini')
    {
        if (is_null(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class;
            self::$_instance->_set_init_file($init_file);
            self::$_instance->_bootstrap();
        }
        return self::$_instance;
    }
    /**
     * @desc bootstrap framework
     * @return boolean
     */
    public function _bootstrap()
    {
        $this->_make_app_config();		// config manager
        $this->_autoload_register();	// set autoloader
        Sys_Config::get_instance()->set_config_path(self::conf_path())
                                  ->check_run_mode_suffix(true);
        $this->_init_log();				// run the logger
        \Module_ModuleManager_Main::get_instance()
            ->init_with_module_path(self::module_path());	//init the modules manager
        return true;
    }
    /**
     * @param string
     */
    private function _set_init_file($file)
    {
        $this->_init_file = $file;
    }
}