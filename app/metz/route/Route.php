<?php
namespace Metz\app\metz\route;

use Metz\app\metz\exceptions;

class Route
{
    const TYPE_MVC = 1;
    const TYPE_RESTFUL = 2;

    const RESTFUL_METHOD_CREATE = 'create';
    const RESTFUL_METHOD_UPDATE = 'update';
    const RESTFUL_METHOD_DELETE = 'delete';
    const RESTFUL_METHOD_GET = 'get';
    const RESTFUL_METHOD_LIST = 'list';

    const RESTFUL_ACTION_PATCH = 'patch';
    const RESTFUL_ACTION_PUT = 'put';
    const RESTFUL_ACTION_POST = 'post';
    const RESTFUL_ACTION_DELETE = 'delete';
    const RESTFUL_ACTION_GET = 'get';

    protected $_klass = null;
    protected $_method = null;
    protected $_args = [];
    protected $_type = null;
    protected $_uri = null;

    public function __construct($klass, $method = null, $type = self::TYPE_MVC)
    {
        $this->set_class($klass);
        $this->_method = $method;
        $this->_type = $type;
    }

    public function set_class($klass)
    {
        if ($klass !== null && !class_exists($klass)) {
            throw new exceptions\http\NotFound('route configure error');
        }
        $this->_klass = $klass;
        return $this;
    }

    public function set_uri($uri)
    {
        $this->_uri = trim($uri, '/');
        return $this;
    }

    public function exec()
    {
        $args = func_get_args();
        $prefix = '\\Metz\\app\\';
        if ($this->_klass === null) {
            $piece = explode('/', $this->_uri);
            $piece_count = count($piece);
            $action = isset($_REQUEST['_method']) ?? self::RESTFUL_ACTION_GET;
            if (empty($this->_uri)) {
                $this->_klass = $prefix . 'Index';
            } elseif ($piece_count == 1) {
                $this->_klass = $prefix . '\\' . $this->_uri;
                if ($this->_type == self::TYPE_MVC) {
                    $this->_method = 'index';
                } else {
                    switch ($action) {
                    case self::RESTFUL_ACTION_GET:
                        $this->_method = self::RESTFUL_METHOD_LIST;
                        break;
                    case self::RESTFUL_ACTION_POST:
                        $this->_method = self::RESTFUL_METHOD_CREATE;
                        break;
                    default:
                        throw new exceptions\http\NotFound('no rules for: ' . $this->_uri);
                    }
                }
            } elseif ($this->_type == self::TYPE_MVC) {
                $method = array_pop($piece);
                $klass = $prefix . implode('\\', $piece);
                if (class_exists($klass)) {
                    $this->_klass = $klass;
                    $this->_method = $method;
                } elseif ($method && class_exists($klass . '\\' . $method)) {
                    $this->_klass = $klass . '\\' . $method;
                } else {
                    throw new exceptions\http\NotFound('no rules for: ' . $this->_uri);
                }
            } elseif ($this->_type == self::TYPE_RESTFUL) {
                switch ($action) {
                case self::RESTFUL_ACTION_GET:
                    $id = array_pop($piece);
                    $klass = $prefix . implode('\\', $piece);
                    if (class_exists($klass)) {
                        $this->_klass = $klass;
                        $this->_method = self::RESTFUL_METHOD_GET;
                        $this->_args[] = $id;
                    } elseif (class_exists($klass . '\\' . $id)) {
                        $this->_klass = $klass . '\\' . $id;
                        $this->_method = self::RESTFUL_METHOD_LIST;
                    }
                    break;
                case self::RESTFUL_ACTION_PUT:
                    $this->args[] = array_pop($piece);
                    $this->_klass = $prefix . implode('\\', $piece);
                    $this->_method = self::RESTFUL_METHOD_UPDATE;
                    break;
                case self::RESTFUL_ACTION_POST:
                    $this->_klass = $prefix . implode('\\', $piece);
                    $this->_method = self::RESTFUL_METHOD_CREATE;
                    break;
                case self::RESTFUL_ACTION_DELETE:
                    $this->args[] = array_pop($piece);
                    $this->_klass = $prefix . implode('\\', $piece);
                    $this->_method = self::RESTFUL_METHOD_DELETE;
                    break;
                default:
                    throw new exceptions\http\NotFound('no rules for: ' . $this->_uri);
                }
            }
            if ($this->_type == self::TYPE_MVC && $this->_method === null) {
                $this->_method = 'index';
            }
        }
        if (!$this->_klass || !class_exists($this->_klass)) {
            throw new exceptions\http\NotFound(
                'no rules for uri: ' . $this->_uri
                . ', expect method: ' . var_export($this->_klass, true)
                . '::' . var_export($this->_method, true)
            );
        }
        $obj = new $this->_klass;
        if (!method_exists($obj, $this->_method)) {
            throw new exceptions\http\NotFound(
                'no method '. $this->_klass . '::' . $this->_method
                . ' for uri: ' . $this->_uri
            );
        }
        return call_user_func([$obj, $this->_method]);
    }
}