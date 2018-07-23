<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/init.php');
$args = getopt('t:a:');

print_r($args);
if (!isset($args['t']) || !isset($args['a'])) {
    echo 'USEAGE: ' . pathinfo(__FILE__)['filename'] . ' -t {table} -a {app}' . PHP_EOL;
    exit;
}

$app = $args['a'];
$table_name = $args['t'];

$table_class = '\\Metz\\app\\' . $app . '\\model\\' . ucfirst($table_name);

$table = new $table_class;
$table->create();
$table->create_index();
