<?php
namespace Metz\app\test;

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
        echo __METHOD__;
    }

    function update($id)
    {
        echo __METHOD__;
    }

    function delete($id)
    {
        echo __METHOD__;
    }
}