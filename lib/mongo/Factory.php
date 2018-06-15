<?php
namespace Metz\lib\mongo;

class Factory
{
    protected static $_mongos = [];

    /**
     * @param array $config
     * @param bool $hold
     * @return Lib_Mongo_Db
     */
    public static function getMongo($config, $hold = true)
    {
        if (!$hold) {
            return (new Db($config));
        }
        $host = $config[Db::CONFIG_FIELD_HOST];
        $port = $config[Db::CONFIG_FIELD_PORT];
        $key = $host.':'.$port;
        if (empty(self::$_mongos) || !isset(self::$_mongos[$key])) {
            self::$_mongos[$key] = new Db($config);
        }
        return self::$_mongos[$key];
    }

    private function __construct()
    {
    }
    /**
     * 禁用对象克隆
     */
    private function __clone()
    {
        throw new Exception("Could not clone the object from class: " . __CLASS__);
    }
}