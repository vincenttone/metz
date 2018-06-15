<?php
trait Singleton
{
    private static $_instance = null;

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
    
}

trait SingletonWithGetInstance
{
    use Singleton;
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
}