<?php
namespace Metz\app\metz\route;

use Metz\app\metz\exceptions;

abstract class Route
{
    public abstract function exec();
    public abstract function match($uri);

    protected $_prefix = '';
    protected $_uri = null;
    protected $_uri_array = null;
    protected $_klass = null;
    protected $_method = null;
    protected $_args = [];

    public function __construct($uri, $klass = null, $method = null)
    {
        $this->_set_uri($uri);
        if ($klass) {
            if (class_exists($klass)) {
                $this->_klass = $klass;
            } else {
                $this->_prefix = $klass;
            }
        }
        $this->_method = $method;
    }

    public function get_uri()
    {
        return $this->_uri;
    }

    public function get_uri_array()
    {
        return $this->_uri_array;
    }

    public function get_class()
    {
        return $this->_klass;
    }

    public function get_method()
    {
        return $this->_method;
    }

    public function set_class($klass)
    {
        return $this->_set_class($klass);
    }

    public function set_method($method)
    {
        $this->_method = $method;
        return $this;
    }

    protected function _set_class($klass)
    {
        if ($klass === null || !class_exists($klass)) {
            throw new exceptions\http\NotFound(
                'route configure error: ' . $this->_uri
                . ' has no expect class: ' . var_export($klass)
            );
        }
        $this->_klass = $klass;
        return $this;
    }

    protected function _set_uri($uri)
    {
        $this->_uri = trim($uri) == '/' ? '/' : trim($uri, '/');
        $this->_uri_array = explode('/', trim($this->_uri, '/'));
        return $this;
    }
}