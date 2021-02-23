<?php

// brazoria fixed file convert



ini_set("auto_detect_line_endings", true);

echo ( "Reading template\n" );

// prop_type_cd,char(5),13,17,5,Property Type Code:
$file = fopen("brazoria.template.csv", 'r'); // expect col-name, col-type, start-pos, end-pos , desc
//
$i=0;
while (($line = fgetcsv($file)) !== FALSE) {
  //$line is an array of the csv elements
  if ( sizeof ($line ) > 1 ) {
    $colname[$i]=trim($line[0]);
    // one is type
    $start[$i]=intval ( trim($line[2])) - 1 ;
    $end[$i]=intval ( trim($line[3])) - 1 ;
    $desc[$i]=trim($line[5]);

    if ( sizeof ( $line ) != 6 ) { echo ( "ERROR at $i " . implode ( " | " , $line ) . "\n"); }
    else { echo ( "Inc at $i [". $colname[$i] ."] at " . $start[$i] . " " . $end[$i] ."\n"); }
    $i++;

  } else { 
    echo ( "ERROR Skipped [". implode ( " | " , $line ) ."] could miss fields!\n"); 
  }
}
fclose($file);

echo ( "Converting\n" );

$size = sizeof ( $start ) ;
$file = fopen("brazoria.txt", 'r');
$cnt=0;
while (($line = fgets($file)) !== FALSE) {
  //$line is an array of the csv elements
  for ( $i=0 ; $i < $size ; $i++ ) {
    $output[$cnt][$i] = ltrim( trim( substr ( $line , $start[$i] , $end[$i] - $start[$i] )) , "0" );
  }
  $cnt++;
}

echo ( "Writing\n" );

$file = fopen("brazoria.barcsv", 'w');
fputcsv ( $file , $colname , "|" , '"' , "\\" );
foreach ( $output as $cnt => $lineArr ) {
  fputcsv ( $file , $lineArr, "|" , '"' , "\\" );
}
fclose($file);

//end