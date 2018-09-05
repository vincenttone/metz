<?php
namespace Gaer;

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
    protected $_url_path_list = [];
    protected $_current_url = '';
    protected $_current_url_path = '';
    protected $_current_url_info = [];
    protected $_pre_route_hooks = [];

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
                $ret = $_r->exec();
                $fmt = RenderEngine::current_format();
                if ($fmt && RenderEngine::is_supporting_format($fmt)) {
                    RenderEngine::output($fmt, $ret);
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
    /**
     * @return string
     */
    protected function _carry_current_url_path()
    {
        $this->_current_url = trim($_SERVER['REQUEST_URI'], '/');
        $this->_current_url_path = $this->get_url_path_from_url($this->_current_url);
        return $this->_current_url_path;
    }

    /**
     * @param string $url
     * @return string
     */
    public function get_url_path_from_url($url)
    {
        $qustion_mark_pos = strpos($url, '?');
        if ($qustion_mark_pos !== false) {
            $url = substr($url, 0, $qustion_mark_pos);
        }
        $url_piece = explode('/', $url);
        $url_base = strpos($url_piece[0], '.php');
        if ($url_base) {
            array_shift($url_piece);
        }
        $path = implode('/', $url_piece);
        $path = '/'.trim($path, '/');
        return $path;
    }

    /**
     * @return string
     */
    public function get_current_url_path()
    {
        return $this->_current_url_path;
    }

    /**
     * @return string
     */
    static function current_url_path()
    {
        $path = self::get_instance()->get_current_url_path();
        return $path;
    }
    /**
     * @return string
     */
    static function current_url()
    {
        return self::get_instance()->_current_url;
    }

    /**
     * @return array
     */
    static function current_url_info()
    {
        return self::get_instance()->_current_url_info;
    }

    /**
     * @return string
     */
    static function current_url_extra_info()
    {
        $url_info = self::current_url_info();
        $perm = isset($url_info['rule'][3])
            ? $url_info['rule'][3]
            : null;
        return $perm;
    }
    /**
     * @param string $path
     * @return bool
     */
    static function is_current_url_path($path = '/')
    {
        if (is_null($path)) {
            return false;
        }
        $url = self::current_url_path();
        if (is_array($path)) {
            foreach ($path as $_p) {
                $_p = rtrim($_p, '/');
                if (strcmp($_p, $url) === 0) {
                    return true;
                }
            }
            return false;
        }
        strcmp($path, '/') === 0
                           || $path = rtrim($path, '/');
        return strcmp($path, $url) === 0;
    }

    /**
     * @param string $path
     * @throws Exception
     */
    static function redirect_to($path)
    {
        if (self::is_current_url_path($path)) {
            throw new exceptions\http\BadRequest('No End Loop Redirect!');
        }
        header('Location: /');
        exit;
    }
}