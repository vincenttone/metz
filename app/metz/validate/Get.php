<?php
namespace Metz\app\metz\validate;

class Get extends Input
{
    protected $_key;

    public function __construct($key, $type = self::TYPE_STR, $required = true, $default = null)
    {
        $this->_key = $key;
        $val = isset($_GET[$key]) ? $_GET[$key] : null;
        parent::__construct($val, $type, $required, $default);   
    }
}