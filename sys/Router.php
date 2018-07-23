<?php
namespace Metz\sys;

class Router
{
    const ERRNO_OK = 200;
    const ERRNO_FORBIDDEN = 403;
    const ERRNO_NOT_FOUND = 404;
    const ERRNO_SERVER_ERR = 500;

    private static $_instance = null;
    protected $_router = [__CLASS__, '_default_router'];
    protected $_ex_handler = null;

    private function __construct()
    {
    }
    /**
     * Forbid to clone the object
     */
    private function __clone()
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
    /**
     * @return array The return value should include 'errno' and 'data'
     */
    function parse_url()
    {
        $request_uri = trim($_SERVER['REQUEST_URI']);
        $request_uri = trim($request_uri, '/');
        /* parse request uri start
         * -----------------------------------
         */
        $p           = strpos($request_uri, '?');
        $request_uri = $p !== false ? substr($request_uri, 0, $p) : $request_uri;
        // security request uri filter
        if (preg_match('/(\.\.|\"|\'|<|>)/', $request_uri)) {
            return array('errno' => self::ERRNO_FORBIDDEN, 'data' => "permission denied.");
        }
        // get display format
        $url_piece = empty($request_uri) ? [] : explode('/', $request_uri);
        $display = '';
        $file = '';
        if (isset($url_piece[0])) {
            if (($p = strrpos($url_piece[0], '.')) !== false) {
                $tail = substr($url_piece[0], $p + 1);
                if(preg_match('/^[a-zA-Z0-9]+$/', $tail)) {
                    $display  = $tail;
                    $file = array_shift($url_piece);
                }
            }
        }
        return [
            'errno' => self::ERRNO_OK,
            'data' => [
                'file' => $file,
                'uri' => isset($url_piece[0]) ? implode('/', $url_piece) : '/',
                'piece' => $url_piece,
                'suffix' => $display,
            ]
        ];
    }

    /**
     * @param array
     * @return array
     */
    function register_router($router)
    {
        if (is_callable($router)) {
            $this->_router = $router;
        }
        return $this;
    }

    function register_exception_handler($handler)
    {
        if (is_callable($handler)) {
            $this->_ex_handler = $handler;
        }
        return $this;
    }

    /**
     * @return array The return value should include 'errno' and 'data'
     */
    function dispatch()
    {
        $parse_url = $this->parse_url();
        if ($parse_url['errno'] != self::ERRNO_OK) {
            return $parse_url;
        }
        $url_piece = $parse_url['data'];
        try {
            $router = call_user_func($this->_router, $url_piece);
        } catch (\Exception $ex) {
            if ($this->_ex_handler !== null && is_callable($this->_ex_handler)) {
                return call_user_func($this->_ex_handler, $ex);
            }
            throw $ex;
        }
        return $router;
    }

    /**
     * @param array
     * @return bool
     */
    private function _default_router($url_piece)
    {
        $data = '<!DOCTYPE html><head></head><body>';
        $data .= '<pre>';
        $data .= '--------------------------------'.PHP_EOL;
        $data .= ' '.implode('/',$url_piece['piece']).PHP_EOL;
        $data .= '--------------------------------'.PHP_EOL;
        $data .= ' Welcome here! '.PHP_EOL;
        $data .= ' Please set you own route.'.PHP_EOL;
        $data .= ' Version: ' . Constant::version_str() . PHP_EOL;
        $data .= '--------------------------------'.PHP_EOL;
        $data .= '</pre>';
        $data .= '</body></html>';
        echo $data;
        return true;
    }
}