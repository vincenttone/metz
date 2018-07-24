<?php
namespace Gaer\db;
use Gaer\exceptions;

class Dao implements \JsonSerializable, \ArrayAccess
{
    const DATA_VAL = 1;
    const DATA_STATUS = 2;

    const DATA_STATUS_EMPTY = 1;
    const DATA_STATUS_ASSIGNED = 2;
    const DATA_STATUS_LOADED = 3;
    const DATA_STATUS_SAVED = 4;
    const DATA_STATUS_MODIFIED = 5;
    const DATA_STATUS_DELETED = 6;

    const PROPERTY_STATUS_INIT = 1;
    const PROPERTY_STATUS_CHANGED = 2;
    const PROPERTY_STATUS_SYNCED = 3;
    const PROPERTY_STATUS_NOT_EXISTS = 4;

    protected $_table = null;
    protected $_conn = null;
    protected $_id = null;
    protected $_data = [];
    protected $_status = self::PROPERTY_STATUS_INIT;

    // methods
    protected $_ext_methods = null;

    public function __construct($table, $id = null, $data = null)
    {
        $this->_table = $table;
        $id && $this->_set_id($id);
        is_array($data) && $this->_fill($data);
    }

    public function get_id()
    {
        return $this->_id;
    }

    public function get_table()
    {
        return $this->_table;
    }

    public function get_primary_key()
    {
        return $this->get_table()->get_primary_key();
    }

    public function load()
    {
        $data = $this->get_table()
              ->connect_and_select()
              ->where([$this->get_primary_key() => $this->get_id()])
              ->get();
        $this->_fill($data);
        $this->_status = self::PROPERTY_STATUS_SYNCED;
        return $this;
    }

    public function save()
    {
        if ($this->_id) {
            $this->_update();
        } elseif ($this->_enable_upsert()) {
            $this->_upsert();
        } else {
            $this->_insert();
        }
        $this->_update_data_status(self::DATA_STATUS_SAVED);
        return $this;
    }

    public function delete()
    {
        $del = $this->get_table()->delete($this->get_id());
        $this->_id = null;
        $this->_data = [];
        $this->_update_data_status(self::DATA_STATUS_DELETED);
        $this->_status = self::PROPERTY_STATUS_NOT_EXISTS;
        return $del;
    }

    protected function _insert()
    {
        $id = $this->get_table()->insert($this->_filter_data());
        $this->_status = self::PROPERTY_STATUS_SYNCED;
        if (!isset($data[$this->table()->get_primary_key()])
            && $this->_id === null
        ) {
            $this->_set_id($id);
        }
        return $this;
    }

    protected function _upsert()
    {
        $data = $this->_filter_data();
        $this->get_table()->upsert($data);
        $this->_status = self::PROPERTY_STATUS_SYNCED;
        return $this;
    }

    protected function _update()
    {
        $data = $this->_filter_data([self::DATA_STATUS_MODIFIED, self::DATA_STATUS_ASSIGNED]);
        if (empty($data)) {
            return 0;
        }
        $this->get_table()->update($data, $this->get_id());
        $this->_status = self::PROPERTY_STATUS_SYNCED;
        return $this;
    }

    /**
     * @desc select by uniq indexes
     * @param $cond
     * @return $this
     */
    protected function _get_by_uniq_index($cond)
    {
        $datas = $this->get_table()->get_by($cond);
        if (isset($datas[0])) {
            $this->_fill($datas[0]);
            $this->_status = self::PROPERTY_STATUS_SYNCED;
        } else {
            $this->_status = self::PROPERTY_STATUS_NOT_EXISTS;
        }
        return $this;
    }

    protected function _set_id($val)
    {
        $this->_id = $val;
        return $this;
    }

    protected function _fill($data)
    {
        if (empty($data)) {
            $this->_status = self::PROPERTY_STATUS_NOT_EXISTS;
            return $this;
        }
        $fields = $this->get_table()->get_fields_info();
        foreach ($fields as $_f => $_conf) {
            if ($_f == $this->get_primary_key()) {
                continue;
            }
            if (array_key_exists($_f, $data)) {
                $this->_data[$_f] = [
                    self::DATA_VAL => $data[$_f],
                    self::DATA_STATUS => self::DATA_STATUS_LOADED,
                ];
            } else {
                throw new exceptions\db\UnexpectedValue(
                    [
                        'err' => 'Unpected result when load data',
                        'conn' => json_encode($this->_conn),
                        'fields' => $fields,
                        'result' => json_encode($data),
                    ]
                );
            }
        }
        return $this;
    }

    protected function _update_data_status($status)
    {
        foreach ($this->_data as $_f => $_d) {
            isset($_d[self::DATA_STATUS])
                && $$this->_data[self::DATA_STATUS] == $status;
        }
        return $this;
    }

    protected function _filter_data($status_array = [])
    {
        $data = [];
        $fields = $this->get_table()->get_fields_info();
        foreach ($fields as $_f => $_conf) {
            if (!empty($status_array)
                && (!isset($this->_data[_f][self::DATA_STATUS])
                    || !in_array($this->_data[_f][self::DATA_STATUS], $status_array)
                )
            ) {
                    continue;
            }
            if (isset($this->_data[$_f][self::DATA_VAL])) {
                $data[$_f] = $this->_data[$_f][self::DATA_VAL];
            }
        }
        return $data;
    }

    protected function _get_fields()
    {
        return $this->get_table()->get_fields();
    }

    protected function _enable_upsert()
    {
        return false;
    }
    /**
     * @desc fake del supporting
     * @return array [status => val]
     */
    /*
    protected function _fake_del_info()
    {
        return [];
    }
    */
    public function array_copy()
    {
        if ($this->get_id() === null
            || $this->_status == self::PROPERTY_STATUS_NOT_EXISTS
        ) {
            return '';
        } elseif (empty($this->_data)) {
            $this->load();
        }
        $data = $this->_filter_data();
        $data[$this->get_primary_key()] = $this->_id;
        return $data;
    }

    public function jsonSerialize()
    {
        return $this->array_copy();
    }

    protected function _create_ext_methods()
    {
        if ($this->_ext_methods === null) {
            $this->_ext_methods = [];
            $fields = $this->get_table()->get_fields_info();
            if (!empty($fields)) {
                foreach ($fields as $_f => $_i) {
                    $this->_ext_methods['set_' . $_f] = $_f;
                }
            }
            $primary_key = $this->get_primary_key();
            $this->_ext_methods['get_by_' . $primary_key] = $primary_key;
            $indexes = $this->get_table()->get_indexes();
            if (isset($indexes[self::INDEX_TYPE_UNIQ])) {
                foreach ($indexes[self::INDEX_TYPE_UNIQ] as $_i) {
                    $fname = is_array($_i)
                           ? 'get_by_' . implode('_and_', $_i)
                           : 'get_by_' . $_i;
                    $this->_ext_methods[$fname] = $_i;
                }
            }
        }
        return $this;
    }

    protected function _run_ext_method($name, $args)
    {
        if (isset($this->_ext_methods[$name])) {
            if (strcmp($name, 'set_') == 0
                && isset($args[0])
            ) {
                $field = $this->_ext_methods[$name];
                $this->_data[$field] = $args[0];
                return $this;
            } elseif (
                strcmp($name, 'get_by_') == 0
                && count($args) == count($this->_ext_methods[$name])
            ) {
                $cond = array_combine($this->_ext_methods[$name], $args);
                return $this->_get_by_uniq_index($cond);
            }
        }
        throw new \BadMethodCallException('no such method ' . get_class() . '::' . $name);
    }

    public function __call($name, $args)
    {
        $this->_create_ext_methods();
        return $this->_run_ext_method($name, $args);
    }

    public function __set($field, $val)
    {
        $fields = $this->get_table()->get_fields_info();
        if (isset($fields[$field])) {
            if (isset($this->_data[$field])
                && isset($this->_data[$field][self::DATA_STATUS])
                && $this->_data[$field][self::DATA_STATUS] != self::DATA_STATUS_ASSIGNED
            ) {
                $this->_data[$field] = [
                    self::DATA_VAL => $data[$_f],
                    self::DATA_STATUS => self::DATA_STATUS_MODIFIED,
                ];
            } else {
                $this->_data[$field] = [
                    self::DATA_VAL => $data[$_f],
                    self::DATA_STATUS => self::DATA_STATUS_ASSIGNED,
                ];
            }
            $this->_status == self::PROPERTY_STATUS_CHANGED;
        } else {
            throw new \UnexpectedValueException('no such property: ' . $field);
        }
    }

    public function __get($field)
    {
        if ($this->_status == self::PROPERTY_STATUS_NOT_EXISTS) {
            return null;
        }
        if (empty($this->_data) && $this->_id) {
            $this->load();
        }
        if (isset($this->_data[$field][self::DATA_VAL])) {
            return $this->_data[$field][self::DATA_VAL];
        }
        return null;
    }

    // array access
    public function offsetExists($offset)
    {
        return $offset == $this->get_primary_key() || isset($this->_data[$offset]);
    }

    public function offsetGet($offset)
    {
        if ($offset == $this->get_primary_key()) {
            return $this->get_id();
        } elseif (isset($this->_data[$offset])) {
            return $this->_data[$offset];
        }
        return null;
    }

    public function offsetSet($offset, $val)
    {
        if ($offset == $this->get_primary_key()) {
            $this->_set_id($val);
        } else {
            $fields_info = $this->_get_table()->get_fields_info();
            if (isset($fields_info[$offset])) {
                $this->_data[$offset] = $val;
            }
        }
    }

    public function offsetUnset($offset)
    {
        if ($offset == $this->get_primary_key()) {
            $this->_set_id(null);
        }
        if (isset($this->_data[$offset])) {
            unset($this->_data[$offset]);
        }
    }
}