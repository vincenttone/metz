<?php
namespace Gaer\route;

use Gaer\exceptions\http\Http as HttpEx;

class ExceptionHandler
{
    public static function process($ex)
    {
        if ($ex instanceof HttpEx) {
            header('HTTP/1.1 ' . $ex->getCode() . ' ' . $ex->getResponseMsg());
            exit();
        } elseif ($ex instanceof \Exception) {
            header('HTTP/1.1 500 somethign wrong happend.');
        } else {
            header('HTTP/1.1 500 somethign wrong happend.');
        }
    }
}