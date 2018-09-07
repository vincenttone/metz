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

    protected $_deepth = -1;

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

    protected function _get_prefix()
    {
        $file = '';
        $line = '';
        $trace = debug_backtrace();
        if ($this->_deepth < 0) {
            foreach ($trace as $_t) {
                if ((isset($_t['class']) && $_t['class'] == get_called_class())
                    || (isset($_t['file']) && $_t['file'] == __FILE__)
                    || (isset($_t['function']) && $_t['function'] == '{closure}')
                ) {
                    $this->_deepth++;
                    continue;
                }
                isset($_t['file']) && $file = $_t['file'];
                isset($_t['line']) && $line = $_t['line'];
                isset($_t['class']) && $class = $_t['class'];
                isset($_t['function']) && $func = $_t['function'];
                break;
            }
        } elseif (isset($trace[$this->_deepth])) {
            isset($trace[$this->_deepth]['file']) && $file = $trace[$this->_deepth]['file'];
            isset($trace[$this->_deepth]['line']) && $line = $trace[$this->_deepth]['line'];
            isset($trace[$this->_deepth]['class']) && $class = $trace[$this->_deepth]['class'];
            isset($trace[$this->_deepth]['function']) && $func = $trace[$this->_deepth]['function'];
        }
        return sprintf("[%s:%s]", $file, $line);
    }

    protected function _record($info, $level = self::LEVEL_INFO)
    {
        if ($this->_m !== null && is_callable($this->_m)) {
            $prefix = $this->_get_prefix();
            return call_user_func_array(
                $this->_m,
                [
                    $level,
                    sprintf("%s\t%s", $prefix, $info)
                ]
            );
        }
        return null;
    }
}