<?php
namespace Gaer\validate;

class Request extends Input
{
    public function __construct($key, $type = self::TYPE_STR, $required = true, $default = null)
    {
        $this->_key = $key;
        $val = isset($_REQUEST[$key]) ? $_REQUEST[$key] : null;
        parent::__construct($val, $type, $required, $default);   
    }
}
