<?php
namespace Metz\app\metz;

use Metz\app\metz\exceptions\db;

abstract class Model extends \ArrayObject
{
    const CHANGE_NEW = 1;
    const CHANGE_UP  = 2;
    const CHANGE_DEL = 3;

    protected $_cur = null;
    protected $_changes = [
        self::CHANGE_NEW => [],
        self::CHANGE_UP  => [],
        self::CHANGE_DEL => [],
    ];
    protected $_enable_cache = true;
    protected $_ext_methods = null;

    abstract protected function _get_binding_dao_class();

    public function __construct($input = array(), $flags = 0, $iterator_class = "ArrayIterator")
    {
        parent::__construct($input, $flags, $iterator_class);
        empty($input) || $this->_changes[self::CHANGE_NEW] = $input;
    }

    protected function _reset()
    {
        $this->_cur = null;
        foreach ([self::CHANGE_NEW, self::CHANGE_DEL, self::CHANGE_DEL] as $_t) {
            $this->_reset_changes($_t);
        }
        return $this;
    }

    protected function _reset_changes($type)
    {
        $this->_changes[$type] = [];
        return $this;
    }

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
        return $this->_record_and_check($daos);
    }

    public function get_by($cond, $order = null, $page = 1, $page_count = 0)
    {
        DaoManager::manager()
            ->from($this->_get_binding_dao_class())
            ->filter_by($cond);
        $page_count == 0 || $DaoManager::manager()->paging($page, $page_count);
        $daos = DaoManager::manager()->get();
        return $this->_record_and_check($daos);
    }

    public function get_related($dao)
    {
        $daos = DaoManager::manager()
              ->from($this->_get_binding_dao_class())
              ->related($dao)
              ->get();
        return $this->_record_and_check($daos);
    }

    protected function _record_and_check($daos)
    {
        if (is_array($daos)) {
            if (isset($daos[0])) {
                foreach ($daos as $_dao) {
                    $this->_cur[] = $_dao->get_primary_val();
                }
            } else {
                return [];
            }
        } elseif ($daos) {
            $this->_cur = [$dao->get_primary_val()];
        } else {
            return [];
        }
        return $daos;
    }

    public function del($id, $reset = true)
    {
        if (empty($id)) {
            throw exceptions\db\UnexpectedInput('no id for delete');
        }
        $del = DaoManager::manager()
             ->from($this->_get_binding_dao_class())
             ->filter($id)
             ->del();
        $reset && $this->_reset();
        return $del;
    }

    public function del_by($cond, $reset = true)
    {
        $del = DaoManager::manager()
            ->from($this->_get_binding_dao_class())
            ->filter_by($cond)
            ->del();
        $reset && $this->_reset();
        return $del;
    }

    public function update($id, $data, $reset = true)
    {
        if (empty($id) || empty($data)) {
            throw exceptions\db\UnexpectedInput('empty id or data for updating');
        }
        $up = DaoManager::manager()
            ->from($this->_get_binding_dao_class())
            ->filter($id)
            ->update($data);
        $reset && $this->_reset();
        return $up;
    }

    public function update_by($cond, $data, $reset = true)
    {
        $up = DaoManager::manager()
            ->from($this->_get_binding_dao_class())
            ->filter_by($cond)
            ->update($data);
        $reset && $this->_reset();
        return $up;
    }

    public function create($data, $reset = true)
    {
        if (empty($data)) {
            throw exceptions\db\UnexpectedInput('empty create data');
        }
        $result = DaoManager::manager()
            ->from($this->_get_binding_dao_class())
            ->create($data);
        $reset && $this->_reset();
        return $result;
    }

    public function create_by_copy($dao, $up_vals = [])
    {
        $data = $dao->array_copy();
        $primary_key = $dao->get_primary_key();
        unset($data[$dao->$primary_key]);
        foreach ($up_vals as $_f => $_v) {
            $data[$_f] = $_v;
        }
        return $this->create($data);
    }

    public function enable_cache()
    {
        $this->_enable_cache = true;
        return $this;
    }

    public function disable_cache()
    {
        $this->_enable_cache = false;
        return $this;
    }

    public function reload($ids)
    {
        $this->reset();
        $this->_cur = $ids;
        return $this;
    }

    public function save()
    {
        if ($this->_enable_cache) {
            DaoManager::manager()->enable_cache();
        }
        if (!empty($this->_changes[self::CHANGE_NEW])) {
            $data = $this->_changes[self::CHANGE_NEW];
            $ids = $this->create($data, false);
            $this->cur = array_merge($this->cur, $ids);
            $this->_reset_changes(self::CHANGE_NEW);
        }
        if (!empty($this->_changes[self::CHANGE_UP])) {
            foreach ($this->_changes[self::CHANGE_UP] as $_id => $_data) {
                $this->update($_id, $_data, false);
            }
            $this->cur = array_merge($this->cur, array_keys($this->_changes[self::CHANGE_UP]));
            $this->_reset_changes(self::CHANGE_UP);
        }
        if (!empty($this->_changes[self::CHANGE_DEL])) {
            $this->del($this->_changes[self::CHANGE_DEL], false);
            $this->cur = array_diff($this->cur, $this->_changes[self::CHANGE_DEL]);
            $this->_reset_changes(self::CHANGE_DEL);
        }
        return $this;
    }

    protected function _create_ext_method($index)
    {
        $fname = is_array($_i)
               ? 'by_' . implode('_and_', $_i)
               : 'by_' . $_i;
        $this->_ext_methods['get_' . $fname] = $_i;
        $this->_ext_methods['up_' . $fname] = $_i;
        $this->_ext_methods['del_' . $fname] = $_i;
        return $this;
    }

    protected function _create_ext_methods()
    {
        if ($this->_ext_methods === null) {
            $this->_ext_methods = [];
            $indexes = DaoManager::manager()->get_dao_indexes($this->_get_binding_dao_class());
            foreach ($indexes as $_type => $_index) {
                foreach ($_index as $__i) {
                    $this->_create_ext_method($__i);
                }
            }
        }
        return $this;
    }

    protected function _run_ext_method($name, $args)
    {
        $argc = count($args);
        $field_count = count($this->_ext_methods[$name]);
        if (isset($this->_ext_methods[$name])) {
            if (strcmp($name, 'get_by_') == 0
                && $argc >= $field_count
            ) {
                $vals = array_slice($args, 0, $field_count);
                $cond = array_combine($this->_ext_methods[$name], $vals);
                $left = $field_count == $argc
                      ? []
                      : array_slice($args, $field_count, 3);
                array_unshift($left, $cond);
                return call_user_func_array([$this, 'get_by'], $left);
            } elseif (
                strcmp($name, 'up_by_') == 0
                && $argc == $field_count + 1
            ) {
                $data = array_shift($args);
                $cond = array_combine($this->_ext_methods[$name], $args);
                return call_user_func_array([$this, 'update_by'], $cond);
            } elseif (
                strcmp($name, 'del_by_') == 0
                && $argc == $field_count
            ) {
                $cond = array_combine($this->_ext_methods[$name], $args);
                return call_user_func_array([$this, 'del_by'], $cond);
            }
        }
        throw new \BadMethodCallException('no such method ' . get_class() . '::' . $name);
    }

    public function __call($name, $args)
    {
        $this->_create_ext_methods();
        return $this->_run_ext_method($name, $args);
    }

    // array objects
    public function append($val)
    {
        $this->_changes[self::CHANGE_NEW][] = $val;
    }

    public function asort()
    {
        // todo sort by property
        $this->ksort();
    }

    public function count()
    {
        $this->save();
        return count($cur);
    }

    public function exchangeArray($input)
    {
        $this->save();
        $input->save();
        $keys = array_keys($this);
        $this->reload(array_keys($input));
        $input->reload($keys);
    }
    
    public function getArrayCopy()
    {
        $this->save();
        $arr = [];
        foreach ($this->_cur as $_id) {
            $arr[$_id] = DaoManager::manager()->get(id)->getArrayCopy();
        }
        return $arr;
    }

    public function ArrayIterator()
    {
        // todo
    }

    public function ksort()
    {
        is_array($this->_cur) && $this->cur = asort($this->_cur);
    }

    public function offsetExists($index)
    {
        $this->get($index);
        return in_array($index, $this->_cur);
    }

    public function offsetGet($index)
    {
        return $this->get($index);
    }

    public function offsetSet($index, $value)
    {
        $this->_changes[self::CHANGE_NEW][$index] = $value;
    }
    
    public function offsetUnset($index)
    {
        $this->_changes[self::CHANGE_DEL][] = $index;
    }
}