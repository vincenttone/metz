<?php
namespace Metz\app\metz;

use Metz\app\metz\db\Table;

class DaoManager
{
    private static $_instance = null;
    // contrl
    protected $_enable_cache = true;
    // daos
    protected $_sample = [];
    protected $_daos = [];
    protected $_rels = [];
    // query
    protected $_dao_cls = null;
    protected $_conds = [];
    protected $_in = [];
    protected $_sort = [];
    protected $_from = 0;
    protected $_to = 0;

    private function __construct()
    {
    }

    private function __clone()
    {
        throw new \Exception('clone failed!');
    }

    public static function manager()
    {
        if (self::$_instance === null) {
            $kls = get_class();
            self::$_instance = new $kls();
        }
        return self::$_instance;
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

    public function count($conds = null)
    {
        $table = $this->_get_dao_instance($this->_dao_cls)
               ->get_table();
        $conds && $table->where($conds);
        if (!empty($this->_conds)) {
            $table->where($this->_conds);
            $this->_conds = [];
        }
        if (!empty($this->_in)) {
            foreach ($this->_in as $_f => $_in) {
                $table->in($_f, $_in);
            }
        }
        return $table->count();
    }

    public function get($id = null)
    {
        $id && $this->filter($id);
        $daos = $this->get_all();
        if (empty($daos)) {
            return null;
        }
        return reset($daos);
    }

    public function get_all($conds = null)
    {
        $table = $this->_get_dao_instance($this->_dao_cls)
               ->get_table();
        $conds && $table->where($conds);
        if (!empty($this->_conds)) {
            $table->where($this->_conds);
            $this->_conds = [];
        }
        if (!empty($this->_in)) {
            foreach ($this->_in as $_f => $_in) {
                $table->in($_f, $_in);
            }
        }
        $this->_from > 0 && $table->offset($this->_from);
        $this->_to > 0 && $table->limit($this->_to - $this->_from);
        $this->_sort && $table->sort($this->_sort);
        $data = $table->get_all();
        $daos = [];
        foreach ($data as $_d) {
            $daos[] = new Dao($_d[], $_d);
        }
        return $daos;
    }

    public function update($conds = null)
    {
        $table = $this->_get_table();
        $conds && $table->where($conds);
        if (!empty($this->_conds)) {
            $table->where($this->_conds);
            $this->_conds = [];
        }
        return $table->update();
    }

    public function delete($conds = null)
    {
        $table = $this->_get_table();
        $conds && $table->where($conds);
        if (!empty($this->_conds)) {
            $table->where($this->_conds);
            $this->_conds = [];
        }
        return $table->delete();
    }

    public function from($dao_cls)
    {
        $this->_dao_cls = $dao_cls;
        return $this;
    }

    public function filter($id)
    {
        $primary_key = $this->_get_primary_key($this->_dao_cls);
        if (is_array($id)) {
            if (isset($this->_in[$primary_key])) {
                $this->_in[$primary_key][] = id;
            } else {
                $this->_in[$primary_key] = [id];
            }
        } else {
            $this->_cond[] = [$primary_key => $id];
        }
        return $this;
    }

    public function filter_by($cond)
    {
        $this->_cond = array_merge($this->_cond, $cond);
        return $this;
    }
    /**
     * @desc paging
     * @param $num
     * @param $per=30
     * @return $this
     */
    public function paging($page, $per = 30)
    {
        $this->_from = $per * ($page - 1);
        $this->_to = $per * $page;
        return $this;
    }
    /**
     * @desc sort data
     * @param $field
     * @param $direct
     * @return $this
     */
    public function sort($field, $direct)
    {
        $this->_sort[$field] = $direct;
        return $this;
    }
    /**
     * @desc in query
     * @param $key
     * @param $vals
     * @return $this;
     */
    public function in($key, $vals)
    {
        if (isset($this->_in[$key])) {
            if (is_array($vals)) {
                $this->_in[$key] = array_merge($vals, $this->_in[$key]);
            } else {
                $this->_in[$key][] = $vals;
            }
        } else {
            if (is_array($vals)) {
                $this->_in[$key] = $vals;
            } else {
                $this->_in[$key] = [$vals];
            }
        }
        return $this;
    }
    /**
     * @desc filter related data 
     * @param $target
     * @return $this
     */
    public function related($target)
    {
        if (is_array($target)) {
            foreach ($target as $_t) {
                $this->related($_t);
            }
        } else {
            $primary = $target->get_primary_key();
            if ($target->$primary == null) {
                throw new \Exception('no primary val');
            }
            $key = $this->_get_related_key(get_class($target), $this->_dao_cls);
            $this->in($key, $target->$primary);
        }
        return $this;
    }

    public function get_dao_indexes($dao_cls)
    {
        $ins = $this->_get_dao_instance($dao_cls);
        return $ins->get_indexes();
    }

    protected function _get_dao_instance($dao_cls)
    {
        isset($this->_sample[$dao_cls])
            || $this->_sample[$dao_cls] = new $dao_cls;
        return $this->_sample[$dao_cls];
    }

    protected function _get_primary_key($dao_cls)
    {
        $ins = $this->_get_dao_instance($dao_cls);
         return $ins->get_primary_key();
    }

    protected function _get_related_key($current_cls, $target_cls)
    {
        $current_dao = $this->_get_dao_instance($current_cls);
        $target_dao = $this->_get_dao_instance($target_cls);
        return $target_dao->get_related_key($current_cls, $current_dao->get_primary_key());
    }

    protected function _get_table()
    {
        return $this->_get_dao_instance($this->_dao_cls)
            ->get_table();
    }
}