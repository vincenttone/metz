<?php
namespace Metz\lib;

class Request
{
    /**
     * @param null $key
     * @return array
     */
    static function post_array_vars($key = null)
    {
        if (empty($_POST)) {
            return [];
        }
        $result = [];
        $filter = FILTER_SANITIZE_STRING;
        if ($key === null) {
            foreach ($_POST as $_k => $_v) {
                if (is_array($_v)) {
                    $result[$_k] = [];
                    foreach ($_v as $__k => $__v) {
                        $result[$_k][$__k] = filter_var($__v, $filter);
                    }
                } else {
                    $result[$_k] = filter_var($_v, $filter);
                }
            }
        } else {
            if (isset($_POST[$key])) {
                foreach ($_POST[$key] as $_k => $_v) {
                    if (is_array($_v)) {
                        $result[$_k] = [];
                        foreach ($_v as $__k => $__v) {
                            $result[$_k][$__k] = filter_var($__v, $filter);
                        }
                    } else {
                        $result[$_k] = filter_var($_v, $filter);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @return array
     */
    static function post_vars()
    {
        if (empty($_POST)) {
            return [];
        }
        $keys = array_keys($_POST);
        $filter = FILTER_SANITIZE_STRING;
        $filters = array_fill_keys($keys,  $filter);
        return filter_input_array(INPUT_POST, $filters);
    }

    /**
     * @return array
     */
    static function get_vars()
    {
        if (empty($_GET)) {
            return [];
        }
        $keys = array_keys($_GET);
        $filter = FILTER_SANITIZE_STRING;
        $filters = array_fill_keys($keys,  $filter);
        return filter_input_array(INPUT_GET, $filters);
    }

    /**
     * @param $key
     * @return array
     */
    static function get_var($key)
    {
        return filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
    }

    /**
     * @param $key
     * @param int $min
     * @param int $max
     * @return mixed
     */
    static function get_int($key, $min = 0, $max = 0)
    {
        $option = null;
        $max > $min && $option = [
            'min_range'=> $min, 
            'max_range' => $max, 
        ];
        if (empty($option)) {
            return filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
        } else {
            return filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT, $option);
        }
    }

    /**
     * @param $key
     * @return mixed
     */
    static function post_var($key)
    {
        return filter_input(INPUT_POST, $key, FILTER_SANITIZE_STRING);
    }

    /**
     * @param $key
     * @param int $min
     * @param int $max
     * @return mixed
     */
    static function post_int_var($key, $min = 0, $max = 0)
    {
        $option = null;
        $max > $min && $option = [
            'min_range'=> $min, 
            'max_range' => $max, 
        ];
        if (empty($option)) {
            return filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
        } else {
            return filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT, $option);
        }
    }

    /**
     * @param $key
     * @param null $val
     * @return null
     */
    static function flash($key, $val = null)
    {
        if (is_null($val)) {
            if (isset($_SESSION[$key])) {
                $result = $_SESSION[$key];
                unset($_SESSION[$key]);
                return $result;
            } else {
                return null;
            }
        } else {
            $_SESSION[$key] = $val;
            return $val;
        }
    }
}