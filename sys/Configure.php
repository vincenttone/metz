<?php
/**
 * @author	vincent
 * @brief	配置管理
 * @version	1.0
 */
namespace Metz\sys;

class Configure
{
    private static $_instance = null;
    private $_config_path = null;
    private $_configure = null;
    private $_check_run_mode_suffix = false;

    private static $_run_mode_map = [
        Constant::RUN_MODE_PRO => 'production',
        Constant::RUN_MODE_DEV => 'development',
        Constant::RUN_MODE_UT => 'unittest',
        Constant::RUN_MODE_PRE=> 'pre-production',
    ];

    private static $_run_mode_abbr_map = [
        Constant::RUN_MODE_PRO => '',
        Constant::RUN_MODE_DEV => 'dev',
        Constant::RUN_MODE_UT => 'ut',
        Constant::RUN_MODE_PRE=> 'pre',
    ];

    /**
     * 只能内部实例化
     */
    private function __construct()
    {
    }

    /**
     * @return array
     */
    static public function get_instance()
    {
        if (is_null(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class;
        }
        return self::$_instance;
    }

    /**
     * 禁用对象克隆
     */
    private function __clone()
    {
        throw new \Exception("Could not clone the object from class: ".__CLASS__);
    }

    /**
     * @param string
     * @return array
     */
    public function set_config_path($config_path)
    {
        $this->_config_path = $config_path;
        return $this;
    }

    /**
     * @param bool
     * @return array
     */
    public function check_run_mode_suffix($with = false)
    {
        $this->_check_run_mode_suffix = $with;
        return $this;
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    private function get_config_path()
    {
        if (is_null($this->_config_path)) {
            throw new \Exception('Please set config path', \Const_Err_Base::ERR_NO_CONFIG_PATH);
        }
        return $this->_config_path;
    }

    /**
     * @param string
     * @return array
     */
    public function get_config_from_filepath($file_path)
    {
        $key = md5($file_path);
        $config = [];
        if (isset($this->_configure[$key])) {
            $config = $this->_configure[$key];
        } else {
            if (is_file($file_path)) {
                $config = parse_ini_file($file_path, true);
            }
            $this->_configure[$key] = $config;
        } 
        return $config;
    }

    /**
     * @param string
     * @param null|string
     * @return array
     * @throws \Exception
     */
    public function get_config_from_file_and_path($file, $path = null)
    {
        $config = false;
        $path == null && $path = $this->get_config_path();
        $file_path = $path . DIRECTORY_SEPARATOR . $file;
        return $this->get_config_from_filepath($file_path);
    }

    /**
     * @param string
     * @param null|array
     * @param null|string
     * @return array|bool
     */
    public function get_config_from_file($file, $section = null, $path = null)
    {
        $config = $this->get_config_from_file_and_path($file, $path);
        if (empty($section)) {
            return $config;
        }
        if (isset($config[$section])) {
            return $config[$section];
        }
        return false;
    }

    /**
     * @param string
     * @param null|string
     * @return array|bool
     * @throws \Exception
     */
    public function get_config($str, $path=null)
    {
        $path_array = explode('/', $str);
        if (!isset($path_array[0])) {
            return false;
        }
        $file = '';
        $filepath = '';
        $section = array_pop($path_array);
        empty($path_array) || $file = array_pop($path_array);
        empty($path_array) || $filepath = implode($path_array);
        empty($path) && $path = $this->get_config_path();
        if (!empty($filepath)) {
            $path .= DIRECTORY_SEPARATOR . $filepath;
        }
        if (empty($file)) {
            $file = $section;
            $section = null;
        }
        if (is_file($path . DIRECTORY_SEPARATOR . $file . '.ini')) {
        } elseif (!empty($file) && is_dir($path . DIRECTORY_SEPARATOR . $file)) {
            $path = $path . DIRECTORY_SEPARATOR . $file;
            $file = $section;
            $section = null;
        } else {
            return false;
        }
        $run_mode = App::application()->get_run_mode();
        $run_mode == Constant::RUN_MODE_PRO && $this->check_run_mode_suffix(false);
        $run_mode_abbr = self::run_mode_abbr($run_mode);
        $run_mode_conf_file = empty($run_mode_abbr) ? $file : $file.'.'.$run_mode_abbr;
        if ($this->_check_run_mode_suffix && is_file($path . DIRECTORY_SEPARATOR . $run_mode_conf_file . '.ini')) {
            $file = $run_mode_conf_file;
            Log::debug("use run mode config file: [%s]", $file);
        }
        $file .= '.ini';
        $config = $this->get_config_from_file($file, $section, $path);
        return $config;
    }

    /**
     * @param string
     * @param null|string
     * @return array
     */
    static function config($str, $path=null)
    {
        return self::get_instance()->get_config($str, $path);
    }

    /**
     * @param string
     * @param array
     * @param null|string
     * @return array|bool|null
     */
    static function two_step_conf($str, $backup_field=[], $path=null)
    {
        $first_step_conf = self::config($str);
        if (!is_array($first_step_conf) || !isset($first_step_conf['path'])) {
            return null;
        }
        $conf_path = $first_step_conf['path'];
        $config = self::config($conf_path);
        if ($config === false) {
            return false;
        }
        foreach ($backup_field as $_k => $_f) {
            !isset($config[$_k])
                && isset($first_step_conf[$_f])
                && $config[$_k] = $first_step_conf[$_f];
        }
        return $config;
    }

    /**
     * @param string
     * @return null|int
     */
    static function run_mode_name($run_mode)
    {
        return isset(self::$_run_mode_map[$run_mode])
            ? self::$_run_mode_map[$run_mode]
            : null;
    }

    /**
     * @param string
     * @return null|int
     */
    static function run_mode_abbr($run_mode)
    {
        return isset(self::$_run_mode_abbr_map[$run_mode]) && self::$_run_mode_abbr_map[$run_mode]
            ? self::$_run_mode_abbr_map[$run_mode]
            : null;
    }
}
