<?php
namespace Metz\Lib;

class StrHelper
{
    /**
     * str_equal('str1', 'str2', 'str3', ...)
     * 只要有一个和str1相等就返回TRUE
     * @return bool
     */
    static function any_equal()
    {
        $args = func_get_args();
        if (!isset($args[1])) {
            return false;
        }
        if (!isset($args[2])) {
            if (strcmp($args[0], $args[1]) === 0) {
                return true;
            } else {
                return false;
            }
        }
        $str1 = array_shift($args);
        foreach ($args as $_arg) {
            if (strcmp($str1, $_arg) === 0) {
                return true;
            }
        }
        return false;
    }
}