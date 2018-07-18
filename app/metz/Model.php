<?php
namespace Metz\app\metz;

use Metz\app\metz\exceptions\db;

abstract class Model extends \ArrayObject
{
    abstract protected function _get_binding_dao_class();

    protected $_daos = [];

    public function get($id)
    {
        return $this->_get_dao_manager()->get($id);
    }

    public function get_by($conds, $page = 0, $count = 30, $sort = null)
    {
        $omg = $this->_get_dao_manager()
             ->filter_by($cond)
             ->paging($page, $count);
        if ($sort) {
            foreach ($sort as $_s) {
                $omg->sort($_s[0], $_s[1]);
            }
        }
        return $omg->get_all();
    }

    public function update($conds)
    {
        return $this->_get_dao_manager()->update($conds);
    }

    public function delete($conds)
    {
        return $this->_get_dao_manager()->delete($conds);
    }

    protected function _get_dao_manager()
    {
        return DaoManager::manager()
            ->from($this->_get_binding_dao_class());
    }
}