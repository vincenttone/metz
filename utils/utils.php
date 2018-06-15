<?php
/**
 * Print the information, using for debug
 */
function p()
{
    $file = '';
    $line = '';
    $end_of_line = '<br/>'.PHP_EOL;
    php_sapi_name() == 'cli' && $end_of_line = PHP_EOL;
    if (true) {
        $trace = debug_backtrace();
        if (isset($trace[0])) {
            isset($trace[0]['file']) && $file = $trace[0]['file'];
            isset($trace[0]['line']) && $line = $trace[0]['line'];
        }
    }
    $head_line = str_repeat('-', 8).' FILE:['.$file.'] -- LINE: ['.$line.'] '.str_repeat('-', 8).$end_of_line;
    $dec_line = str_repeat('-', strlen($head_line) - 1).$end_of_line;
    echo $dec_line;
    echo $head_line;
    echo $dec_line;
    $args = func_get_args();
    foreach($args as $_arg) {
        if (empty($_arg) || is_bool($_arg)) {
            var_dump($_arg);
        } elseif (is_scalar($_arg)) {
            echo $_arg;
            echo $end_of_line;
        } else {
            print_r($_arg);
            echo $end_of_line;
        }
    }
    echo str_repeat('=', strlen($head_line) - 1).$end_of_line;
}