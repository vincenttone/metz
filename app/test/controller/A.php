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
        echo json_encode($t1->get_all());
    }

    function create()
    {
        echo __METHOD__;
        try {
            $t1 = new T1();
            $id = $t1->insert(
                [
                    'a1' => time(),
                    'b1' => 'test',
                    'c1' => 3,
                    'a2' => 6,
                ]
            );
            echo 'Insert success, id: ' . $id;
        } catch (\Exception $ex) {
            print_r($ex->getMessage());
            throw $ex;
        }
    }

    function get($id)
    {
        echo __METHOD__ . ' ' . $id;
        $t1 = new T1();
        $dao = $t1->get($id);
        echo json_encode($dao);
    }

    function update($id)
    {
        echo __METHOD__ . ' ' . $id;
        $t1 = new T1();
        $rows = $t1->update(
            [
                'a1' => time(),
                'b1' => 'test',
                'c1' => 3,
            ],
            $id
        );
        var_dump($rows);
    }

    function delete($id)
    {
        echo __METHOD__ . ' ' . $id;
        $t1 = new T1();
        $rows = $t1->delete($id);
        var_dump($rows);
    }
}