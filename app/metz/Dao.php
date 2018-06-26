<?php
namespace Metz\app\metz;
use Metz\sys\Log;
use Metz\app\metz\exceptions;
use Metz\app\metz\constants\Driver;

abstract class Dao
{
    const DATA_VAL = 1;
    const DATA_STATUS = 2;

    const DATA_STATUS_EMPTY = 1;
    const DATA_STATUS_ASSIGNED = 2;
    const DATA_STATUS_LOADED = 3;
    const DATA_STATUS_SAVED = 4;
    const DATA_STATUS_MODIFIED = 5;
    const DATA_STATUS_DELETED = 6;

    const STATUS_INIT = 1;
    const STATUS_CHANGED = 2;
    const STATUS_SAME = 3;
    const STATUS_NOT_EXISTS = 4;

    protected $_conn = null;
    protected $_primary_val = null;
    protected $_data = [];
    protected $_status = self::DATA_STATUS_INIT;

    public abstract function get_db_config();
    public abstract function get_table_name();
    public abstract function get_fields();
    public abstract function get_primary_key();
    public abstract function get_keys();
    public abstract function get_table_relations();

    public function load()
    {
        $primary_key = $this->get_primary_key();
        $data = $this->_get_connection()
              ->set_table($this->_get_table_name())
              ->where([$primary_key => $this->_primary_val])
              ->select($this->get_fields())
              ->get();
        if (empty($data)) {
            $this->_status = self::STATUS_NOT_EXISTS;
            return $this;
        }
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
                throw new exceptions\UnexpectedValue(
                    [
                        'err' => 'Unpected result when load data',
                        'conn' => json_encode($this->_conn),
                        'fields' => $fields,
                        'result' => json_encode($data),
                    ]
                );
            }
        }
        $this->_status = self::STATUS_SAME;
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
        $this->_status = self::STATUS_NOT_EXISTS;
        return $del;
    }

    protected function _insert()
    {
        $data = $this->_filter_data();
        $this->_get_connection()
            ->set_table($this->_get_table_name())
            ->insert($data);
        $this->_status = self::STATUS_SAME;
        return $this;
    }

    protected function _upsert()
    {
        $data = $this->_filter_data();
        $this->_get_connection()
            ->set_table($this->_get_table_name())
            ->upsert($data);
        $this->_status = self::STATUS_SAME;
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
        $this->_status = self::STATUS_SAME;
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
        $fields = $this->get_fields();
        foreach ($fields as $_f => $_conf) {
            if ($_f == $this->get_primary_key()) {
                continue;
            }
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
    protected function _get_table_name()
    {
        $table_name = $this->get_table_name();
        if (!is_string($table_name) || !$table_name) {
            throw new exceptions\UnexpectedInput(
                'unexpect table name: ' . var_export($table_name, true)
            );
        }
        return $table_name;
    }

    protected function _get_connection()
    {
        $conf = $this->get_db_config();
        if (!isset($conf['ip'])
            || !isset($conf['port'])
        ) {
        }
        $driver = isset($conf['driver']) ? $conf['driver'] : Driver::MYSQL;
        $ext = isset($conf['ext']) ? $conf['ext'] : [];
        if ($this->_conn) {
            try {
                $this->_conn->select_db($db_name);
            } catch (exceptions\Db $ex) {
                Log::warning($ex);
                $this->_conn = null;
            }
        }
        if ($this->_conn) {
            $this->conn = Dba::connection($driver, $conf['ip'], $conf['port'], $ext);
        }
        return $this->conn;
    }

    public function __call($name, $args)
    {
        $primary_key = $this->get_primary_key();
        $fname_set_primary_val = 'set_' . $primary_key;
        if (strcmp($name,$fname_set_primary_val) == 0
            && isset($args[0])
        ) {
            $this->_primary_val = $args[0];
            return $this;
        }
        throw new \BadMethodCallException('no such method ' . get_class() . '::' . $name);
    }

    public function __set($field, $val)
    {
        $fields = $this->get_fields();
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
            $this->_status == self::STATUS_CHANGED;
        } else {
            throw new \UnexpectedValueException('no such property: ' . $field);
        }
    }

    public function __get($field)
    {
        if ($this->_status == self::STATUS_NOT_EXISTS) {
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
}