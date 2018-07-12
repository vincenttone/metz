<?php
namespace Metz\app\metz\validate;

class Request extends Input
{
    protected $_key;

    public function __construct($key, $type = self::TYPE_STR, $required = true, $default = null)
    {
        $this->_key = $key;
        $val = isset($_REQUEST[$key]) ? $_REQUEST[$key] : null;
        parent::__construct($val, $type, $required, $default);   
    }
}
