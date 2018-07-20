<?php
namespace Metz\app\test\model;

use Gaer\Model;

class T1 extends Model
{
    protected function _get_binding_table_class()
    {
        return \Metz\app\test\model\table\Test::class;
    }
}