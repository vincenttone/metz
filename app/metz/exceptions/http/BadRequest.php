<?php
namespace Metz\app\metz\exceptions\http;

class BadRequest extends Http
{
    protected $code = 400;
}