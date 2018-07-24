<?php
namespace Gaer\db;

use Gaer\exceptions\db;

use Gaer\db\drivers;

class Connection implements \JsonSerializable
{
    protected $_driver = null;
    protected $_dirver_name = null;
    protected $_ip = null;
    protected $_port = null;
    protected $_user = 'root';
    protected $_password = '';
    protected $_ext = [];
    protected $_auth_mode = null;
    protected $_db_name = null;
    protected $_connection = null;

    public function __construct($driver, $ip, $port, $user, $password, $db_name = null, $ext = [])
    {
        $this->_select_driver($driver)
            ->_set_ip($ip)
            ->_set_port($port)
            ->_set_user($user)
            ->_set_password($password);
        $db_name && $this->_set_db_name($db_name);
        empty($ext) || $this->_ext = $ext;
        $this->connect();
    }

    public function __destruct()
    {
        $this->_driver->disconnect();
    }

    public function connect()
    {
        if ($this->_connection === null) {
            $this->_connection = $this->_driver->connect(
                $this->_ip,
                $this->_port,
                $this->_user,
                $this->_password,
                $this->_db_name,
                $this->_ext
            );
        } else {
            $this->_check_connection();
        }
        $this->_db_name && $this->select_db($this->_db_name);
        return $this;
    }

    protected function _check_connection()
    {
        if ($this->_connection === null) {
            $this->_connection = $this->_driver->connect($this->_ip, $this->_port, $ext);
            return true;
        }
        return $this->_driver->check_connection();
    }

    protected function _set_db_name($db_name)
    {
        if (!is_string($db_name) || !$db_name) {
            throw new exception\UnexpectedInput(
                'unexpect db name: ' . var_export($db_name, true)
            );
        }
        $this->_db_name = $db_name;
        return $this;
    }

    protected function _set_ip($ip)
    {
        if (!is_string($ip) || !$ip) {
            throw new exception\UnexpectedInput(
                'unexpect ip address: ' . var_export($ip, true)
            );
        }
        $this->_ip = $ip;
        return $this;
    }

    protected function _set_port($port)
    {
        if (!is_string($port) || !$port) {
            throw new exception\UnexpectedInput(
                'unexpect port: ' . var_export($port, true)
            );
        }
        $this->_port = $port;
        return $this;
    }

    protected function _set_user($user)
    {
        $this->_user = $user;
        return $this;
    }

    protected function _set_password($password)
    {
        $this->_password = $password;
        return $this;
    }

    protected function _select_driver($driver)
    {
        if (!is_string($driver)
            || !$driver
            || empty(Driver::driver_class($driver))
        ) {
            throw new exception\UnexpectedInput(
                'unsupport db driver: ' . var_export($driver, true)
                . ', supporting: ' . json_encode(Driver::supporting_list())
            );
        }
        $driver_class = Driver::driver_class($driver);
        $this->_driver_name = $driver;
        $this->_driver = new $driver_class;
        return $this;
    }

    public function jsonSerialize()
    {
        return [
            'driver' => $this->_driver_name,
            'ip' => $this->_ip,
            'port' => $this->_port,
            'db' => $this->_db_name,
            'ext' => $this->ext,
        ];
    }

    public function __call($name, $args)
    {
        if (method_exists($this->_driver, $name)) {
            return call_user_func_array([$this->_driver, $name], $args);
        }
        throw new \BadMethodCallException('unexpect method: ' . $name . ' in class: ' . get_class());
    }
}