<?php
namespace Metz\app\metz\db;

use Metz\sys\Log;
use Metz\app\metz\exceptions;
use Metz\app\metz\configure\Driver;

abstract class Table
{
    abstract protected function _get_db_config();
    abstract public function get_table_name();
    abstract public function get_indexes();
    abstract public function get_fields_info();
    abstract public function get_related_table_info();

    const FIELD_TYPE_INT = 1;
    const FIELD_TYPE_FLOAT = 2;
    const FIELD_TYPE_DOUBLE = 7;
    const FIELD_TYPE_CHAR = 8;
    const FIELD_TYPE_VARCHAR = 9;
    const FIELD_TYPE_TEXT = 10;
    const FIELD_TYPE_DATE = 11;
    const FIELD_TYPE_TIME = 12;
    const FIELD_TYPE_DATETIME = 13;
    const FIELD_TYPE_TIMESTAMP = 14;
    const FIELD_TYPE_BOOL = 15;

    const FIELD_INFO_TYPE = 'type';
    const FIELD_INFO_AUTO_INCREMENT = 'ai';
    const FIELD_INFO_NULLABLE = 'nullable';
    const FIELD_INFO_DEFAULT = 'default';
    const FIELD_INFO_LENGTH = 'length';
    const FIELD_INFO_UNSIGNED = 'unsigned';

    protected $_conn = null;

    protected static $_instance = null;

    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

    public function get_instance()
    {
        if (self::$_instance === null) {
            $kls = get_class();
            self::$_instance = new $kls();
        }
        return self::$_instance;
    }

    public function get_primary_key()
    {
        $indexes = $this->get_indexes();
        return $indexes[self::INDEX_TYPE_PRIMARY];
    }

    public function create()
    {
        return $this->_get_connection()->set_monitor(
            function ($str) {
                Log::info("[DB execute info]\t" . $str);
            }
        )->create_table(
            $this->get_table_name(),
            $this->get_fields_info(),
            $this->get_primary_key()
        );
    }

    public function create_index()
    {
        return $this->_get_connection()
            ->create_indexes(
                $this->get_table_name(),
                $this->get_indexes()
            );
    }

    public function get($primary, $fields = null)
    {
        return $this->connect_and_select()
            ->where([$this->get_primary_key() => $primary])
            ->select(empty($fields) ? $this->get_fields() : $fields)
            ->get();
    }

    public function get_by($cond, $fields = null)
    {
        return $this->connect_and_select()
            ->where($cond)
            ->select(empty($fields) ? $this->get_fields() : $fields)
            ->get_all();
    }

    public function insert($data)
    {
        $data = $this->_filter_by_fields($data);
        $this->connect_and_select()
            ->insert($data);
        return $this->_get_connection()->last_insert_id();
    }

    public function multi_insert()
    {
        $filtered = [];
        foreach ($data as $_d) {
            $filtered[] = $this->_filter_by_fields($_d);
        }
        return $this->connect_and_select()
            ->insert($filtered);
    }

    public function upsert($data)
    {
        $data = $this->_filter_by_fields($data);
        return $this->connect_and_select()
            ->upsert($data);
    }

    public function update($data, $cond)
    {
        $data = $this->_filter_by_fields($data);
        $cond = $this->_filter_by_fields($cond);
        return $this->connect_and_select()
            ->where($cond)
            ->update($data);
    }

    public function delete($cond)
    {
        $cond = $this->_filter_by_fields($cond);
        return $this->connect_and_select()
            ->where($cond)
            ->delete();
    }

    public function get_fields()
    {
        $fields_info = $this->_get_fields_info();
        return array_keys($fields_info);
    }

    public function get_related_key($dao_cls, $key)
    {
        $info = $this->_get_related_table_info();
        return isset($info[$dao_cls][$key])
            ? $info[$dao_cls][$key]
            : null;
    }

    public function connect_and_select()
    {
        return $this->_get_connection()
            ->set_table($this->get_table_name());
    }

    protected function _filter_by_fields($data)
    {
        $fields = $this->get_fields();
        foreach ($data as $_f => $_d) {
            if (!isset($fields[$_f])) {
                unset($data[$_f]);
            }
        }
        return $data;
    }

    protected function _get_connection()
    {
        $conf = $this->_get_db_config();
        if (!isset($conf['ip'])
            || !isset($conf['port'])
        ) {
            throw new exceptions\db\UnexpectedInput('unexpect db configure: ' . json_encode($conf));
        }
        $driver = isset($conf['driver']) ? $conf['driver'] : Driver::MYSQL;
        $ext = isset($conf['ext']) ? $conf['ext'] : [];
        $db_name = $conf['db'];
        if ($this->_conn) {
            try {
                $this->_conn->select_db($db_name);
            } catch (exceptions\db\Db $ex) {
                Log::warning($ex);
                $this->_conn = null;
            }
        }
        if ($this->_conn === null) {
            $this->_conn = Dba::connection(
                $driver,
                $conf['ip'],
                $conf['port'],
                $conf['user'] ?? 'root',
                $conf['password'] ?? '',
                $db_name,
                $ext
            );
        }
        return $this->_conn;
    }
}