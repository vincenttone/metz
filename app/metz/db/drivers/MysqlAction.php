<?php
namespace Metz\app\metz\db\drivers;

use Metz\app\metz\exceptions;

class MysqlAction
{
    const STATUS_NORMAL = 1;
    const STATUS_EXPECT_UPSERT = 2;
    const STATUS_EXPECT_ON_CONFLICT = 3;

    const INFO_KEY_TBL = 'table';
    const INFO_KEY_FIELDS = 'fields';
    const INFO_KEY_INSERT = 'insert';
    const INFO_KEY_UPDATE = 'update';
    const INFO_KEY_DELETE = 'delete';
    const INFO_KEY_WHERE = 'where';
    const INFO_KEY_COUNT = 'count';
    const INFO_KEY_EXISTS = 'exists';
    const INFO_KEY_IN = 'in';
    const INFO_KEY_LIMIT = 'limit';
    const INFO_KEY_OFFSET = 'offset';
    const INFO_KEY_SORT = 'sort';
    const INFO_KEY_ON_CONFLICT = 'on_conflict';

    const TYPE_INSERT = 1;
    const TYPE_UPDATE = 2;
    const TYPE_UPSERT = 3;
    const TYPE_DELETE = 4;
    const TYPE_SELECT = 5;
    // not support now
    const TYPE_PUSH = 101;
    const TYPE_POP  = 102;

    protected $_type = null;
    protected $_info = [];

    protected $_status = self::STATUS_NORMAL;
    protected $_data = [];

    public function __construct($type = null)
    {
        $type && $this->_set_type($type);
    }

    public function has_type()
    {
        return $this->_type === null;
    }

    public function update_type($type)
    {
        if (($this->_type == self::TYPE_INSERT
             && $type == self::TYPE_UPDATE)
            || ($this->_type == self::TYPE_UPDATE
             && $type == self::TYPE_INSERT)
        ) {
            if ($this->_status == self::STATUS_NORMAL) {
                $this->_status = self::STATUS_EXPECT_ON_CONFLICT;
            } elseif ($this->_status == self::STATUS_EXPECT_UPSERT) {
                $this->_status = self::STATUS_NORMAL;
            } else {
                throw exceptions\unexpectedInput([
                    'err' => 'unexpect action type',
                    'current' => $this->_type,
                    'new' => $type,
                ]);
            }
        } else {
            throw exceptions\unexpectedInput([
                'err' => 'unexpect action type',
                'current' => $this->_type,
                'new' => $type,
            ]);
        }
        return $this;
    }

    protected function _set_type($type)
    {
        $this->_type = $type;
        return $this;
    }

    public function get_type()
    {
        return $this->_type;
    }

    public function add_info($key, $val)
    {
        if (isset($this->_info[$key])
            && is_array($this->_info[$key])
            && is_array($val)
        ) {
            $this->_info[$key] = array_merge($val, $this->_info[$key]);
        } else {
            $this->_info[$key] = $val;
        }
        if ($key == self::INFO_KEY_ON_CONFLICT) {
            if ($this->_status == self::STATUS_NORMAL) {
                $this->_status = self::STATUS_EXPECT_UPSERT;
            } elseif ($this->_status == self::STATUS_EXPECT_ON_CONFLICT) {
                $this->_status = self::STATUS_NORMAL;
            }
        }
        return $this;
    }

    public function get_exec_info()
    {
        $str = null;
        switch($this->_type) {
        case self::TYPE_SELECT:
            $str = 'SELECT ' . $this->_get_fields_str()
                 . ' FROM ' . $this->_get_table();
            $where = $this->_get_where_str();
            empty($where) || $str .= $where;
            $sort = $this->_get_sort_str();
            empty($sort) || $str .= $sort;
            $offset = $this->_get_offset_str();
            empty($offset) || $str .= $offset;
            $limit = $this->_get_limit_str();
            empty($$limit) || $str .= $$limit;
            break;
        case self::TYPE_INSERT:
            $str = 'INSERT INTO ' . $this->_get_table();
            $fields_str = $this->_get_fields_str('');
            empty($fields_str) || $str .= $fields_str;
            $str .= ' VALUES '. $this->_get_insert_str();
            break;
        case self::TYPE_UPDATE:
            $str = 'UPDATE ' . $this->_get_table();
            $str .= ' SET ' . $this->_get_update_str();
            $where = $this->_get_where_str();
            empty($where) || $str .= $where;
            break;
        case self::TYPE_DELETE:
            $str = 'DELETE FROM ' . $this->_get_table();
            $where = $this->_get_where_str();
            empty($where) || $str .= $where;
            break;
        case self::TYPE_UPSERT:
            $str = 'INSERT INTO ' . $this->_get_table();
            $fields_str = $this->_get_fields_str('');
            empty($fields_str) || $str .= $fields_str;
            $str .= $this->_get_insert_str();
            $str .= 'ON DUNPLICATE KEY UPDATE ' . $this->_get_update_str();
            break;
        }
        return [$str, $this->_get_counted_data()];
    }

    protected function _get_fields_str($default = '*')
    {
        $data = null;
        if (isset($this->_info[self::INFO_KEY_FIELDS])
            && count($this->_info[self::INFO_KEY_FIELDS]) > 0
        ) {
            $count = count($this->_info[self::INFO_KEY_FIELDS]);
        } else {
            $count = 0;
        }
        if ($count > 0) {
            $fields = implode(',', array_fill(0, $count, '? '));
            $data = $this->_info[self::INFO_KEY_FIELDS];
        } else {
            $fields = $default;
        }
        if (isset($this->_info[self::INFO_KEY_COUNT])) {
            if ($count == 1) {
                $fields = ' count(?) ';
                $data = $this->_info[self::INFO_KEY_FIELDS];
            } else {
                $fields = ' count(' . $default . ') ';
                $data = null;
            }
        }
        $data == null && array_merge($this->_data, $data);
        return $fields;
    }

    protected function _get_table()
    {
        $this->_data[] = $this->_info[self::INFO_KEY_TBL];
        return ' ? ';
    }

    protected function _get_insert_str()
    {
        if (isset($this->_info[self::INFO_KEY_INSERT][0])) {
            $str_arr = [];
            $data = [];
            $count = count($this->_info[self::INFO_KEY_INSERT][0]);
            foreach ($this->_info[self::INFO_KEY_INSERT] as $_d) {
                if (count($_d) != $count) {
                    throw exceptions\unexpectedInput('unexpect insert data: ' . json_encode($this->_info));
                }
                $data = array_merge($data, $_d);
                $str_arr[] = '(' . implode(', ', array_fill(0, $count, '?')) . ')';
            }
            $this->_data = array_merge($this->_data, $data);
            return implode(', ', $str_arr);
        } else {
            throw exceptions\unexpectedInput('unexpect insert data: ' . json_encode($this->_info));
        }
    }

    protected function _get_update_str()
    {
        $str = '';
        if (isset($this->_info[self::INFO_KEY_UPDATE])) {
            $strs = [];
            foreach ($this->_info[self::INFO_KEY_UPDATE] as $_k => $_v) {
                $strs[] = strval($_k) . ' = ?';
                $this->_data[] = $_v;
            }
            return implode(', ', $strs);
        } else {
            throw exceptions\unexpectedInput('unexpect update data: ' . json_encode($this->_info));
        }
    }

    protected function _get_where_str()
    {
    }
    protected function _get_sort_str()
    {
    }
    protected function _get_offset_str()
    {
    }
    protected function _get_limit_str()
    {
    }
    protected function _get_counted_data()
    {
    }
}