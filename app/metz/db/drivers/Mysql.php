<?php
namespace Metz\app\metz\db\drivers;

use Metz\app\metz\db\Table;
use Metz\app\metz\exceptions;

class Mysql implements Driver
{
    protected $_conn = null;
    protected $_charset = 'utf8';
    protected $_engine = 'InnoDB';
    protected $_monitor = null;

    protected $_acts = null;

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
        $this->_add_act(MysqlAction::TYPE_QUERY);
        $this->_get_current_act()->set_fields($fields);
        return $this;
    }

    public function insert($data)
    {
        $this->_add_act(MysqlAction::TYPE_INSERT);
        $this->_get_current_act()->insert($data);
        return $this;
    }

    public function update($data)
    {
        $this->_add_act(MysqlAction::TYPE_UPDATE);
        $this->_get_current_act()->update($data);
        return $this;
    }

    public function delete()
    {
        return $this->_add_act(MysqlAction::TYPE_DELETE);
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

    public function on_conflict($keys = null)
    {
        $this->_get_current_act()->add_info(MysqlAction::INFO_KEY_ON_CONFLICT, $keys);
        return $this;
    }

    public function get()
    {
        $result = $this->get_all();
        if (is_array($result) && isset($result[0])) {
            return $result[0];
        }
        return null;
    }

    public function get_all()
    {
        $this->_add_act(MysqlAction::TYPE_SELECT);
        return $this->exec();
    }

    public function exec()
    {
        if (is_array($this->_acts)) { // transaction, not support now
        } else {
            $exec_info = $this->_acts->get_exec_info();
            $ret = $this->_prepare_and_run($exec_info['prepare_str'], $exec_info['data']);
            if ($ret) {
                switch ($exec_info['type']) {
                case MysqlAction::TYPE_INSERT:
                    return $this->_conn->LastInsertId();
                case MysqlAction::TYPE_SELECT:
                    return $ret->fetchAll();
                case MysqlAction::TYPE_UPSERT:
                case MysqlAction::TYPE_UPDATE:
                case MysqlAction::TYPE_DELETE:
                default:
                    return $ret->rowCount();
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
            switch($_i[Table::FIELD_INFO_TYPE]) {
            case Table::FIELD_TYPE_BOOL:
                $sql .= 'tinyint(1) ';
                break;
            case Table::FIELD_TYPE_INT:
                if (isset($_i[Table::FIELD_INFO_LENGTH])) {
                    if ($_i[Table::FIELD_INFO_LENGTH] < 4) {
                        $sql .= 'tinyint(' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                    } elseif ($_i[Table::FIELD_INFO_LENGTH] < 6) {
                        $sql .= 'smallint(' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                    } elseif ($_i[Table::FIELD_INFO_LENGTH] < 12) {
                        $sql .= 'int(' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                    } else {
                        $sql .= 'bigint(' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                    }
                } else {
                    $sql .= 'int';
                }
                isset($_i[Table::FIELD_INFO_UNSIGNED])
                    && $_i[Table::FIELD_INFO_UNSIGNED]
                    && $sql .= ' unsigned ';
                break;
            case Table::FIELD_TYPE_FLOAT:
                $sql .= 'float';
                if (isset($_i[Table::FIELD_INFO_LENGTH][1])) {
                    $sql .= '(' . $_i[Table::FIELD_INFO_LENGTH][1] . ', ' . $_i[Table::FIELD_INFO_LENGTH][1] . ') ';
                }
                isset($_i[Table::FIELD_INFO_UNSIGNED])
                    && $_i[Table::FIELD_INFO_UNSIGNED]
                    && $sql .= ' unsigned ';
                break;
            case Table::FIELD_TYPE_DOUBLE:
                $sql .= 'double';
                if (isset($_i[Table::FIELD_INFO_LENGTH][1])) {
                    $sql .= '(' . $_i[Table::FIELD_INFO_LENGTH][1] . ', ' . $_i[Table::FIELD_INFO_LENGTH][1] . ') ';
                }
                isset($_i[Table::FIELD_INFO_UNSIGNED])
                    && $_i[Table::FIELD_INFO_UNSIGNED]
                    && $sql .= ' unsigned ';
                break;
            case dao::FIELD_TYPE_CHAR:
                $sql .= 'char';
                if (isset($_i[Table::FIELD_INFO_LENGTH])) {
                    $sql .= '(' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                }
                break;
            case dao::FIELD_TYPE_VARCHAR:
                $sql .= 'varchar';
                if (isset($_i[Table::FIELD_INFO_LENGTH])) {
                    $sql .= '(' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                }
                break;
            case Table::FIELD_TYPE_TEXT:
                if (isset($_i[Table::FIELD_INFO_LENGTH])) {
                    if ($_i[Table::FIELD_INFO_LENGTH] < 256) {
                        $sql .= 'tinytext(' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                    } elseif ($_i[Table::FIELD_INFO_LENGTH] < 65536) {
                        $sql .= 'text(' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                    } elseif ($_i[Table::FIELD_INFO_LENGTH] < 16777216) {
                        $sql .= 'mediumtext (' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                    } else {
                        $sql .= 'longtext (' . $_i[Table::FIELD_INFO_LENGTH] . ') ';
                    }
                } else {
                    $sql .= 'text ';
                }
                break;
            case Table::FIELD_TYPE_DATE:
                $sql .= 'date ';
                break;
            case Table::FIELD_TYPE_TIME:
                $sql .= 'time ';
                break;
            case Table::FIELD_TYPE_DATETIME:
                $sql .= 'datetime ';
                break;
            case Table::FIELD_TYPE_TIMESTAMP:
                $sql .= 'timestamp ';
                break;
            }
            isset($_i[Table::FIELD_INFO_AUTO_INCREMENT])
                && $sql .= ' AUTO_INCREMENT ';
            isset($_i[Table::FIELD_INFO_NULLABLE])
                && $_i[Table::FIELD_INFO_NULLABLE] === false
                && $sql .= ' NOT NULL ';
            if (isset($_i[Table::FIELD_INFO_DEFAULT])) {
                $sql .= ' DEFAULT ? ';
                $data[] = $_i[Table::FIELD_INFO_DEFAULT];
            }
            $sql .= ',';
        }
        $sql .= ' PRIMARY KEY (' . $primary_key;
        $sql .= ')) ENGINE = ' . $this->_engine
             . ' DEFAULT CHARSET = ' . $this->_charset;
        $this->_prepare_and_run($sql, $data);
        return true;
    }

    public function create_indexes($table, $indexes)
    {
        if (isset($indexes[Table::INDEX_TYPE_COMMON][0])) {
            foreach ($indexes[Table::INDEX_TYPE_COMMON] as $_i) {
                $idx = is_array($_i) ? 'idx_' . implode('_', $_i) : 'idx_' .$_i;
                $sql = 'CREATE INDEX ' . $idx;
                $sql .= ' ON ' . $table;
                $sql .= is_array($_i) ? ' (' . implode(', ' , $_i) . ')' : '(' . $_i . ')';
                $this->_prepare_and_run($sql, []);
            }
        }
        if (isset($indexes[Table::INDEX_TYPE_UNIQ][0])) {
            foreach ($indexes[Table::INDEX_TYPE_UNIQ] as $_i) {
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
        $str = $exec === false ? 'Execute sql failed: ' : 'Execute sql success: ';
        $str .= $sth->queryString;
        empty($arr) || $str .= ', data: ' . json_encode($arr);
        $this->_monitor($str);
        if ($exec === false) {
            throw new exceptions\db\ExecuteFailed(
                'Execute failed, sql: ' . $sth->queryString
                . ', error info: ' . json_encode($sth->errorInfo())
            );
        }
        return $sth;
    }

    protected function _monitor($str)
    {
        $this->_monitor === null
                        || (is_callable($this->_monitor)
                            && call_user_func($this->_monitor, $str));
    }

    protected function _get_current_act()
    {
        $this->_add_act();
        return $this->_acts;
    }

    protected function _reset_acts()
    {
        $this->_acts = null;
        return $this;
    }

    protected function _add_act($act = null)
    {
        if ($this->_acts === null) {
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