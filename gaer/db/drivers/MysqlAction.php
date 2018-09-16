<?php
namespace Gaer\db\drivers;

use Gaer\exceptions;

class MysqlAction
{
    const STATUS_NORMAL = 1;
    /*
    const STATUS_READY = 2;
    const STATUS_EXPECT_INSERT = 3;
    const STATUS_EXPECT_UPDATE = 4;
    */
    const STATUS_EXPECT_UPSERT = 5;
    const STATUS_EXPECT_ON_CONFLICT = 6;

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

    const EXTRA_TYPE_NONE = 0;
    const EXTRA_TYPE_COUNT = 1;

    protected $_type = null;
    protected $_extra_type = self::EXTRA_TYPE_NONE;
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

    public function is_ready()
    {
        return $this->_status == self::STATUS_NORMAL;
    }

    public function update_type($type)
    {
        if ($type === null) {
            return $this;
        }
        if ($this->_type === null) {
            $this->_type = $type;
        } elseif (($this->_type == self::TYPE_INSERT
             && $type == self::TYPE_UPDATE)
            || ($this->_type == self::TYPE_UPDATE
             && $type == self::TYPE_INSERT)
        ) {
            if ($this->_status == self::STATUS_NORMAL) {
                $this->_status = self::STATUS_EXPECT_ON_CONFLICT;
            } elseif ($this->_status == self::STATUS_EXPECT_UPSERT) {
                $this->_status = self::STATUS_NORMAL;
                $this->_type = self::TYPE_UPSERT;
            } else {
                throw new exceptions\db\UnexpectedInput([
                    'err' => 'unexpect action type',
                    'current' => $this->_type,
                    'new' => $type,
                ]);
            }
        } else {
            throw new exceptions\db\UnexpectedInput([
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
            
            $limit = $this->_get_limit_str();
            empty($limit) || $str .= $limit;

            $offset = $this->_get_offset_str();
            empty($offset) || $str .= $offset;
            break;
        case self::TYPE_INSERT:
            $str = 'INSERT INTO ' . $this->_get_table();
            $fields_str = $this->_get_fields_str('');
            empty($fields_str) || $str .=  ' (' . $fields_str . ') ';
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
            $str .= ' ON DUNPLICATE KEY UPDATE ' . $this->_get_update_str();
            break;
        }
        return [
            'type' => $this->_type,
            'extra_type' => $this->_extra_type,
            'prepare_str' => $str,
            'data' => $this->_get_counted_data(),
        ];
    }

    public function set_table($table)
    {
        $this->_info[self::INFO_KEY_TBL] = $table;
        return $this;
    }

    public function set_fields($fields)
    {
        $this->_info[self::INFO_KEY_FIELDS] = $fields;
        return $this;
    }

    public function where($conds)
    {
        $this->_info[self::INFO_KEY_WHERE] = $conds;
        return $this;
    }

    public function count($fields = null)
    {
        $fields && $this->set_fields($fields);
        $this->_info[self::INFO_KEY_COUNT] = 1;
        return $this;
    }

    public function exists($fields = null)
    {
        $fields && $this->set_fields($fields);
        $this->_info[self::INFO_KEY_EXISTS] = 1;
        return $this;
    }

    public function limit($count)
    {
        $count && $this->_info[self::INFO_KEY_LIMIT] = $count;
        return $this;
    }

    public function offset($count)
    {
        $count && $this->_info[self::INFO_KEY_OFFSET] = $count;
        return $this;
    }

    public function sort($sort)
    {
        $this->_info[self::INFO_KEY_SORT] = $sort;
        return $this;
    }

    public function insert($data)
    {
        $first = reset($data);
        if (is_array($first)) {
            isset($first[0]) || $this->set_fields(array_keys($first));
            $this->_info[self::INFO_KEY_INSERT] = empty($this->_info[self::INFO_KEY_INSERT])
                                                ? $data
                                                : array_merge($this->_info[self::INFO_KEY_INSERT], $data);
        } else {
            isset($data[0])  || $this->set_fields(array_keys($data));
            if (empty($this->_info[self::INFO_KEY_INSERT])) {
                $this->_info[self::INFO_KEY_INSERT] = [$data];
            } else {
                $this->_info[self::INFO_KEY_INSERT][] = $data;
            }
        }
        return $this;
    }

    public function update($data)
    {
        $this->_info[self::INFO_KEY_UPDATE] = empty($this->_info[self::INFO_KEY_UPDATE])
                                            ? $data
                                            : array_merge($this->_info[self::INFO_KEY_UPDATE], $data);
        return $this;
    }

    protected function _get_fields_str($default = '*')
    {
        $data = null;
        if (isset($this->_info[self::INFO_KEY_FIELDS])
            && count($this->_info[self::INFO_KEY_FIELDS]) > 0
        ) {
            if (isset($this->_info[self::INFO_KEY_COUNT])) {
                $fields = 'count(' . reset($this->_info[self::INFO_KEY_FIELDS]) . ')';
                $this->_extra_type = self::EXTRA_TYPE_COUNT;
            } else {
                $fields = implode(', ', $this->_info[self::INFO_KEY_FIELDS]);
            }
        } else {
            if (isset($this->_info[self::INFO_KEY_COUNT])) {
                $fields = ' count(' . $default . ') ';
                $this->_extra_type = self::EXTRA_TYPE_COUNT;
            } else {
                $fields = $default;
            }
        }
        return ' ' . $fields . ' ';
    }

    protected function _get_table()
    {
        return ' `' . $this->_info[self::INFO_KEY_TBL] . '` ';
    }

    protected function _get_insert_str()
    {
        if (isset($this->_info[self::INFO_KEY_INSERT][0]) && !empty($this->_info[self::INFO_KEY_INSERT][0])) {
            $str_arr = [];
            $data = [];
            $count = count($this->_info[self::INFO_KEY_INSERT][0]);
            foreach ($this->_info[self::INFO_KEY_INSERT] as $_d) {
                if (count($_d) != $count) {
                    throw new exceptions\db\UnexpectedInput('unexpect insert data: ' . json_encode($this->_info));
                }
                $data = array_merge($data, array_values($_d));
                $str_arr[] = '(' . implode(', ', array_fill(0, $count, '?')) . ')';
            }
            $this->_data = array_merge($this->_data, $data);
            return implode(', ', $str_arr);
        } else {
            throw new exceptions\db\UnexpectedInput('unexpect insert data: ' . json_encode($this->_info));
        }
    }

    protected function _get_update_str()
    {
        $str = '';
        if (isset($this->_info[self::INFO_KEY_UPDATE])) {
            $strs = [];
            foreach ($this->_info[self::INFO_KEY_UPDATE] as $_k => $_v) {
                $strs[] = ' `' . strval($_k) . '` = ? ';
                $this->_data[] = $_v;
            }
            return implode(', ', $strs);
        } else {
            throw new exceptions\db\UnexpectedInput('unexpect update data: ' . json_encode($this->_info));
        }
    }

    protected function _get_where_str()
    {
        $str = ' WHERE ';
        $str_arr = [];
        $data = [];
        foreach ($this->_info[self::INFO_KEY_WHERE] as $_k => $_v) {
            if (is_array($_v)) {
                foreach ($_v as $__k => $__v) {
                    switch(trim($__k)) {
                    case '=':
                    case '>':
                    case '<':
                    case '>=':
                    case '<=':
                    case 'like':
                        $str_arr[] = $_k . ' ' . $__k . ' ?';
                        $data[] = $__v;
                        break;
                    case 'in':
                        if (!is_array($__v)) {
                            throw new exceptions\db\UnexpectedInput(
                                'unexpect where in data: ' . json_encode($this->_info[self::INFO_KEY_WHERE])
                            );
                        }
                        $str_arr[] = '(' . implode(', ', array_fill(0, count($__v), '?')) . ')';
                        $data = array_merge($data, $__v);
                        break;
                    }
                }
            } else {
                $str_arr[] = $_k . ' = ' . ' ?';
                $data[] = $_v;
            }
        }
        if (empty($str_arr)) {
            $str = '';
        } else {
            $str .= implode(' AND ', $str_arr);
            $this->_data = array_merge($this->_data, $data);
        }
        return $str;
    }

    protected function _get_sort_str()
    {
        if (isset($this->_info[self::INFO_KEY_SORT])
            && is_array($this->_info[self::INFO_KEY_SORT])
        ) {
            $str = ' ORDER BY ';
            $arr = [];
            foreach ($this->_info[self::INFO_KEY_SORT] as $_f => $_d) {
                $arr[] = $_f . ' ' . $_d;
            }
            if (empty($arr)) {
                $str = '';
            } else {
                $str .= implode(', ', $arr);
            }
            return $str;
        }
        return '';
    }

    protected function _get_offset_str()
    {
        return isset($this->_info[self::INFO_KEY_OFFSET]) && $this->_info[self::INFO_KEY_OFFSET] > 0
            ? ' OFFSET ' . $this->_info[self::INFO_KEY_OFFSET]
            : '';
    }

    protected function _get_limit_str()
    {
        return isset($this->_info[self::INFO_KEY_LIMIT]) && $this->_info[self::INFO_KEY_LIMIT] > 0
            ? ' LIMIT ' . $this->_info[self::INFO_KEY_LIMIT]
            : '';
    }

    protected function _get_counted_data()
    {
        return $this->_data;
    }
}