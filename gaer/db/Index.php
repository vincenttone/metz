<?php
namespace Gaer\db;

class Index
{
    const INDEX_TYPE_PRIMARY = 1;
    const INDEX_TYPE_UNIQ = 2;
    const INDEX_TYPE_COMMON = 3;

    protected $_type;
    protected $_name;
    protected $_fields;

    public function __construct($fields, $type = self::INDEX_TYPE_COMMON, $name = null)
    {
        $this->_fields = $fields;
        $this->_type = $type;
        if ($name) {
            $this->_name = $name;
        } else {
            switch ($type) {
            case self::INDEX_TYPE_UNIQ:
                $this->_name = 'uniq';
                break;
            case self::INDEX_TYPE_PRIMARY:
                $this->_name = '';
                break;
            case self::INDEX_TYPE_COMMON:
            default:
                $this->_name = 'idx';
            }
            $this->_name .=  is_array($fields)
                         ? implode('_', $fields)
                         : $fields;
        }
    }
}