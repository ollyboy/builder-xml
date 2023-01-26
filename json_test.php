<?php
function arrayToCsv($array, $delimiter = ',', $enclosure = '"') {
    $csv = '';
    arrayToCsvHelper($array, '', $csv, $delimiter, $enclosure);
    return $csv;
}

function arrayToCsvHelper($array, $prefix, &$csv, $delimiter, $enclosure) {
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            arrayToCsvHelper($value, $prefix . $key . '.', $csv, $delimiter, $enclosure);
        } else {
            $csv .= $enclosure . $prefix . $key . $enclosure . $delimiter . $enclosure . $value . $enclosure . "\n";
        }
    }
}

$json = file_get_contents( $argv[1] );
$array = json_decode($json, true);
if($array === null) {
    echo json_last_error_msg();
}
//var_dump( $array );
$csv = arrayToCsv($array);
var_dump ( $csv);

