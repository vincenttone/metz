<?php
namespace Metz\app\metz\db\drivers;

use Metz\app\metz\exceptions\db;

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

    public function is_ready()
    {
        return $this->_status == self::STATUS_NORMAL;
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
                throw exceptions\db\unexpectedInput([
                    'err' => 'unexpect action type',
                    'current' => $this->_type,
                    'new' => $type,
                ]);
            }
        } else {
            throw exceptions\db\unexpectedInput([
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
        return [
            'type' => $this->_type,
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

    public function insert($data)
    {
        $first = reset($data);
        if (is_array($frist)) {
            isset($first[0]) || $this->set_fields(array_keys($first));
            $this->_info[self::INFO_KEY_INSERT] = empty($this->_info[self::INFO_KEY_INSERT])
                                                ? $data
                                                : array_merge($this->_info[self::INFO_KEY_INSERT], $data);
        } else {
            isset($data[0])  || $this->_get_current_act()->set_fields(array_keys($data));
            if (empty($this->_info[self::INFO_KEY_INSERT])) {
                $this->_info[self::INFO_KEY_INSERT] = [$data];
            } else {
                $this->_info[self::INFO_KEY_INSERT][] = $data;
            }
        }
        return $this;
    }

    public function update()
    {
        $first = reset($data);
        if (is_array($frist)) {
            $this->_info[self::INFO_KEY_UPDATE] = empty($this->_info[self::INFO_KEY_UPDATE])
                                                ? $data
                                                : array_merge($this->_info[self::INFO_KEY_UPDATE], $data);
        } else {
            if (empty($this->_info[self::INFO_KEY_UPDATE])) {
                $this->_info[self::INFO_KEY_UPDATE] = [$data];
            } else {
                $this->_info[self::INFO_KEY_UPDATE][] = $data;
            }
        }
        return $this;
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
                    throw exceptions\db\unexpectedInput('unexpect insert data: ' . json_encode($this->_info));
                }
                $data = array_merge($data, $_d);
                $str_arr[] = '(' . implode(', ', array_fill(0, $count, '?')) . ')';
            }
            $this->_data = array_merge($this->_data, $data);
            return implode(', ', $str_arr);
        } else {
            throw exceptions\db\unexpectedInput('unexpect insert data: ' . json_encode($this->_info));
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
            throw exceptions\db\unexpectedInput('unexpect update data: ' . json_encode($this->_info));
        }
    }

    protected function _get_where_str()
    {
        $str = ' WHERE ';
        $str_arr = [];
        $data = [];
        foreach ($this->_info[self::INFO_KEY_WHERE] as $_k => $_v) {
            if (is_array($_v)) {
                if (isset($_v[0]) && isset($_v[1])) {
                    switch(trim($_v[0])) {
                    case '=':
                    case '>':
                    case '<':
                    case '>=':
                    case '<=':
                    case 'like':
                        $str_arr[] = $_k . ' ' . $_v[0] . ' ?';
                        $data[] = $_v[1];
                        break;
                    case 'in':
                        if (!is_array($_v[1])) {
                            throw new exceptions\db\UnexpectedInput(
                                'unexpect where in data: ' . json_encode($this->_info[self::INFO_KEY_WHERE])
                            );
                        }
                        $str_arr[] = '(' . implode(', ', array_fill(0, count($_v[1]), '?')) . ')';
                        $data = array_merge($data, $_v[1]);
                        break;
                    }
                }
            } else {
                $str_arr[] = $_k . ' = ' . ' ?';
                $data[] = $_v[1];
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
        $str = ' ORDER BY ';
        $arr = [];
        foreach ($this->_info[self::INFO_KEY_SORT] as $_f => $_d) {
            $arr[] = $_f . ' ' . $_v;
        }
        if (empty($arr)) {
            $str = '';
        } else {
            $str .= implode(', ', $arr);
        }
        return $str;
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