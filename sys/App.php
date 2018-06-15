<?php
namespace Metz\sys;

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
    static public function application()
    {
        if (is_null(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class;
            // self::$_instance->bootstrap();
        }
        return self::$_instance;
    }
    /**
     * @return int the run_mode
     */
    static function run_mode()
    {
        return self::application()->get_run_mode();
    }
    /**
     * @desc bootstrap framework
     * @return boolean
     */
    public function bootstrap($init_file = 'app.ini')
    {
        $this->_set_init_file($init_file);
        if ($this->_configure() === false) {  // config manager
            return false;
        }
        $this->_register_autoload();  // set autoloader
        Configure::get_instance()
            ->set_config_path(self::conf_path())
            ->check_run_mode_suffix(true);
        $this->_init_log();  // run the logger
        return true;
    }
    /**
     * @param string
     */
    private function _set_init_file($file)
    {
        $this->_init_file = $file;
    }
    /**
     * @desc autloader
     * @param 
     * @return 
     */
    private function _register_autoload()
    {
        // nothing now
    }
    private function _configure()
    {
        $this->_configure = Configure::get_instance()
                          ->get_config_from_filepath($this->_init_file);
        if ($this->_configure === false) {
            return false;
        }
        $this->_set_run_mode();
        return $this;
    }
    /**
     * init the log manager
     */
    private function _init_log()
    {
        $config = Configure::config('log/base');
        if ($config) {
            $this->_configure['path']['log'] = isset($config['path']) 
                                             ? $config['path'] : 'logs';
            $config['path'] = self::log_path();
        } else {
            $config = [];
        }
        Log::get_instance()->init($config);
    }
    /**
     * @return int the run_mode
     */
    public function get_run_mode()
    {
        return (
            isset($this->_configure['base'])
            && isset($this->_configure['base']['run_mode']))
            ? $this->_configure['base']['run_mode']
            : Constant::RUN_MODE_PRO;
    }

    /**
     * @return array
     */
    private function _set_run_mode()
    {
        if (self::run_mode() == Constant::RUN_MODE_PRO) {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
        } else {
            if (!ini_get('display_errors')) {
                ini_set('display_errors', 'On');
            }
            error_reporting(E_ALL);
        }
        return $this;
    }
    /**
     * @return array
     */
    private function _get_configure()
    {
        return $this->_configure;
    }
    /**
     * @param string
     * @param null|string $sub_path
     * @return null|string
     */
    public function get_path_by_name($pathname, $sub_path = '')
    {
        $path = null;
        $get_value = function ($base, $keyword) {
            $path = null;
            isset($this->_configure['path'])
            && isset($this->_configure['path'][$keyword])
            && $path = $this->_configure['path'][$keyword];
            if (is_null($path)) {
                throw new \Exception('No such configure :'.$base
                . '/'.$keyword.' in config file'
                , \Const_Err_Base::ERR_CONFIG_MISSING);
            }
            return $path;
        };
        $path = $get_value('path', $pathname);
        if (substr($path, 0,1) !== '/') {
            $path = $this->get_path_by_name('home') . '/' . $path;
        }
        if (substr($path, -1,1) === '/') {
            $path = substr($path, 0, strlen($path) - 1);
        }
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        if (!empty($sub_path)) {
            $path .= '/'.$sub_path;
        }
        return $path;
    }
    /**
     * @param string
     * @param array
     * @return string|null
     * @throws \Exception
     */
    static function __callStatic($name, $args)
    {
        if (stripos($name, '_path') > 0) {
            $path = substr($name, 0, strlen($name) - 5);
            $config = self::application()->_get_configure();
            if (isset($config['path'])) {
                try {
                    $sub_path = '';
                    if (!empty($args) && isset($args[0])) {
                        $sub_path = $args[0];
                    }
                    return self::application()->get_path_by_name($path, $sub_path);
                } catch (\Exception $ex) {
                }
            }
        }
        throw new \Exception('Method '.$name.' not exists');
    }
}