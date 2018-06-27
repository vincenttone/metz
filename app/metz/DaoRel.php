<?php
namespace Metz\app\metz;

abstract class DaoRel
{
    public abstract function get_one_to_one_relations();
    public abstract function get_many_to_one_relations();
    public abstract function get_one_to_many_relations();
    public abstract function get_many_to_many_relations();
}