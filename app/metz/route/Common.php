<?php
namespace Gaer\route;

use Gaer\exceptions;

class Common extends Route
{
    const DEFAULT_CLASS = 'Home';
    const DEFAULT_METHOD = 'index';

    protected $_controller_path = 'controller';

    public function __construct($uri, $klass = null, $method = null)
    {
        parent::__construct($uri, $klass, $method);
        $this->_generate();
    }

    public function match($uri)
    {
        $uri = trim($uri);
        if ($uri == '/' || $uri == '') {
            $uri = '/';
        } else {
            $uri = trim($uri, '/');
        }
        if (strpos($uri, $this->_uri) === 0) {
            $len = strlen($this->_uri);
            $left_str = trim(substr($uri, $len), '/');
            $left = empty($left_str) ? [] : explode('/', $left_str);
            $left_count = count($left);
            if ($this->_klass) {
                if (!$this->_method) {
                    $this->_method = $left_count > 0 ? $left[0] : self::DEFAULT_METHOD;
                }
            } elseif ($this->_prefix) {
                $left_count == 0 && $left[] = self::DEFAULT_CLASS;
                $method = array_pop($left);
                $kls = '\\' . trim($this->_prefix, '\\');
                $this->_controller_path && $kls .= $this->_controller_path . '\\';
                isset($left[0]) && $kls .= '\\' . implode('\\', $left);
                if (class_exists($kls)) {
                    $this->_klass = $kls;
                    $this->_method = $method;
                } elseif (class_exists($kls . '\\' . $method)) {
                    $this->_klass = $kls . '\\' . $method;
                    $this->_method = self::DEFAULT_METHOD;
                }
            }
        } else {
            return false;
        }
        if ($this->_klass === null
            || $this->_method === null
            || !method_exists($this->_klass, $this->_method)
        ) {
            return false;
        }
        return true;
    }

    public function exec()
    {
        return call_user_func_array([new $this->_klass, $this->_method], $this->_args);
    }

    public function enable_controller($path = 'controller')
    {
        $this->_controller_path = $name;
        return $this;
    }

    public function disable_controller()
    {
        $this->_controller_path = null;
        return $this;
    }

    protected function _generate()
    {
        if ($this->_klass === null && empty($this->_prefix)) {
            $uri_arr = $this->get_uri_array();
            $tail = array_pop($uri_arr);
            $kls = implode('\\', $uri_arr);
            if (class_exists($kls)) {
                $this->_klass = $kls;
            } elseif (class_exists($kls . '\\' . $tail)) {
                $this->_klass = $kls . '\\' . $tail;
            } else {
                $this->_prefix = $kls . '\\' . $tail;
            }
        }
    }
}