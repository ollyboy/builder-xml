<?php

// brazoria fixed file convert

ini_set("auto_detect_line_endings", true);
libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', 1); // Do send to output
ini_set('log_errors', 1 ); // send errors to log

ini_set('memory_limit', '4096M');

if ( file_exists( "Brazoria.template.csv" ) && file_exists( "Brazoria.txt" ) ) {
  echo ( "NOTE: Good to Start convert of Brazoria.txt to Brazoria.csvbar \n"); 
} else {
  echo ( "ERROR: Missing Brazoria.txt or Brazoria.template.csv. Cant generate Brazoria.csvbar \n"); 
  exit( false );
}

// prop_type_cd,char(5),13,17,5,Property Type Code:
$file = fopen("Brazoria.template.csv", 'r'); // expect col-name, col-type, start-pos, end-pos , desc
//
$i=0; $j=0;
while (($str = fgets($file)) !== FALSE) {
  $line = explode ( "," , $str );
  //$line is an array of the csv elements
  if ( sizeof ($line ) > 1 ) {
    $colname[$i]=trim($line[0]);
    // one is type
    $start[$i]=intval ( trim($line[2])) - 1 ;
    $end[$i]=intval ( trim($line[3])) - 1 ;
    $desc[$i]=trim($line[5]);
    for ( $l=6 ; $l <= count ( $line ) ; $l++ ) {
      if ( isset ( $line[$l] )) echo ( "ERROR Looks like bad csv [" .  preg_replace('/[^A-Za-z0-9\-]/', '~', $line[$l] ) . "]\n" ); 
    }
    if ( sizeof ( $line ) < 6 ) { echo ( "ERROR at $i " . implode ( " | " , $line ) . "\n"); }
    else { 
      echo ( "NOTE: Inc at " . ( $i + 1 ) . " [". $colname[$i] ."] at position " . $start[$i] . " to " . $end[$i] . " Desc:". $desc[$i] . "\n"); 
      $i++;
    }
  } else { 
    echo ( "ERROR Skipped [". implode ( " | " , $line ) ."] could miss fields!\n"); 
  }
  $j++;
}
fclose($file);
$size = $i;

if ( $i == $j ) echo ( "Converting with $size col-names from template with $j records\n" );
else echo ( "ERROR Converting with $size col-names from template with $j records\n" );

$file = fopen("Brazoria.txt", 'r');
$cnt=0;
while (($line = fgets($file)) !== FALSE) {
  //$line is an array of the csv elements
  for ( $i=0 ; $i < $size ; $i++ ) {
    if ( strlen ( $line) < $end[$i] ) echo ( "ERROR Can't process Line $cnt not same. Found " . strlen ( $line) ." chars\n" );
    // prop_val_yr,numeric(5),18,22,5,Appraisal or Tax Year   - thats 5 chars but 22-18 is 4 
    $output[$cnt][$i] = trim( substr ( $line , $start[$i] , $end[$i] - $start[$i] + 1 )); 
  }
  $cnt++;
}

echo ( "Writing $cnt records\n" );

$file = fopen("Brazoria.csvbar", 'w');
$colCount = count ( $colname );
fputcsv ( $file , $colname , "|" , '"' , "\\" );
foreach ( $output as $cnt => $lineArr ) {
  fputcsv ( $file , $lineArr, "|" , '"' , "\\" );
  if ( $colCount != count ( $lineArr )) echo ( "ERROR output data does not match header\n");
}
fclose($file);

echo ( "Writing complete..\n" );

//end