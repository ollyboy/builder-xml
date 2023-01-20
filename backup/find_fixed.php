<?php

foreach($argv as $value)
{
  echo "$value\n";
  $line_counter = 0; 
  $max_line = 200000;
  $matrix_pos = array();
  $matrix_diff = array();
  $content = array();
  //
  $fh = fopen( $value,'r') or die($php_errormsg); 
  while ((! feof($fh)) && ($line_counter <= $max_line)) { 
    if ($in = fgets($fh,1048576)) { 
      $content[ $line_counter ] = $in;
      for($i=0; $i<strlen($in); $i++) {
        if (( $i > 0 && $in[$i - 1] == " " &&  $in[$i] != " " ) || $i == 0 ) {
          //$key = $line_counter . ":" . $i;
          $key = $i;
          if ( isset ( $matrix_pos[$key])) $matrix_pos[$key]++; // count the occurence of " X"
          else $matrix_pos[$key] = 1;
          if ( isset ( $in[$i+1] )) $next = $in[$i+1]; else $next = " ";
          $data = $in[$i] . $next;
          if ( isset ( $matrix_diff[$key])) $matrix_diff[$key] .= "^" . $data; // make a sample string
          else $matrix_diff[$key] = $data;
        }
      }
      $line_counter++; 
    } 
  } 
  fclose($fh) or die($php_errormsg);
  
  print_r ( $matrix_pos );
  print_r ( $matrix_diff);
}

?>