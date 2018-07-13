<?php
namespace Metz\configure;

use Metz\app\metz\route\Restful as R;
use Metz\app\metz\route\Common as C;

class Route
{
    static function configure()
    {
        return [
            /*
            new R('/',                \Metz\app\test\controller\A::class, 'z'),
            new R('/call',            \Metz\app\test\controller\A::class, 'x'),
            new R('/call/some/where', \Metz\app\test\controller\A::class, 'y'),
            new R('/call/this',       \Metz\app\test\controller\A::class),
            new R('wall/li',          '\\Metz\\app\\test'),
            (new R('h1/del',          '\\Metz\\app\\test'))->set_match_success_hook(function($x) {$x->set_method('delete');}),
            (new R('h1/up',          '\\Metz\\app\\test'))->set_match_success_hook(function($x) {$x->set_method('update');}),
            new R('metz/app/test/a'),
            new C('/', \Metz\app\test\A::class),
            new C('metz/app/test'),
            new C('/c/play',           '\\Metz\\app\\test'),
            new C('/c/play/m',          \Metz\app\test\A::class, 'z'),
            */
            (new C('/',           '\\Metz\\app\\metz'))->disable_controller(),
            // new R(RESTFUL_URI, ROUTE_CLASS, ROUTE_METHOD),
            // new C(COMMON_URI, ROUTE_CLASS, ROUTE_METHOD),
        ];
    }
}