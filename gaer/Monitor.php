<?php
namespace Gaer;

class Monitor
{
    const LEVEL_DEBUG = 1;
    const LEVEL_INFO = 2;
    const LEVEL_WARNING = 3;
    const LEVEL_ERROR = 4;

    protected static $_instance = null;
    protected $_m = null;

    protected function __construct()
    {
    }
    /**
     * Forbid to clone the object
     */
    protected function __clone()
    {
        throw new \Exception("Could not clone the object from class: ".__CLASS__);
    }
    static function register_monitor($m)
    {
        return self::_get_monitor()->_register_monitor($m);
    }

    static function debug($info)
    {
        return self::record($info, self::LEVEL_DEBUG);
    }

    static function info($info)
    {
        return self::record($info, self::LEVEL_INFO);
    }

    static function warn($info)
    {
        return self::record($info, self::LEVEL_WARNING);
    }

    static function error($info)
    {
        return self::record($info, self::LEVEL_ERROR);
    }

    static function record($info, $level = self::LEVEL_INFO)
    {
        return self::_get_monitor()->_record($info, $level);
    }
    /**
     * @return array the instance of the singleton
     */
    protected static function _get_monitor()
    {
        if (is_null(self::$_instance)) {
            $class = get_called_class();
            self::$_instance = new $class;
        }
        return self::$_instance;
    }

    protected function _register_monitor($m)
    {
        if (is_callable($m)) {
            $this->_m = $m;
            self::record('monitor register success.', self::LEVEL_DEBUG);
        }
        return $this;
    }

    protected function _record($info, $level = self::LEVEL_INFO)
    {
        if ($this->_m !== null && is_callable($this->_m)) {
            return call_user_func_array($this->_m, [$level, $info]);
        }
        return null;
    }
}