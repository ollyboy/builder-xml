<?php

$json = file_get_contents( $argv[1] );
$array = json_decode($json, true);
if($array === null) {
    echo json_last_error_msg();
}
var_dump( $array );
