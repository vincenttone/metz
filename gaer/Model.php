<?php
namespace Gaer;

use Gaer\exceptions\db;

abstract class Model extends \ArrayObject
{
    abstract protected function _get_binding_table_class();

    public function count($conds = null)
    {
        return $this->_get_table_instance()->count($conds);
    }

    public function get($id)
    {
        return $this->_get_table_instance()->get($id);
    }

    public function get_all($conds = [], $page = 0, $count = 30, $sort = null)
    {
        $offset = $count * ($page - 1);
        return $this->_get_table_instance()->get_by($conds, $sort, $offset, $count);
    }

    public function insert($data)
    {
        return $this->_get_table_instance()->insert($data);
    }

    public function upsert($data)
    {
        return $this->_get_table_instance()->upsert($data);
    }

    public function update($data, $conds)
    {
        return $this->_get_table_instance()->update($data, $conds);
    }

    public function delete($conds)
    {
        return $this->_get_table_instance()->delete($conds);
    }

    protected function _get_table_instance()
    {
        $kls = $this->_get_binding_table_class();
        return $kls::get_instance();
    }
}