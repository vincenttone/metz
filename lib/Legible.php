<?php
namespace Metz\Lib;

class Legible
{
    /**
     * @param int $size
     * @return string
     */
    static function format_size($size, $fmt_func = null)
    {
        $get_val = $fmt_func ?? function ($val) {
            return strval(sprintf("%.3f", $val));
        };
        $k_base = 1024 * 1024;
        if ($size < $k_base) {
            if ($size < 1024) {
                return strval($size).'B';
            } else {
                return $get_val($size/$k_base).'KB';
            }
        } else {
            $m_base = $k_base * 1024;
            $g_base = $m_base * 1024;
            if ($size < $m_base) {
                return $get_val($size / $k_base).'MB';
            } elseif ($size < $g_base) {
                return $get_val($size / $m_base).'GB';
            } else {
                return $get_val($size / $g_base).'TB';
            }
        }
    }
    /**
     * @param $time
     * @return string
     */
    static function format_time($time)
    {
        $get_val = function ($val) {
            return strval(sprintf("%.4f", $val));
        };
        $hour_base = 3600;
        if ($time < $hour_base) {
            if ($time < 60) {
                return $get_val($time).'s';
            } else {
                return $get_val($time/60).'min';
            }
        } else {
            $day_base = $hour_base * 24;
            if ($time < $day_base) {
                return $get_val($time / $hour_base).'hour';
            } else {
                return $get_val($time / $day_base).'day';
            }
        }
    }
}