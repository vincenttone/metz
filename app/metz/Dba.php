<?php
namespace Metz\app\metz;

class Dba
{
    private static $_instance = null;
    private $_conns = [];

    private function __construct()
    {
    }

    private function __clone()
    {
        throw new \Exception('clone failed!');
    }

    public static function getInstance()
    {
        if (self::$_instance === null) {
            $kls = get_class();
            self::$_instance = new $kls();
        }
        return self::$_instance;
    }

    public static function connection($driver, $ip, $port, $user, $password, $db_name = null, $ext = [])
    {
        return self::getInstance()->get_connection($driver, $ip, $port, $user, $password, $db_name, $ext);
    }

    public function get_connection($driver, $ip, $port, $user, $password, $db_name = null, $ext = [])
    {
        $key = $driver . '::' . $ip . '::' . $port;
        $db_name && $key .= $db_name;
        if (!isset($this->_conns[$key])) {
            $this->_conns[$key] = new db\Connection($driver, $ip, $port, $user, $password, $db_name, $ext);
        }
        return $this->_conns[$key];
    }
}