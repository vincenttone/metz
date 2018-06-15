<?php
namespace Metz\lib;
/**
 * redis操作类，仅仅是一层redis操作的封装，为了更好的管理和使用redis.
 * @brief redis操作类
 * @author	vincent
 * @date	Tue Jan  7 20:42:29 2014
 * @version	1.0
 */
class Redis
{
    const RETRY_COUNT = 3;
    protected $_redis = null;
    protected $_ip = null;
    protected $_port = null;
    protected $_unix_sock = null;
    // 在此列表中的动作返回结果会进行检查
    // 如果检查结果不满足回调方案，则进行重试
    // 0为符合则满足，比如mset返回必须是bool
    // 1为不符合则满足
    protected $_retry_action_list = [
        0 => [
            'mset' => 'is_bool',
            'sadd' => 'is_numeric',
            'srem' => 'is_numeric',
            'delete' => 'is_numeric',
        ],
        1 => [
        ],
    ];

    /**
     * @param null $ip
     * @param null $port
     * @param null $unix_sock
     * @throws Exception
     */
    function __construct($ip = null, $port=null, $unix_sock = null)
    {
        if (!empty($ip) && !empty($port)) {
            $this->_ip = $ip;
            $this->_port = $port;
            Lib_Log::debug("Lib redis start to connect redis %s:%s", [strval($ip), strval($port)]);
        } else {
            if (empty($unix_sock)) {
                throw new Exception("Lib redis, empty ip and port, without unixsock addr too.");
            }
            if (file_exists($unix_sock)) {
                $this->_unix_sock = $unix_sock;
                Lib_Log::debug("Lib redis connect to unix sock: %s", $unix_sock);
            } else {
                throw new Exception('Lib redis, no such sock file: '.$unix_sock);
            }
        }
        $this->_create_connection();
    }

    /**
     * @return $this|bool
     */
    protected function _create_connection()
    {
        if (is_null($this->_redis)) {
            $this->_redis = new Redis();
        }
        $connect = $this->_unix_sock === null
            ? $this->_redis->connect($this->_ip, $this->_port)
            : $this->_redis->connect($this->_unix_sock);
        if (!$connect) {
            $this->_redis = null;
            Lib_Log::warn(
                "Redis connecnt faild! host: [%s:%s], unix sock: [%s]",
                [strval($this->_ip), strval($this->_port), strval($this->_unix_sock)]
            );
            return false;
        }
        return $this;
    }

    function close_connection()
    {
        if ($this->_redis) {
            $this->_redis->close();
        }
        $this->_redis = null;
    }

    /**
     * @return bool
     */
    function get_redis()
    {
        return ($this->_redis || null);
    }

    function __destruct()
    {
        $this->close_connection();
    }

    /**
     * 目前的redis不支持ping,true
     * @return bool
     */
    protected function _is_connecting(){
        return true;
        try{
            $status = $this->_redis->ping();
        } catch(Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function _reconnect(){
        if($this->_is_connecting()){
            $this->close_connection();
        }
        if(!$this->_create_connection()){
            return false;
        }
        return true;
    }

    /**
     * @param $callback
     * @return array|null
     */
    protected function _do_action_by_callback($callback)
    {
        $result = null;
        $falid_things = function($result) use ($callback) {
            Lib_Log::warn(
                'REDIS-CALLBACK: '.json_encode($callback)
                .', result: '.json_encode($result)
            );
            $this->_reconnect();
        };
        foreach (range(1, self::RETRY_COUNT) as $_count) {
            try {
                if (!$this->_redis || !$this->_is_connecting()) {
                    $this->_create_connection();
                }
                if (!$this->_redis || !$this->_is_connecting()) {
                    $result = ['errno' => 1, 'data' => 'redis is not connected'];
                } else {
                    $result = ['errno' => 0, 'data' => $callback()];
                    break;
                }
            } catch (RedisException $ex) {
                try {
                    $falid_things([$ex->getCode(), $ex->getMessage()]);
                } catch (RedisException $ex) {
                    Lib_Log::warn(
                        "Redis reconnect failed, err: %s",
                        json_encode($ex)
                    );
                }
                $result = ['errno' => $ex->getCode(), 'data' => $ex->getMessage()];
            }
        }
        return $result;
    }

    /**
     * @param $keys
     * @return array|null
     */
    public function multi_smembers($keys)
    {
        $callback = function() use ($keys) {
            $this->_redis->pipeline();
            foreach ($keys as $_k) {
                $this->_redis->smembers($_k);
            }
            return $this->_redis->exec();
        };
        return $this->_do_action_by_callback($callback);
    }

    /**
     * @param array $datas
     * @return array|null
     */
    public function multi_sadd($datas)
    {
        $callback = function() use ($datas) {
            $this->_redis->pipeline();
            foreach ($datas as $_k => $_d) {
                if (is_array($_d)) {
                    $__data = $_d;
                    array_unshift($__data, $_k);
                    call_user_func_array([$this->_redis, 'sadd'], $__data);
                } else {
                    $this->_redis->sadd($_k, $_d);
                }
            }
            return $this->_redis->exec();
        };
        return $this->_do_action_by_callback($callback);
    }

    /**
     * @param array $datas
     * @return array|null
     */
    public function multi_srem($datas)
    {
        $callback = function() use ($datas) {
            $this->_redis->pipeline();
            foreach ($datas as $__key => $__data) {
                array_unshift($__data, $__key);
                call_user_func_array([$this->_redis, 'srem'], $__data);
            }
            return $this->_redis->exec();
        };
        return $this->_do_action_by_callback($callback);
    }

    /**
     * @param $key
     * @param $counts
     * @return array|null
     */
    public function multi_hincrby($key, $counts)
    {
        $callback = function() use ($key, $counts) {
            $this->_redis->pipeline();
            foreach ($counts as $_k => $_v) {
                $this->_redis->hincrby($key, $_k, intval($_v));
            }
            return $this->_redis->exec();
        };
        return $this->_do_action_by_callback($callback);
    }

    /**
     * @param $key
     * @param $counts
     * @return array|null
     */
    public function multi_hincrbyfloat($key, $counts)
    {
        $callback = function() use ($key, $counts) {
            $this->_redis->pipeline();
            foreach ($counts as $_k => $_v) {
                $this->_redis->hincrbyfloat($key, $_k, floatval($_v));
            }
            return $this->_redis->exec();
        };
        return $this->_do_action_by_callback($callback);
    }

    /**
     * @param $action
     * @param $args
     * @return array|mixed|null
     */
    protected function _do_action($action, $args)
    {
        $result = null;
        $falid_things = function($result) use ($action, $args) {
            Lib_Log::warn(
                'REDIS-ACTION: '.strtoupper($action)
                .' FAILD, args: '.json_encode($args)
                .', result: '.json_encode($result)
            );
            $this->_reconnect();
        };
        foreach (range(1, self::RETRY_COUNT) as $_count) {
            try {
                if (!$this->_redis || !$this->_is_connecting()) {
                    $this->_create_connection();
                }
                if (!$this->_redis || !$this->_is_connecting()) {
                    $result = ['errno' => 1, 'data' => 'redis is not connected'];
                } else {
                    $result = call_user_func_array([$this->_redis, $action], $args);
                    $result = ['errno' => 0, 'data' => $result];
                    break;
                }
            } catch (RedisException $ex) {
                try {
                    $falid_things([$ex->getCode(), $ex->getMessage(), 'retry count: '.$_count]);
                } catch (RedisException $ex) {
                    Lib_Log::warn(
                        "Redis reconnect failed, err: %s",
                        json_encode($ex)
                    );
                }
                $result = ['errno' => $ex->getCode(), 'data' => $ex->getMessage()];
            }
        }
        return $result;
    }

    /**
     * @param $method
     * @param $args
     * @return array|mixed|null
     */
    function __call($method, $args)
    {
        return $this->_do_action($method, $args);
    }
}
