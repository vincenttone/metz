<?php
namespace Gaer\validate;

use Gaer\exceptions;

class Input
{
    const TYPE_RAW = 1;
    const TYPE_BOOL = 2;
    const TYPE_INT = 3;
    const TYPE_FLOAT = 4;
    const TYPE_STR = 5;

    const TYPE_DOMAIN = 101;
    const TYPE_EMAIL = 102;
    const TYPE_IP = 103;
    const TYPE_MAC = 104;
    const TYPE_URL = 105;

    protected static $_validate_map = [
        self::TYPE_BOOL => FILTER_VALIDATE_BOOLEAN,
        self::TYPE_INT => FILTER_VALIDATE_INT,
        self::TYPE_FLOAT => FILTER_VALIDATE_FLOAT,

        self::TYPE_DOMAIN => FILTER_VALIDATE_DOMAIN,
        self::TYPE_EMAIL => FILTER_VALIDATE_EMAIL,
        self::TYPE_IP => FILTER_VALIDATE_IP,
        self::TYPE_MAC => FILTER_VALIDATE_MAC,
        self::TYPE_URL => FILTER_VALIDATE_URL,
    ];

    protected static $_sanitize_map = [
        self::TYPE_FLOAT => FILTER_SANITIZE_NUMBER_FLOAT,
        self::TYPE_INT => FILTER_SANITIZE_NUMBER_INT,
        self::TYPE_STR => FILTER_SANITIZE_STRING,
        self::TYPE_EMAIL => FILTER_SANITIZE_EMAIL,
        self::TYPE_URL => FILTER_SANITIZE_URL,
    ];

    protected $_val = null;
    protected $_type = null;
    protected $_regexp = null;
    protected $_default = null;
    protected $_required = true;

    public function __construct($val, $type = self::TYPE_STR, $required = true, $default = null)
    {
        $this->_val = $val;
        $this->_type = $type;
        $this->_required = $required;
        $this->_default = $default;
    }

    public function set_type($type)
    {
        $this->_type = $type;
        return $this;
    }

    public function set_required($required, $default = null)
    {
        $this->_required = $required;
        $default === null || $this->_default = $default;
        return $this;
    }

    public function set_val($val)
    {
        $this->_val = $val;
        $this->sanitize();
        return $this;
    }

    public function get_val()
    {
        return $this->_val;
    }

    public function set_regex($regex)
    {
        $this->_type = self::TYPE_RAW;
        $this->_regexp = $regex;
        return $this;
    }

    public function validate()
    {
        if (empty($this->_val)) {
            if ($this->_required) {
                throw new exceptions\request\params\MissingParams(
                    'required params, but got: '
                    . json_encode($this->_val)
                );
            } elseif ($this->_default) {
                $this->_val = $this->_default;
                return;
            }
        }
        $match = true;
        if (isset($this->_validate_map[$this->_type])) {
            try {
                $match = $this->_filter_var($this->_validate_map[$this->_type]);
            } catch (\Exception $ex) {
                $match = false;
            }
        } elseif ($this->_regexp) {
            $match = preg_match($this->_regexp, $this->_val) > 0;
        }
        if ($match == false) {
            throw new exceptions\request\params\unexpecteParams(
                'unexpect params format: '
                . json_encode($this->_val)
            );
        }
        return $this;
    }

    public function sanitize()
    {
        if (isset($this->_sanitize_map[$this->_type])) {
            $this->_val = $this->_filter_var($this->_sanitize_map[$this->_type]);
        } else {
            $this->_val = $this->_filter_var(FILTER_SANITIZE_STRING);
        }
        return $this;
    }

    protected function _filter_var($filter, $options = 0)
    {
        if ($options === 0) {
            return filter_var($this->_val, $filter);
        } else {
            return filter_var($this->_val, $filter, $options);
        }
    }
}