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
        echo 'x';
    }

    function y()
    {
        echo 'y';
    }

    function z()
    {
        echo 'z';
    }

    function index()
    {
        echo 'list';
    }

    function get($id)
    {
        echo 'get ' . $id;
    }

    function update($id)
    {
        echo 'up ' . $id;
    }

    function delete($id)
    {
        echo 'del ' . $id;
    }
}