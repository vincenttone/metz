<?php
namespace Metz\app\metz;

class Home
{
    public function index()
    {
        echo '<pre>';
        echo 'welcome to Metz.';
        echo PHP_EOL;
        echo 'version: ';
        echo \Metz\sys\Constant::version_str();
        echo '</pre>';
    }
}