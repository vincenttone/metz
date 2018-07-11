<?php
namespace Metz\app\metz\route;

use Metz\app\metz\exceptions;

class Restful extends Route
{
    const METHOD_CREATE = 'create';
    const METHOD_UPDATE = 'update';
    const METHOD_DELETE = 'delete';
    const METHOD_GET = 'get';
    const METHOD_INDEX = 'index';

    const METHOD_NEW = 'new';
    const METHOD_MODIFY = 'modify';
    const METHOD_REMOVE = 'remove';
    const METHOD_LIST = 'list';
    const METHOD_SHOW = 'show';

    const ACTION_PATCH = 'patch';
    const ACTION_PUT = 'put';
    const ACTION_POST = 'post';
    const ACTION_DELETE = 'delete';
    const ACTION_GET = 'get';

    protected static $_method_need_args = [
        self::METHOD_GET,
        self::METHOD_UPDATE,
        self::METHOD_DELETE,
    ];

    public function __construct($uri, $klass = null, $method = null)
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
            $left = empty($left_str) ? [] : explode('/', $left_str);
            $left_count = count($left);
            if (empty($this->_prefix) && empty($this->_klass)) {
                $this->_klass = str_replace('/', '\\', $this->_uri);
            }
            if ($this->_klass) {
                $this->_choose_method($left);
            } elseif ($left_count > 0) {
                $this->_choose_method_without_class($left);
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

    protected function _get_action()
    {
        return isset($_REQUEST['_method']) ? $_REQUEST['_method'] : self::ACTION_GET;
    }

    protected function _choose_method($action, $args = [])
    {
        $left_count = count($args);
        switch ($this->_get_action()) {
        case self::ACTION_GET:
            if ($left_count > 0) {
                $this->_args[] = filter_var($args[0], FILTER_SANITIZE_STRING);
                $this->_method || $this->_method = self::METHOD_GET;
            } else {
                $this->_method || $this->_method = self::METHOD_INDEX;
            }
            break;
        case self::ACTION_POST:
            $this->_method || $this->_method = self::METHOD_CREATE;
        case self::ACTION_PUT:
            $this->_method || $this->_method = self::METHOD_UPDATE;
            if ($left_count > 0) {
                $this->_args[] = filter_var($args[0], FILTER_SANITIZE_STRING);
            } else {
                throw new exceptions\db\unexpectedInput('put method without argument ' .  json_encode($args));
            }
        case self::ACTION_DELETE:
            $this->_method || $this->_method = self::METHOD_DELETE;
            if ($left_count > 0) {
                $this->_args[] = filter_var($args[0], FILTER_SANITIZE_STRING);
            } else {
                throw new exceptions\db\unexpectedInput('put method without argument ' .  json_encode($args));
            }
        }
    }

    protected function _choose_method_without_class($action, $args)
    {
        $tail = array_pop($args);
        $kls = rtrim($this->_prefix, '\\');
        isset($args[0]) && $kls .= '\\' . implode('\\', $args);
        switch ($this->_get_action()) {
        case self::ACTION_GET:
            if (class_exists($kls)) {
                $this->_klass = $kls;
                $this->_args[] = filter_var($tail, FILTER_SANITIZE_STRING);
                $this->_method = self::METHOD_GET;
            } else {
                $kls .= '\\' . $tail;
                if (class_exists($kls)) {
                    $this->_klass = $kls;
                    $this->_method = self::METHOD_INDEX;
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
                $this->_args[] = filter_var($tail, FILTER_SANITIZE_STRING);
                $this->_method = self::METHOD_UPDATE;
            }
        case self::ACTION_DELETE:
            if (class_exists($kls)) {
                $this->_klass = $kls;
                $this->_args[] = filter_var($tail, FILTER_SANITIZE_STRING);
                $this->_method = self::METHOD_DELETE;
            }
        }
    }
}