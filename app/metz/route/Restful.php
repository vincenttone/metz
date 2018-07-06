<?php
namespace Metz\app\metz\route;

use Metz\app\metz\exceptions;
use Metz\sys\Log;

class Restful extends Route
{
    const METHOD_CREATE = 'create';
    const METHOD_UPDATE = 'update';
    const METHOD_DELETE = 'delete';
    const METHOD_GET = 'get';
    const METHOD_LIST = 'list';

    const ACTION_PATCH = 'patch';
    const ACTION_PUT = 'put';
    const ACTION_POST = 'post';
    const ACTION_DELETE = 'delete';
    const ACTION_GET = 'get';

    public function __construct($uri, $klass, $method = null)
    {
        parent::__construct($uri, $klass, $method);
    }

    public function match($uri)
    {
        if (trim($uri) == '/') {
            $uri = '/';
        } else {
            $uri = trim($uri, '/');
        }
        if (strpos($uri, $this->_uri) === 0) {
            $len = strlen($this->_uri);
            $left_str = trim(substr($uri, $len), '/');
            $left = explode('/', $left_str);
            $left_count = count($left);
            $action = $this->_get_action();
            if ($this->_klass) {
                $args = $left;
                switch ($action) {
                case self::ACTION_GET:
                    if ($left_count > 0) {
                        $this->_args[] = $args[0];
                        $this->_method || $this->_method = self::METHOD_GET;
                    } else {
                        $this->_method || $this->_method = self::METHOD_LIST;
                    }
                    break;
                case self::ACTION_POST:
                    $this->_method || $this->_method = self::METHOD_CREATE;
                case self::ACTION_PUT:
                    if ($left_count > 0) {
                        $this->_args[] = $args[0];
                        $this->_method || $this->_method = self::METHOD_UPDATE;
                    }
                case self::ACTION_DELETE:
                    if ($left_count > 0) {
                        $this->_args[] = $args[0];
                        $this->_method || $this->_method = self::METHOD_DELETE;
                    }
                }
            } elseif ($left_count > 0) {
                $tail = array_pop($left);
                $kls = rtrim($this->_prefix, '\\');
                isset($left[0]) && $kls .= '\\' . implode('\\', $left);
                switch ($action) {
                case self::ACTION_GET:
                    if (class_exists($kls)) {
                        $this->_klass = $kls;
                        $this->_args[] = $tail;
                        $this->_method = self::METHOD_GET;
                    } else {
                        $kls .= '\\' . $tail;
                        if (class_exists($kls)) {
                            $this->_klass = $kls;
                            $this->_method = self::METHOD_LIST;
                        }
                    }
                    break;
                case self::ACTION_POST:
                    if (class_exists($kls . '\\' . $tail)) {
                        $this->_klass = $kls . '\\' . $tail;
                        $this->_method = self::METHOD_CREATE;
                    }
                case self::ACTION_PUT:
                    if (class_exists($kls)) {
                        $this->_klass = $kls;
                        $this->_args[] = $tail;
                        $this->_method = self::METHOD_UPDATE;
                    }
                case self::ACTION_DELETE:
                    if (class_exists($kls)) {
                        $this->_klass = $kls;
                        $this->_args[] = $tail;
                        $this->_method = self::METHOD_DELETE;
                    }
                }
            }
            if ($this->_klass === null
                || $this->_method === null
                || !method_exists($this->_klass, $this->_method)
            ) {
                return false;
            }
            return true;
        }
        return false;
    }

    public function exec()
    {
        return call_user_func_array([new $this->_klass, $this->_method], $this->_args);
    }

    protected function _get_action()
    {
        return isset($_REQUEST['_method']) ? $_REQUEST['_method'] : self::ACTION_GET;
    }
}