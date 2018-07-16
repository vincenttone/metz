<?php
namespace Metz\app\metz;

use Metz\app\metz\exceptions\db;

abstract class Model extends \ArrayObject
{
    abstract protected function _get_binding_dao_class();

    protected $_daos = [];

    public function get($id)
    {
        return DaoManager::manager()
              ->from($this->_get_binding_dao_class())
              ->filter($id)
              ->get();
    }

    public function get_by($cond, $sort = null, $page = 0, $count = 30)
    {
        return DaoManager::manager()
              ->from($this->_get_binding_dao_class())
              ->filter($id)
              ->get_all();
    }

}