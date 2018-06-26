<?php
namespace Metz\app\metz;
use Metz\sys\Log;

abstract class Dao
{
    const DATA_VAL = 1;
    const DATA_STATUS = 2;

    const DATA_STATUS_LOAD = 1;
    const DATA_STATUS_SAVED = 2;
    const DATA_STATUS_MODIFIED = 3;

    protected $_conn = null;
    protected $_data = [];

    public abstract function get_db_name($fields = null);
    public abstract function get_table_name($fields = null);
    public abstract function get_fields();
    public abstract function get_primary_key();
    public abstract function get_keys();
    public abstract function get_table_relations();

    public function load($primary_val)
    {
        $conn = $this->get_connection();
        $primary_key = $this->get_primary_key();
        $fields = $this->get_fields();
        $data = $this->_from_table()->get_one([$primary_key => $primary_val], $fields);
        foreach ($fields as $_f => $_conf) {
            if ($_f == $primary_key) {
                continue;
            }
            if (array_key_exists($_f, $data)) {
                $this->_data[$_f] = [
                    self::DATA_VAL => $data[$_f],
                    self::DATA_STATUS => self::DATA_STATUS_LOAD,
                ];
            } else {
                throw new UnexpectedValueException(
                    [
                        'err' => 'Unpected result when load data',
                        'conn' => json_encode($this->_conn),
                        'fields' => $fields,
                        'result' => json_encode($data),
                    ]
                );
            }
        }
    }

    public function update()
    {
        $data = $this->filter_update_data();
        return $this->_conn
            ->update($data)
            ->where($this->$primary_key);
    }

    public function save()
    {
    }

    public function del()
    {
    }

    protected function _fake_del()
    {
        return false;
    }

    protected function _from_table($fields)
    {
        $table_name = $this->get_table_name($fields);
        if (!is_string($table_name) || !$table_name) {
            throw new PrepareException(
                'unexpect table name: ' . var_export($table_name, true)
                . ', fields: ' . json_encode($fields)
            );
        }
        return $conn->from($conn->table);
    }

    protected function _get_connection($fields = null)
    {
        $db_name = $this->get_db_name($fields);
        try {
            if ($this->_conn) {
                $this->_conn->select_db($db_name);
            }
        } catch (ConnectException $ex) {
            Log::warning($ex);
        }
        $this->conn = Dba::get_connection($db_name);
        return $this->conn;
    }

    public function __set($field, $val)
    {
        $fields = $this->get_fields();
        if (isset($fields[$field])) {
            $this->_data[$field] = [
                self::DATA_VAL => $data[$_f],
                self::DATA_STATUS => self::DATA_STATUS_MODIFIED,
            ];
        } else {
            throw new \UnexpectedValueException('no such property: ' . $field);
        }
    }

    public function __get($field)
    {
        if (isset($this->_data[$field][self::DATA_VAL])) {
            return $this->_data[$field][self::DATA_VAL];
        }
        return null;
    }
}