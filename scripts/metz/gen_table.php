<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/init.php');
$args = getopt('d:a:');

print_r($args);
if (!isset($args['d']) || !isset($args['a'])) {
    echo 'USEAGE: ' . pathinfo(__FILE__)['filename'] . ' -d {dao} -a {app}' . PHP_EOL;
    exit;
}

$app = $args['a'];
$dao_name = $args['d'];

$dao_class = '\\Metz\\app\\' . $app . '\\dao\\' . ucfirst($dao_name);

$dao = new $dao_class;
$dao->generate_table();
