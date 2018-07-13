<?php
namespace Metz\app\test\controller;

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