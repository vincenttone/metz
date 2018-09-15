<?php
namespace Gaer;

use Gaer\validate\Input;
use Gaer\validate\Post as VaPost;
use Gaer\validate\Get as VaGet;
use Gaer\validate\Request as VaReq;

class RequestHandler
{
    protected static $_instance = null;

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
    static function handler()
    {
        if (is_null(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class;
        }
        return self::$_instance;
    }

    static function post($key, $type = Input::TYPE_STR, $required = true, $default = null)
    {
        return self::handler()->post_var($key, $type, $required, $default);
    }

    static function get($key, $type = Input::TYPE_STR, $required = true, $default = null)
    {
        return self::handler()->get_var($key, $type, $required, $default);
    }

    static function request($key, $type = Input::TYPE_STR, $required = true, $default = null)
    {
        return self::handler()->request_var($key, $type, $required, $default);
    }

    static function post_vars($keys = null)
    {
        return self::handler()->_fetch_vars($keys, $_POST);
    }

    static function get_vars($keys = null)
    {
        return self::handler()->_fetch_vars($keys, $_GET);
    }

    static function request_vars($keys = null)
    {
        return self::handler()->_fetch_vars($keys, $_REQUEST);
    }

    public function post_var($key, $type = Input::TYPE_STR, $required = true, $default = null)
    {
        $r = new VaPost($key, $type, $required, $default);
        return $r->validate()->sanitize()->get_val();
    }

    public function get_var($key, $type = Input::TYPE_STR, $required = true, $default = null)
    {
        $r = new VaGet($key, $type, $required, $default);
        return $r->validate()->sanitize()->get_val();
    }

    public function request_var($key, $type = Input::TYPE_STR, $required = true, $default = null)
    {
        $r = new VaReq($key, $type, $required, $default);
        return $r->validate()->sanitize()->get_val();
    }
    /**
     * [k => [required, default]]
     */
    protected function _fetch_vars($keys, array $src)
    {
        $result = [];
        $input = new Input(null);
        foreach ($src as $_k => $_v) {
            if (is_array($keys)) {
                if (isset($keys[$_k])) {
                    if (is_array($keys[$_v])) {
                        isset($keys[$_v][0]) && $input->set_type($_v[0]);
                        if (isset($keys[$_v][2])) {
                            $input->set_required($_v[1], $_v[2]);
                        } elseif (isset($keys[$_v][1])) {
                            $input->set_required($_v[1]);
                        }
                    } else {
                        $input->set_type($_k);
                    }
                } else {
                    continue;
                }
            } else {
                $input->set_type(Input::TYPE_STR)->set_required(false);
            }
            $result[$_k] = $input->set_key($_k)
                         ->set_val($_v)
                         ->validate()
                         ->sanitize()
                         ->get_val();
        }
        return $result;
    }
}