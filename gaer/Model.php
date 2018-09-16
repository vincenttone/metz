<?php
namespace Gaer;

use Gaer\exceptions;

abstract class Model extends \ArrayObject
{
    abstract protected function _get_binding_dao_class();

    protected static function _related_daos()
    {
        return [
            // daoClass => ['key' => [$binding_key1 => $key1, ...], 'type' => '1-1 or 1-more']
        ];
    }

    protected static $_table = null;

    public function count($conds = null)
    {
        return $this->_get_table_instance()->count($conds);
    }

    public function get($id)
    {
        $kls = $this->_get_binding_dao_class();
        return new $kls($id);
    }

    public function get_all($conds = [], $page = 1, $count = 30, $sort = null)
    {
        $offset = $count * ($page - 1);
        $data = $this->_get_table_instance()->get_by($conds, $sort, $offset, $count);
        $daos = [];
        foreach ($data as $_d) {
            $kls = $this->_get_binding_dao_class();
            $daos[] = new $kls(null, $_d);
        }
        return $daos;
    }

    public function insert($data)
    {
        $input = self::_format_input_data($data);
        return $this->_get_table_instance()->insert($input);
    }

    public function upsert($data)
    {
        $input = self::_format_input_data($data);
        return $this->_get_table_instance()->upsert($input);
    }

    public function update($data, $conds)
    {
        $input = self::_format_input_data($data);
        return $this->_get_table_instance()->update($input, $conds);
    }

    public function delete($conds)
    {
        return $this->_get_table_instance()->delete($conds);
    }

    protected static function _format_input_data($data)
    {
        if (is_array($data)) {
            $first = reset($data);
            if (is_object($first)) {
                $input = [];
                foreach ($data as $_o) {
                    $input[] = $_o->array_copy();
                }
            } else {
                $input = $data;
            }
        } elseif (is_object($_o)) {
            $input = $_o->array_copy();
        } else {
            throw new exceptions\input\ParamsUnexpectType();
        }
        return $input;
    }

    protected function _get_table_instance()
    {
        if (!self::$_table) {
            $dao_kls = $this->_get_binding_dao_class();
            $dao = new $dao_kls;
            self::$_table = $dao->get_table();
        }
        return self::$_table;
    }
}