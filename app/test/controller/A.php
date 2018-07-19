<?php
namespace Metz\app\test\controller;

use Metz\app\test\model\T1;

class A
{
    public function __construct()
    {
        echo 'Call: ' . get_class();
        echo '<br/>';
    }

    function x()
    {
        echo __METHOD__;
    }

    function y()
    {
        echo __METHOD__;
    }

    function z()
    {
        echo __METHOD__;
    }

    function index()
    {
        echo __METHOD__;
        $t1 = new T1();
        print_r($t1->get_all());
    }

    function get($id)
    {
        echo __METHOD__ . ' ' . $id;
    }

    function update($id)
    {
        echo __METHOD__ . ' ' . $id;
    }

    function delete($id)
    {
        echo __METHOD__ . ' ' . $id;
    }
}