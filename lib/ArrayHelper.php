<?php
namespace Metz\lib;

class ArrayHelper
{
    /**
     * @param array $array
     * @param array $sort_key
     * @param bool $asc
     * @param bool $index
     * @return null|int
     */
    static function sort_two_dimension_array(&$array, $sort_key, $asc = true, $index = true)
    {
        $sort_func = function($a, $b) use ($sort_key, $asc) {
            $result = 0;
            if ($a[$sort_key] < $b[$sort_key]) {
                $result =  $asc ? -1 : 1;
            } elseif ($a[$sort_key] > $b[$sort_key]) {
                $result = $asc ? 1 : -1;
            }
            return $result;
        };
        $index ? uasort($array, $sort_func)
            : usort($array, $sort_func);
    }
    /**
     * @param $data
     * @param $key
     * @param string $default
     * @param bool $conv2int
     * @return int|string
     */
    static function get_val($data, $key, $default='', $conv2int=false)
    {
        $val = array_key_exists($key, $data) ? $data[$key] : $default;
        if ($conv2int) {
            return intval($val);
        }
        return $val;
    }

    /**
     * @param $array
     * @param $key
     * @param $default
     */
    static function replace_empty_val(&$array, $key, $default)
    {
        array_key_exists($key ,$array)
            && empty($array[$key])
            && $array[$key] = $default;
    }

    /**
     * @param $array
     * @param $origin_key
     * @param $new_key
     */
    static function update_key(&$array, $origin_key, $new_key)
    {
        if (array_key_exists($origin_key, $array)) {
            $array[$new_key] = $array[$origin_key];
            unset($array[$origin_key]);
        }
    }

    /**
     * @param $array
     * @param string $group_sep
     * @param string $kv_sep
     * @return string
     */
    static function join_key_val($array, $group_sep = ', ', $kv_sep = ': ')
    {
        $arr = [];
        foreach ($array as $_k => $_v) {
            $arr[] = strval($_k).$kv_sep.$_v;
        }
        return implode($group_sep, $arr);
    }

    /**
     * @param $array
     * @param string $group_sep
     * @param string $kv_sep
     * @param string $key_wraper
     * @param string $val_wraper
     * @return string
     */
    static function join_and_wrap_key_val(
        $array,
        $group_sep = ', ',
        $kv_sep = ': ',
        $key_wraper = '',
        $val_wraper = ''
    )
    {
        $arr = [];
        foreach ($array as $_k => $_v) {
            $arr[] = $key_wraper.strval($_k).$key_wraper
                .$kv_sep
                .$val_wraper.$_v.$val_wraper;
        }
        return implode($group_sep, $arr);
    }

    /**
     * @param $delimiter
     * @param $array
     * @param $key
     * @param $default
     * @param null $trim
     * @return array
     */
    static function check_and_explode_val($delimiter, $array, $key, $default, $trim = null)
    {
        $data = isset($array[$key])
            ? (
                $trim === null
                ? $array[$key]
                : trim($array[$key], $trim)
            )
            : null;
        return $data === null
            ? $default
            : explode($delimiter, $data);
    }
    /**
     * @param $array
     * @param $keys
     * @param bool $get_str
     * @return string
     */
    static function filter_by_keys($array, $keys, $get_str = false, $not_exists_callback = null)
    {
        $str = '';
        foreach ($array as $_k => $_v) {
            if (!in_array($_k, $keys)) {
                unset($array[$_k]);
                continue;
            }
            if ($get_str === true) {
                is_string($_v) && $str .= $_k.': '.$_v.' ';
            } elseif (is_string($get_str)) {
                is_string($_v) && $str .= sprintf($get_str, [$_k, $_v]);
            }
        }
        if ($not_exists_callback) {
            $diff_keys = array_diff($keys, array_keys($array));
            foreach ($diff_keys as $_k) {
                $_r = call_user_func($not_exists_callback, $_k);
                if ($get_str) {
                    is_string($_r) && $str .= $_r;
                } else {
                    $array[$_k] = $_r;
                }
            }
        }
        return $get_str ? $str : $array;
    }
    /**
     * @param array $array
     * @param $keys_map
     */
    static function switch_keys(&$array, $keys_map)
    {
        foreach ($keys_map as $_k => $_new_k) {
            if (isset($array[$_k])) {
                $array[$_new_k] = $array[$_k];
                unset($array[$_k]);
            }
        }
    }
}
