<?php
namespace Metz\app\metz;

use Metz\app\metz\exceptions\db;

abstract class Model extends \ArrayObject
{
    abstract protected function _get_binding_dao_class();

    protected $_daos = [];

    public function get($id)
    {
        if (!$id) {
            // do not get after save at master-slave db
            $id = $this->_cur;
        }
        $daos = DaoManager::manager()
              ->from($this->_get_binding_dao_class())
              ->filter($id)
              ->get();
        if ($daos) {
            $this->_daos[] = $daos->get_primary_val();
        }
        return null;
    }
}