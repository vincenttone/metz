<?php
namespace Gaer\validate;

class Post extends Input
{
    protected $_key;

    public function __construct($key, $type = self::TYPE_STR, $required = true, $default = null)
    {
        $this->_key = $key;
        $val = isset($_POST[$key]) ? $_POST[$key] : null;
        parent::__construct($val, $type, $required, $default);   
    }
}