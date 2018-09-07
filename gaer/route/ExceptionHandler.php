<?php
namespace Gaer\route;

use Gaer\Router;
use Gaer\exceptions\http\Http as HttpEx;
use Gaer\exceptions\Base as BaseEx;
use Gaer\Monitor;
use Gaer\RenderEngine;

class ExceptionHandler
{
    public static function process($ex)
    {
        if ($ex instanceof HttpEx) {
            header('HTTP/1.1 ' . $ex->getCode() . ' ' . $ex->getResponseMsg());
            Monitor::error($ex);
        } elseif ($ex instanceof BaseEx) {
            $current_fmt = RenderEngine::current_format();
            $data = ['code' => $ex->getCode(), 'message' => $ex->getResponseMsg()];
            switch ($current_fmt) {
            case RenderEngine::TYPE_JSON:
            case RenderEngine::TYPE_XML:
                RenderEngine::output($data);
                break;
            default:
                Router::redirect_to_pre_url($data);
            }
            Monitor::error('handle exception: ' . json_encode($ex));
        } elseif ($ex instanceof \Exception) {
            header('HTTP/1.1 500 somethign wrong happend.');
            Monitor::error('handle exception, errno: ' . $ex->getCode() . ' msg: ' . $ex->getMessage());
        } else {
            header('HTTP/1.1 500 somethign wrong happend.');
            Monitor::error('something wrong happend!');
        }
        exit();
    }
}