<?php
namespace Metz\app\metz\db\drivers;

use Metz\app\metz\Dao;
use Metz\app\metz\exceptions;

class Mysql implements Driver
{
    protected $_conn = null;
    protected $_charset = 'utf8';
    protected $_engine = 'InnoDB';
    protected $_monitor = null;

    protected $_acts = null;

    const ACT_FLAG_WAITING = 1;
    const ACT_FLAG_LOADING = 2;
    const ACT_FLAG_SEEKING_UPSERT = 3;
    const ACT_FLAG_SEEKING_CONFLICT = 4;

    public function connect($ip, $port, $user = null, $password = null, $db = null, $ext = [])
    {
        $dsn = 'mysql:host=' . $ip . ';port=' . $port;
        $db && $dsn .= ';dbname=' . $db;
        $this->_conn = new \PDO($dsn, $user, $password);
        isset($ext['charset']) && $this->_charset = $ext['charset'];
        return $this->set_charset($this->_charset);
    }

    public function disconnect()
    {
        $this->_conn = null;
    }

    public function set_charset($charset)
    {
        $this->_charset = $charset;
        $this->_prepare_and_run('set names :charset;', [':charset' => $charset]);
        return $this;
    }

    public function check_connection()
    {
        return $this->set_charset($this->_charset);
    }

    public function select_db($db_name)
    {
        $this->_prepare_and_run('use ' . $db_name);
        return $this;
    }

    public function set_table($table)
    {
        $this->_get_current_act()->set_table($table);
        return $this;
    }

    public function set_monitor($monitor)
    {
        $this->_monitor = $monitor;
        return $this;
    }

    public function select($fields = null)
    {
        $this->_add_act(self::ACT_QUERY);
        $this->_get_current_act()->set_fields($fields);
        return $this;
    }

    public function insert($data)
    {
        $this->_add_act(self::ACT_INSERT);
        $this->_get_current_act()->insert($data);
        return $this;
    }

    public function update($data)
    {
        $this->_add_act(self::ACT_UPDATE);
        $this->_get_current_act()->update($data);
        return $this;
    }

    public function del()
    {
        return $this->_add_act(self::ACT_DELETE);
    }

    public function count($fields = null)
    {
        $this->_get_current_act()->count($fields);
        return $this;
    }

    public function exists($fields = null)
    {
        $this->_get_current_act()->exists($fields);
        return $this;
    }

    public function where($cond)
    {
        $this->_get_current_act()->where($cond);
        return $this;
    }

    public function in($field, $arr)
    {
        $this->_get_current_act()->in([$field => $arr]);
        return $this;
    }

    public function sort($fields)
    {
        $this->_get_current_act()->sort($fields);
        return $this;
    }

    public function limit($count)
    {
        $this->_get_current_act()->limit($count);
        return $this;
    }

    public function offset($count)
    {
        $this->_get_current_act()->offset($count);
        return $this;
    }

    public function on_conflict($keys)
    {
        $this->_get_current_act()->add_info(MysqlAction::INFO_KEY_ON_CONFLICT, $keys);
        return $this;
    }

    public function get()
    {
        $exec_info = $this->_acts->get_exec_info();
        $sth = $this->_conn->prepare($exec_info['prepare_str']);
        $ret = $sth->execute($exec_info['data']);
        return $sth->fetch();
    }

    public function get_all()
    {
        $exec_info = $this->_acts->get_exec_info();
        $sth = $this->_conn->prepare($exec_info['prepare_str']);
        $ret = $sth->execute($exec_info['data']);
        return $sth->fetchAll();
    }

    public function exec()
    {
        if (is_array($this->_acts)) { // transaction, not support now
        } else {
            $exec_info = $this->_acts->get_exec_info();
            $sth = $this->_conn->prepare($exec_info['prepare_str']);
            $ret = $sth->execute($exec_info['data']);
            if ($ret) {
                switch ($exec_info['type']) {
                case MysqlAction::TYPE_INSERT:
                case MysqlAction::TYPE_UPDATE:
                    return $this->_conn->getLastInsertId();
                case MysqlAction::TYPE_SELECT:
                    return $this->_conn->fetchAl();
                case MysqlAction::TYPE_UPDATE:
                case MysqlAction::TYPE_DELETE:
                default:
                    return $sth->rowCount();
                }
            } else {
                throw new exceptions\db\ExecuteFailed(json_encode($sth->errorInfo()));
            }
        }
        throw new exceptions\db\ExecuteFailed('executing option not supported');
    }

    public function create_table($table_name, $fields_info, $primary_key)
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $table_name . '` (';
        foreach ($fields_info as $_f => $_i) {
            $sql .= $_f . ' ';
            switch($_i[Dao::FIELD_INFO_TYPE]) {
            case Dao::FIELD_TYPE_INT:
                if (isset($_i[Dao::FIELD_INFO_LENGTH])) {
                    if ($_i[Dao::FIELD_INFO_LENGTH] < 4) {
                        $sql .= 'tinyint (' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                    } elseif ($_i[Dao::FIELD_INFO_LENGTH] < 6) {
                        $sql .= 'smallint (' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                    } elseif ($_i[Dao::FIELD_INFO_LENGTH] < 12) {
                        $sql .= 'int (' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                    } else {
                        $sql .= 'bigint (' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                    }
                } else {
                    $sql .= 'int';
                }
                isset($_i[Dao::FIELD_INFO_UNSIGNED])
                    && $_i[Dao::FIELD_INFO_UNSIGNED]
                    && $sql .= ' unsigned ';
                break;
            case Dao::FIELD_TYPE_FLOAT:
                $sql .= 'float';
                if (isset($_i[Dao::FIELD_INFO_LENGTH][1])) {
                    $sql .= '(' . $_i[Dao::FIELD_INFO_LENGTH][1] . ', ' . $_i[Dao::FIELD_INFO_LENGTH][1] . ') ';
                }
                isset($_i[Dao::FIELD_INFO_UNSIGNED])
                    && $_i[Dao::FIELD_INFO_UNSIGNED]
                    && $sql .= ' unsigned ';
                break;
            case Dao::FIELD_TYPE_DOUBLE:
                $sql .= 'double';
                if (isset($_i[Dao::FIELD_INFO_LENGTH][1])) {
                    $sql .= '(' . $_i[Dao::FIELD_INFO_LENGTH][1] . ', ' . $_i[Dao::FIELD_INFO_LENGTH][1] . ') ';
                }
                isset($_i[Dao::FIELD_INFO_UNSIGNED])
                    && $_i[Dao::FIELD_INFO_UNSIGNED]
                    && $sql .= ' unsigned ';
                break;
            case dao::FIELD_TYPE_CHAR:
                $sql .= 'char';
                if (isset($_i[Dao::FIELD_INFO_LENGTH])) {
                    $sql .= '(' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                }
                break;
            case dao::FIELD_TYPE_VARCHAR:
                $sql .= 'varchar';
                if (isset($_i[Dao::FIELD_INFO_LENGTH])) {
                    $sql .= '(' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                }
                break;
            case Dao::FIELD_TYPE_TEXT:
                if (isset($_i[Dao::FIELD_INFO_LENGTH])) {
                    if ($_i[Dao::FIELD_INFO_LENGTH] < 256) {
                        $sql .= 'tinytext(' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                    } elseif ($_i[Dao::FIELD_INFO_LENGTH] < 65536) {
                        $sql .= 'text(' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                    } elseif ($_i[Dao::FIELD_INFO_LENGTH] < 16777216) {
                        $sql .= 'mediumtext (' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                    } else {
                        $sql .= 'longtext (' . $_i[Dao::FIELD_INFO_LENGTH] . ') ';
                    }
                } else {
                    $sql .= 'text ';
                }
                break;
            case Dao::FIELD_TYPE_DATE:
                $sql .= 'date ';
                break;
            case Dao::FIELD_TYPE_TIME:
                $sql .= 'time ';
                break;
            case Dao::FIELD_TYPE_DATETIME:
                $sql .= 'datetime ';
                break;
            case Dao::FIELD_TYPE_TIMESTAMP:
                $sql .= 'timestamp ';
                break;
            }
            isset($_i[Dao::FIELD_INFO_AUTO_INCREMENT])
                && $sql .= ' AUTO_INCREMENT ';
            isset($_i[Dao::FIELD_INFO_NULLABLE])
                && $_i[Dao::FIELD_INFO_NULLABLE] === false
                && $sql .= ' NOT NULL ';
            if (isset($_i[Dao::FIELD_INFO_DEFAULT])) {
                $sql .= ' DEFAULT ? ';
                $data[] = $_i[Dao::FIELD_INFO_DEFAULT];
            }
            $sql .= ',';
        }
        $sql .= ' PRIMARY KEY (' . $primary_key;
        $sql .= ')) ENGINE = ' . $this->_engine
             . ' DEFAULT CHARSET = ' . $this->_charset;
        return $this->_prepare_and_run($sql, $data);
    }

    public function create_indexes($table, $indexes)
    {
        if (isset($indexes[Dao::INDEX_TYPE_COMMON][0])) {
            foreach ($indexes[Dao::INDEX_TYPE_COMMON] as $_i) {
                $idx = is_array($_i) ? 'idx_' . implode('_', $_i) : 'idx_' .$_i;
                $sql = 'CREATE INDEX ' . $idx;
                $sql .= ' ON ' . $table;
                $sql .= is_array($_i) ? ' (' . implode(', ' , $_i) . ')' : '(' . $_i . ')';
                $this->_prepare_and_run($sql, []);
            }
        }
        if (isset($indexes[Dao::INDEX_TYPE_UNIQ][0])) {
            foreach ($indexes[Dao::INDEX_TYPE_UNIQ] as $_i) {
                $idx = is_array($_i) ? 'idx_' . implode('_', $_i) : 'idx_' .$_i;
                $sql = 'CREATE UNIQUE INDEX ' . $idx;
                $sql .= ' ON ' . $table;
                $sql .= is_array($_i) ? ' (' . implode(', ' , $_i) . ')' : '(' . $_i . ')';
                $this->_prepare_and_run($sql, []);
            }
        }
    }

    protected function _prepare_and_run($prepare_str, $arr = [])
    {
        $sth= $this->_conn->prepare($prepare_str);
        $exec = $sth->execute($arr);
        if ($exec === false) {
            throw new exceptions\db\ExecuteFailed(
                'Execute failed, sql: ' . $sth->queryString
                . ', error info: ' . json_encode($sth->errorInfo())
            );
        }
        $str = 'Execute sql success: ' . $sth->queryString;
        empty($arr) || $str .= ', data: ' . json_encode($arr);
        $this->_monitor($str);
        return true;
    }

    protected function _monitor($str)
    {
        $this->_monitor
            && is_callable($this->_monitor)
            && call_user_func($this->_monitor, $str);
    }

    protected function _get_current_act()
    {
        return $this->_acts;
    }

    protected function _reset_acts()
    {
        $this->_acts = null;
        return $this;
    }

    protected function _add_act($act)
    {
        if ($this->_flag == self::ACT_FLAG_WAITING) {
            $this->_acts = new MysqlAction($act);
        } else {
            $this->_acts->update_type($act);
        }
        return $this;
    }

    protected function _add_to_act_info($key, $val)
    {
        $this->_acts->add_info($key, $val);
        return $this;
    }
}