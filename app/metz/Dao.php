<?php
namespace Metz\app\metz;
use Metz\sys\Log;
use Metz\app\metz\exceptions\db;
use Metz\app\metz\configure\Driver;

abstract class Dao implements \JsonSerializable, \ArrayAccess
{
    public abstract function get_indexes();
    
    protected abstract function _get_db_config();
    protected abstract function _get_table_name();
    protected abstract function _get_fields_info();
    // relations
    protected abstract function _get_related_table_info();

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

    const INDEX_TYPE_PRIMARY = 1;
    const INDEX_TYPE_UNIQ = 2;
    const INDEX_TYPE_COMMON = 3;

    protected $_conn = null;
    protected $_primary_val = null;
    protected $_data = [];
    protected $_status = self::PROPERTY_STATUS_INIT;

    // methods
    protected $_ext_methods = null;

    public function __construct($val = null)
    {
        $val && $this->_set_primary_val($val);
    }

    public function get_primary_key()
    {
        $indexes = $this->get_indexes();
        return $indexes[self::INDEX_TYPE_PRIMARY];
    }

    public function get_primary_val()
    {
        return $this->_primary_val;
    }

    public function load()
    {
        $primary_key = $this->get_primary_key();
        $data = $this->_get_connection()
              ->set_table($this->_get_table_name())
              ->where([$primary_key => $this->_primary_val])
              ->select($this->get_fields())
              ->get();
        $this->_package($data);
        $this->_status = self::PROPERTY_STATUS_SYNCED;
        return $this;
    }

    public function save()
    {
        if ($this->_primary_val) {
            $this->_update();
        } elseif ($this->_enable_upsert()) {
            $this->_upsert();
        } else {
            $this->_insert();
        }
        $this->_update_data_status(self::DATA_STATUS_SAVED);
        return $this;
    }

    public function del()
    {
        $del = $this->_get_connection()
            ->set_table($this->_get_table_name())
            ->where([$primary_key => $this->_primary_val])
            ->delete();
        $this->_update_data_status(self::DATA_STATUS_DELETED);
        $this->_data = [];
        $this->_status = self::PROPERTY_STATUS_NOT_EXISTS;
        return $del;
    }

    public function get_related_key($dao_cls, $key)
    {
        $info = $this->_get_related_table_info();
        return isset($info[$dao_cls][$key])
            ? $info[$dao_cls][$key]
            : null;
    }

    public function get_fields()
    {
        $fields_info = $this->_get_fields_info();
        return array_keys($fields_info);
    }

    protected function _package($data)
    {
        if (empty($data)) {
            $this->_status = self::PROPERTY_STATUS_NOT_EXISTS;
            return $this;
        }
        $fields = $this->_get_fields_info();
        $primary_key = $this->get_primary_key();
        foreach ($fields as $_f => $_conf) {
            if ($_f == $primary_key) {
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

    protected function _insert()
    {
        $data = $this->_filter_data();
        $this->_get_connection()
            ->set_table($this->_get_table_name())
            ->insert($data);
        $this->_status = self::PROPERTY_STATUS_SYNCED;
        if (!isset($data[$this->get_primary_key()])
            && $this->_primary_val === null
        ) {
            $this->_set_primary_val(
                $this->_get_connection()
                ->last_insert_id()
            );
        }
        return $this;
    }

    protected function _set_primary_val($val)
    {
        $this->_primary_val = $val;
        $this->_data[$this->get_primary_key()] = $val;
        return $this;
    }

    protected function _upsert()
    {
        $data = $this->_filter_data();
        $this->_get_connection()
            ->set_table($this->_get_table_name())
            ->upsert($data);
        $this->_status = self::PROPERTY_STATUS_SYNCED;
        return $this;
    }

    protected function _update()
    {
        $data = $this->_filter_data([self::DATA_STATUS_MODIFIED, self::DATA_STATUS_ASSIGNED]);
        if (empty($data)) {
            return 0;
        }
        $this->_get_connection()
            ->set_table($this->_get_table_name())
            ->update($data)
            ->where([$this->$primary_key => $this->_primary_val])
            ->commit();
        $this->_status = self::PROPERTY_STATUS_SYNCED;
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
        $fields = $this->_get_fields_info();
        foreach ($fields as $_f => $_conf) {
            if (!empty($status_array)
                && (!isset($this->_data[_f][self::DATA_STATUS])
                    || !in_array($this->_data[_f][self::DATA_STATUS], $status_array)
                )
            ) {
                    continue;
            }
            if (isset($this->_data[_f][self::DATA_VAL])) {
                $data[$_f] = $this->_data[_f][self::DATA_VAL];
            }
        }
        return $data;
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
    protected function _get_connection()
    {
        $conf = $this->_get_db_config();
        if (!isset($conf['ip'])
            || !isset($conf['port'])
        ) {
        }
        $driver = isset($conf['driver']) ? $conf['driver'] : Driver::MYSQL;
        $ext = isset($conf['ext']) ? $conf['ext'] : [];
        if ($this->_conn) {
            try {
                $this->_conn->select_db($db_name);
            } catch (exceptions\db\Db $ex) {
                Log::warning($ex);
                $this->_conn = null;
            }
        }
        if ($this->_conn) {
            $this->conn = Dba::connection($driver, $conf['ip'], $conf['port'], $ext);
        }
        return $this->conn;
    }
    /**
     * @desc select by uniq indexes
     * @param $cond
     * @return $this
     */
    protected function _get_by_uniq_index($cond)
    {
        $data = $this->_get_connection()
              ->set_table($this->_get_table_name())
              ->where($cond)
              ->select($this->get_fields())
              ->get();
        $this->_package($data);
        $this->_status = self::PROPERTY_STATUS_SYNCED;
        return $this;
    }

    public function array_copy()
    {
        return $this->_filter_data();
    }

    public function jsonSerialize()
    {
        return $this->array_copy();
    }

    protected function _create_ext_methods()
    {
        if ($this->_ext_methods === null) {
            $this->_ext_methods = [];
            $fields = $this->_get_fields_info();
            if (!empty($fields)) {
                foreach ($fields as $_f => $_i) {
                    $this->_ext_methods['set_' . $_f] = $_f;
                }
            }
            $primary_key = $this->get_primary_key();
            $this->_ext_methods['get_by_' . $primary_key] = $primary_key;
            $indexes = $this->get_indexes();
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
        $fields = $this->_get_fields_info();
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
        if (empty($this->_data) && $this->_primary_val) {
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
            return $this->get_primary_val();
        } elseif (isset($this->_data[$offset])) {
            return $this->_data[$offset];
        }
        return null;
    }

    public function offsetSet($offset, $val)
    {
        if ($offset == $this->get_primary_key()) {
            $this->_set_primary_val($val);
        } else {
            $fields_info = $this->_get_fields_info();
            if (isset($fields_info[$offset])) {
                $this->_data[$offset] = $val;
            }
        }
    }

    public function offsetUnset($offset)
    {
        if ($offset == $this->get_primary_key()) {
            $this->_set_primary_val(null);
        } elseif (isset($this->_data[$offset])) {
            unset($this->_data[$offset]);
        }
    }
}