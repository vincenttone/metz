<?php
namespace Metz\app\metz\db\drivers;

class Mysql implements Driver
{
    protected $_conn = null;
    protected $_charset = 'utf-8';

    protected $_acts = null;

    const ACT_FLAG_WAITING = 1;
    const ACT_FLAG_LOADING = 2;
    const ACT_FLAG_SEEKING_UPSERT = 3;
    const ACT_FLAG_SEEKING_CONFLICT = 4;

    public function connect($ip, $port, $user = null, $password = null, $db = null, $ext = [])
    {
        $dsn = 'mysql:host=' . $ip . ';port=' . $port;
        $db && $dsn .= ';dbname=' . $db;
        $this->_conn = new PDO($dsn, $username, $password);
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
        $this->_prepare_and_run('set names ?;', [$charset]);
        return $this;
    }

    public function check_connection()
    {
        return $this->set_charset($this->_charset);
    }

    public function select_db($db_name)
    {
        $this->_prepare_and_run('use ?;', [$db_name]);
        return $this;
    }

    public function set_table($table)
    {
        $this->_get_current_act()->set_table($table);
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

    public function exec()
    {
        if (is_array($this->_acts)) { // transaction, not support now
        } else {
            $exec_info = $this->_acts->get_exec_info();
            $this->_conn->prepare($exec_info['prepare_str']);
            return $this->_conn->exec($exec_info['data']);
        }
    }

    protected function _prepare_and_run($prepare_str, $arr)
    {
        $this->_conn->prepare($prepare_str);
        return $this->_conn->exec($arr);
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