<?php
namespace Metz\app\metz\db\drivers;

interface Driver
{
    public function connect($ip, $port, $username = null, $password = null, $db = null, $ext = []);
    public function disconnect();
    public function check_connection();
    public function select_db($db_name);

    public function set_table($table_name);
    public function select($fields = null);
    public function insert($data);
    public function update($data);
    public function del();
    public function count($fields = null);
    public function exists();
    
    public function where($cond);
    public function in($field, $arr);
    public function sort($fields);
    public function limit($count);
    public function offset($offset);
    public function on_conflict($keys);

    public function get();
    public function get_all();
    public function exec();

    // advanced function
    public function push($key, $data);
    public function pop($key);
    public function expire($sec, $key = null);
}