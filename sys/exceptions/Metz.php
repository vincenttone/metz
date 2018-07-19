<?php
namespace Metz\sys\exceptions;

class Metz extends \Exception
{
    public function __construct($msg = "", $code = 0, $previous = null)
    {
        is_array($msg) && $msg = json_encode($msg);
        parent::__construct($msg, $code, $previous);
    }
}