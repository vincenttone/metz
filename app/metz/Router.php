<?php
namespace Metz\app\metz;

use Metz\sys\Log;

use Metz\app\metz\exceptions;

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
    function route($url_info)
    {
        return $this->_route_v2($url_info);
        $url_piece = $url_info['piece'];
        $uri = $url_info['uri'];
        $match = [];
        foreach ($this->_configure as $_r) {
            if ($_r->match($uri)) {
                $match[count($_r->get_uri_array())] = $_r;
            }
        }
        if (empty($match)) {
            throw new exceptions\http\NotFound('not configure for uri: [' . $uri . ']');
        } elseif (isset($match[1])) {
            krsort($match);
            $obj = reset($match);
            return $obj->exec();
        } else {
            $obj = reset($match);
            return $obj->exec();
        }
    }

    protected function _route_v2($url_info)
    {
        $url_piece = $url_info['piece'];
        $url_piece_count = count($url_piece);
        $routes = [];
        foreach ($this->_configure as $_r) {
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
                return $_r->exec();
            }
        }
        throw new exceptions\http\NotFound('not configure for uri: [' . $url_info['uri'] . ']');
    }

    protected function _filter_config($piece, $configure)
    {
        $uri = implode('/', $piece);
        $c = 0;
        $r = null;
        foreach ($configure as $_u => $_o) {
            $_s = trim($_u, '/');
            $_len = strlen($_s);
            if (strncmp($uri, $_s, $_len) == 0) {
                if ($_len > $c) {
                    $r = [$_o, [$_u]];
                }
            }
        }
        return $r;
    }

    protected function _up_configure($configure, $pre = '')
    {
        foreach ($configure as $_k => $_val) {
            $_key = rtrim($pre, '/') . '/' . trim($_k, '/');
            if (is_array($_val)) {
                $_r = $this->_up_configure($_val, $_key);
                unset($configure[$_k]);
                $configure = array_merge($_r, $configure);
            } else {
                $configure[$_key] = $_val;
            }
        }
        print_r(array_keys($configure));
        return $configure;
    }
    /**
     * @param array $router
     * @param string $path
     * @return array
     */
    protected function _dispatch_to_method($router, $path)
    {
        if (!isset($router[$path])) {
            return [
                'errno' => Da\Sys_Router::ERRNO_NOT_FOUND,
                'data' => 'path ['.$path.'] not exists.'
            ];
        }
        $rules = $router[$path];
        $count = count($rules);
        $http_method = 'get';
        if ($count > 2) {
            $http_method = $rules[2];
            if (strtolower($http_method) == 'post' && empty($_POST)) {
                return [
                    'errno' => Da\Sys_Router::ERRNO_NOT_FOUND,
                    'data' => 'ONLY SUPPORT POST FOR URL:'.$path.', but got empty Post vars.',
                ];
            }
        }
        $method = array_slice($rules, 0, 2);
        if (!is_callable($method)) {
            return [
                'errno' => Da\Sys_Router::ERRNO_NOT_FOUND,
                'data' => 'method '.json_encode($method). ' not exists',
            ];
        }
        $this->_carry_current_url_path();
        $this->_current_url_info['url_path'] = self::current_url_path();
        $this->_current_url_info['rule'] = $rules;
        foreach($this->_pre_route_hooks as $_hook) {
            if (is_callable($_hook)) {
                call_user_func($_hook);
            }
        }
        try {
            $result = call_user_func($method);
        } catch (Exception $ex) {
            Log::error('Runtime error errno: [%d], msg: [%s]', [$ex->getCode(), $ex->getMessage()]);
            return [
                'errno' => Da\Sys_Router::ERRNO_SERVER_ERR,
                'data' => 'something error!',
            ];
        }
        return ['errno' => 200, 'data' => 'REUQEST OK!'];
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
     * 注册urlpath
     * @param string $path
     * @return bool
     */
    function register_url_path($path)
    {
        if (!isset($path['name'])) {
            return false;
        }
        $_name = $path['name'];
        unset($path['name']);
        if (isset($this->_url_path_list[$_name])) {
            return false;
        }
        $this->_url_path_list[$_name] = $path;
        return true;
    }

    /**
     * @return array
     */
    function get_url_path_list()
    {
        return $this->_url_path_list;
    }

    /**
     * 返回页面的url
     * 建议使用绝对路径 /xxx/yyy/zzz
     * @param string $path
     * @return string
     */
    static function site_url($path = '/')
    {
        if (strpos($path, '/') === 0) {
            $base_path = trim($_SERVER['REQUEST_URI'], '/');
            $url_piece = explode('/', $base_path);
            if (isset($url_piece[0])) {
                $url_base = strpos($url_piece[0], '.php');
                if ($url_base) {
                    $path = $url_piece[0].$path;
                }
            }
        }
        $http_conf = Da\Sys_Config::config('env/http');
        $domain = $http_conf['domain'];
        return 'http://'.$domain.'/'.trim($path, '/');
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
                if (Lib_Helper::str_equal($_p, $url)) {
                    return true;
                }
            }
            return false;
        }
        Lib_Helper::str_equal($path, '/')
            || $path = rtrim($path, '/');
        return Lib_Helper::str_equal($path, $url);
    }

    /**
     * @param string $path
     * @throws Exception
     */
    static function redirect_to($path)
    {
        if (self::is_current_url_path($path)) {
            throw new Exception('No End Loop Redirect!', Const_Err_Request::ERR_NO_END_LOOP);
        }
        $url = self::site_url($path);
        header('Location:'.$url);
        exit;
    }
}