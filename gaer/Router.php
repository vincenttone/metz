<?php
namespace Gaer;

use Gaer\RenderEngine;
use Gaer\exceptions;

class Router
{
    const TYPE_MVC = 1;
    const TYPE_RESTFUL = 2;

    const MODE_MULT = 1;
    const MODE_SINGLE = 2;

    protected static $_instance = null;

    protected $_configure = [];
    protected $_router = [];

    // uri
    protected $_user;
    protected $_pass;
    protected $_host;
    protected $_path;
    protected $_scheme;
    protected $_query;
    protected $_fragment;
    // headers
    protected $_referer;
    
    protected $_pre_route_hooks = [];

    protected function __construct()
    {
        $this->_analyze_current_url();
        $this->_analyze_headers();
    }
    /**
     * Forbid to clone the object
     */
    protected function __clone()
    {
        throw new \Exception("Could not clone the object from class: ".__CLASS__);
    }
    /**
     * @return array the instance of the singleton
     */
    static function get_instance()
    {
        if (is_null(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class;
        }
        return self::$_instance;
    }

    static function expected_content_type()
    {
        $format_str = isset($_SERVER['HTTP_ACCEPT']) ? strtolower($_SERVER['HTTP_ACCEPT']) : 'text/html';
        $format = RenderEngine::content_type_str($format_str);
        return $format ?? RenderEngine::TYPE_HTML;
    }

    public function load_configure($configure)
    {
        $this->_configure = $configure;
        return $this;
    }
    /**
     * @param array $url_piece
     * @return array
     * @throws Exception
     */
    public function route($url_info)
    {
        $url_piece = $url_info['piece'];
        $url_piece_count = count($url_piece);
        $routes = [];
        foreach ($this->_configure as $_r) {
            if (!isset($url_piece[0])) {
                if ($_r->get_uri() == '/') {
                    $routes[0] = $_r;
                }
                continue;
            }
            $arr = $_r->get_uri_array();
            $_c = count($arr);
            if ($_c > $url_piece_count) continue;
            $pre_match = false;
            for ($__i = 0; $__i < $_c; $__i++) {
                $__kwd = isset($arr[$__i][0]) ? $arr[$__i] : '/';
                if ($url_piece[$__i] != $__kwd) {
                    $pre_match = false;
                    break;
                }
                $pre_match = true;
            }
            $pre_match && $routes[$_c] = $_r;
        }
        krsort($routes);
        foreach ($routes as $_r) {
            if ($_r->match($url_info['uri'])) {
                $fmt = RenderEngine::current_format();
                RenderEngine::format($fmt);
                $ret = $_r->exec();
                if ($ret) {
                    RenderEngine::output($ret);
                }
                return $ret;
            }
        }
        throw new exceptions\http\NotFound('not configure for uri: [' . $url_info['uri'] . ']');
    }
    /**
     * @param array $hook
     * @return $this
     */
    function register_pre_router_hook($hook)
    {
        $this->_pre_route_hooks[] = $hook;
        return $this;
    }

    protected function _analyze_headers()
    {
        $this->_referer = $_SERVER['HTTP_REFERER'] ?? '';
        return $this;
    }
    /**
     * @return string
     */
    protected function _analyze_current_url()
    {
        $this->_url = trim($_SERVER['REQUEST_URI'], '/');
        $uri = parse_url($this->_url);
        $this->_user = $uri['user'] ?? '';
        $this->_pass = $uri['pass'] ?? '';
        $this->_host = $uri['host'] ?? $_SERVER['HTTP_HOST'] ?? '';
        $this->_path = isset($uri['path']) ? trim($uri['path'], '/') : '';
        $this->_scheme = $uri['scheme'] ?? 'http';
        $this->_query = $uri['query'] ?? '';
        $this->_fragment = $uri['fragment'] ?? '';
        return $this;
    }

    public function get_host()
    {
        return empty($this->_host) ? '/' : $this->_scheme . '://' . $this->_host;
    }

    public function get_query_array()
    {
        return self::convert_query_to_array($this->_query);
    }

    public function get_url($suffix = '')
    {
        $qa = $this->get_query_array();
        $sa = is_array($suffix) ? $suffix : self::convert_query_to_array($suffix);
        $qa = array_merge($qa, $sa);
        return empty($qa) ? $this->get_host() : $this->get_host() . '?' . self::convert_array_to_query($qa);
    }
    /**
     * @return string
     */
    static function host()
    {
        return self::get_instance()->get_host();
    }

    static function current_url($suffix = '')
    {
        return self::get_instance()->get_url($suffix);
    }
    /**
     * @param string $path
     * @return bool
     */
    public function is_current_url_path($path = '/')
    {
        if (is_null($path)) {
            return false;
        }
        return strcmp(trim($path, '/'), $this->_path) === 0;
    }

    public function get_referer()
    {
        return $this->_referer;
    }

    public static function referer()
    {
        return self::get_instance()->get_referer();
    }
    /**
     * @param string $path
     * @throws Exception
     */
    static function redirect_to($path, $data = [])
    {
        /*
        if (self::get_instance()->is_current_url_path($path)) {
            throw new exceptions\http\BadRequest('No End Loop Redirect!');
        }
        */
        header('Location: ' . $path . '?' . http_build_query($data)) . ' &from=' . self::current_url();
        exit;
    }

    static function redirect_to_pre_url($data)
    {
        $referer = self::referer();
        if (isset($referer[0])) {
            $u = parse_url($referer);
            $path = $u['path'] ?? '/';
            self::redirect_to($path, $data);
        } else {
            self::redirect_to('/', $data);
        }
    }

    public static function convert_query_to_array($query)
    {
        if (empty($query)) {
            return [];
        }
        $arr = [];
        $q = explode('&', $query);
        foreach ($q as $_q) {
            $_p = explode('=', $_q);
            isset($_p[1]) && $arr[$_p[0]] = $_p[1];
        }
        return $arr;
    }

    public static function convert_array_to_query($arr)
    {
        $qa = [];
        foreach ($arr as $_k => $_v) {
            $qa[] = urlencode($_k) . '=' . urlencode($_v);
        }
        return implode('&', $qa);
    }
}