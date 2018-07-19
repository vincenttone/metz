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
    protected $_cache = null;

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

    public static function indexes()
    {
        return self::get_instance()->get_indexes();
    }

    public static function table_name()
    {
        return self::get_instance()->get_table_name();
    }

    public static function fields()
    {
        return self::get_instance()->get_fields();
    }

    public static function fields_info()
    {
        return self::get_instance()->get_fields_info();
    }

    public static function primary_key()
    {
        return self::get_instance()->primary_key();
    }

    public static function related_table_info()
    {
        return self::get_instance()->get_related_table_info();
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

    public function get($primary)
    {
        $dao = null;
        if ($this->_is_enable_cache()) {
            $dao = $this->_load_from_cache($primary);
        }
        $dao === null && $dao = new Dao($this, $primary);
        $this->_is_enable_cache() && $this->_cache($dao);
        return $dao;
    }

    public function insert($data)
    {
        $data = self::filter_by_fields($data);
        $this->connect_and_select()
            ->insert($data);
        $id = $this->_get_connection()->last_insert_id();
        $this->_is_enable_cache() && $this->_cache(new Dao($this, $id, $data));
        return $id;
    }

    public function upsert($data)
    {
        $this->_is_enable_cache() && $this->_clear_cache();
        $data = self::filter_by_fields($data);
        return $this->connect_and_select()
            ->upsert($data);
    }
    
    public function update($data, $cond)
    {
        $data = self::filter_by_fields($data);
        if (is_array($cond)) {
            $cond = self::filter_by_fields($cond);
            $this->_is_enable_cache() && $this->_clear_cache();
        } else {
            $cond = [self::primary_key() => $cond];
            $this->_is_enable_cache() && $this->_clear_cache($cond);
        }
        return $this->connect_and_select()
            ->where($cond)
            ->update($data);
    }

    public function delete($cond)
    {
        if (is_array($cond)) {
            $cond = self::filter_by_fields($cond);
            $this->_is_enable_cache() && $this->_clear_cache();
        } else {
            $cond = [self::primary_key() => $cond];
            $this->_is_enable_cache() && $this->_clear_cache($cond);
        }
        return $this->connect_and_select()
            ->where($cond)
            ->delete();
    }

    public function count($conds = null)
    {
        $h = $this->connect_and_select();
        $conds && $h->where($conds);
        return $h->count();
    }

    public function get_by($conds, $sort = null, $offset = 0, $limit = 0)
    {
        $h = $this->connect_and_select()
             ->where($conds);
        $offset > 0 && $h->offset($offset);
        $limit > 0 && $h->limit($limit);
        $sort && $h->sort($sort);
        $result = $h->get_all();
        $daos = [];
        foreach ($result as $_d) {
            $daos[] = new Dao($this, $_d[self::primary_key()], $_d);
        }
        return $daos;
    }

    public function multi_insert($data)
    {
        $filtered = [];
        foreach ($data as $_d) {
            $filtered[] = self::filter_by_fields($_d);
        }
        return $this->connect_and_select()
            ->insert($filtered);
    }

    public function get_fields()
    {
        $fields_info = $this->get_fields_info();
        return array_keys($fields_info);
    }

    public function get_related_key($table_cls, $key)
    {
        $info = $this->get_related_table_info();
        return isset($info[$table_cls][$key])
            ? $info[$table_cls][$key]
            : null;
    }

    public function connect_and_select()
    {
        return $this->get_db_connection()
            ->set_table($this->get_table_name());
    }

    public function get_db_connection()
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

    public static function filter_by_fields($data)
    {
        $fields = self::fields();
        foreach ($data as $_f => $_d) {
            if (!isset($fields[$_f])) {
                unset($data[$_f]);
            }
        }
        return $data;
    }

    public function enable_cache()
    {
        $this->_cache = [];
        return $this;
    }

    public function disable_cache()
    {
        $this->_cache = null;
        return $this;
    }

    protected function _is_enable_cache()
    {
        return is_array($this->_cache);
    }

    protected function _cache($dao)
    {
        $this->_cache[$dao[$this->get_primary_key()]] = $dao;
        return $this;
    }

    protected function _load_from_cache($id)
    {
        return isset($this->_cache[$id]) ? $this->_cache[$id] : null;
    }

    protected function _clear_cache($target = null)
    {
        if (is_object($target) && ($target instanceof Dao)) {
            $this->_clear_cache($target[$this->get_primary_key()]);
        } elseif (isset($this->_cache[$target])) {
            unset($this->_cache[$target]);
        } else {
            $this->_cache = [];
        }
    }
}