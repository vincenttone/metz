<?php
namespace Metz\app\metz\db\drivers;

interface Driver
{
    public function connect($ip, $port, $db = null, $ext = []);
    public function disconnect();
    public function check_connection();
    public function select_db($db_name);
    public function set_table($table_name);

    public function select($fields = null);
    public function insert($data);
    public function update($data);
    public function upsert($data, $up, $conflict = []);
    public function del($cond);

    public function exists();
    public function count();
    public function where($cond);
    public function in($field, $arr);
    public function sort($fields);
    public function limit($count);
    public function offset($offset);

    public function get();
    public function get_all();
    public function commit();

    // advanced function
    public function push();
    public function pop();
}