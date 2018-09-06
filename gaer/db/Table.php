<?php
namespace Gaer\db;

use Gaer\exceptions;

abstract class Table
{
    abstract protected function _get_db_config();

    abstract public function get_table_name();
    abstract public function get_indexes();
    abstract public function get_fields_info();

    const INDEX_TYPE_PRIMARY = 1;
    const INDEX_TYPE_UNIQ = 2;
    const INDEX_TYPE_COMMON = 3;

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

    public static function get_instance()
    {
        if (self::$_instance === null) {
            $kls = get_called_class();
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
        return self::get_instance()->get_primary_key();
    }

    public function get_primary_key()
    {
        $indexes = $this->get_indexes();
        return isset($indexes[self::INDEX_TYPE_PRIMARY])
            ? $indexes[self::INDEX_TYPE_PRIMARY]
            : null;
    }

    public function create()
    {
        return $this->get_db_connection()
            ->create_table(
                $this->get_table_name(),
                $this->get_fields_info(),
                $this->get_primary_key()
            );
    }

    public function create_index()
    {
        return $this->get_db_connection()
            ->create_indexes(
                $this->get_table_name(),
                $this->get_indexes()
            );
    }

    public function get($primary)
    {
        $primary_key = $this->get_primary_key();
        if (!$primary_key) {
            throw new exceptions\db\unexpectedInput('no primary key in table: ' . get_called_class());
        }
        $data = null;
        if ($this->_is_enable_cache()) {
            $data = $this->_load_from_cache($primary);
        }
        $data || $data = $this->get_by([$primary_key => $primary]);
        $this->_is_enable_cache() && $this->_cache($primary, $data);
        return $data;
    }

    public function insert($data)
    {
        $data = self::filter_by_fields($data);
        $id = $this->connect_and_select()
            ->insert($data)
            ->exec();
        $this->_is_enable_cache() && $this->_cache($id, $data);
        return $id;
    }

    /*
    public function upsert($data)
    {
        $this->_is_enable_cache() && $this->_clear_cache();
        $data = self::filter_by_fields($data);
        return $this->connect_and_select()
            ->insert($data)
            ->on_conflict()
            ->update($data)
            ->exec();
    }
    */

    public function update($data, $cond)
    {
        $primary_key = self::primary_key();
        $data = self::filter_by_fields($data);
        if (is_array($cond)) {
            $cond = self::filter_by_fields($cond);
            $this->_is_enable_cache() && $this->_clear_cache();
        } elseif ($primary_key) {
            $cond = [$primary_key => $cond];
            $this->_is_enable_cache() && $this->_clear_cache($cond);
        } else {
            throw new exceptions\db\unexpectedInput('no primary key in table: ' . get_called_class());
        }
        return $this->connect_and_select()
            ->where($cond)
            ->update($data)
            ->exec();
    }

    public function delete($cond)
    {
        $primary_key = self::primary_key();
        if (is_array($cond)) {
            $cond = self::filter_by_fields($cond);
            $this->_is_enable_cache() && $this->_clear_cache();
        } elseif ($primary_key) {
            $cond = [$primary_key => $cond];
            $this->_is_enable_cache() && $this->_clear_cache($cond);
        } else {
            throw new exceptions\db\unexpectedInput('no primary key in table: ' . get_called_class());
        }
        return $this->connect_and_select()
            ->where($cond)
            ->delete()
            ->exec();
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
        if ($sort) {
            $h->sort($sort);
        }
        $result = $h->get_all();
        return $result;
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
        $fields = self::fields_info();
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
        if (!self::primary_key()) {
            return false;
        }
        return is_array($this->_cache);
    }

    protected function _cache($id, $data)
    {
        $this->_cache[$id] = $data;
        return $this;
    }

    protected function _load_from_cache($id)
    {
        return isset($this->_cache[$id]) ? $this->_cache[$id] : null;
    }

    protected function _clear_cache($target = null)
    {
        if ($target && isset($this->_cache[$target])) {
            unset($this->_cache[$target]);
        } else {
            $this->_cache = [];
        }
    }
}