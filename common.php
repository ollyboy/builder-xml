  <?php

// convert to common format

// sort order of map and data file is essentual !

ini_set("auto_detect_line_endings", true);

libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', 1); // Do send to output
ini_set('log_errors', 1 ); // send errors to log

$name = "global.field.map";
$map=array();
if ( !file_exists( $name )) { print ( "no map file $name\n"); exit (0); }
$map = explode( "\n", file_get_contents( $name ));
foreach ( $map as $k => $v ) if ( strlen ( $v ) < 2 ) unset ($map[$k]); // get rid of junk
//print_r ( $map ); // array of lines like 6,7,8,9|Plan|6|PlanNumber,PlanName
foreach ( $map as $v ) {
	$line = array_map ( "trim" , explode ( "|" , $v));
	if ( count ( $line ) != 2) { print ( "Line $v is bad\n"); exit (0); }
	$tmp2 = array_map ( "trim" , explode ( "," , $line[1] )); //  py_owner_name , appr_owner_name , legal_desc
	foreach ( $tmp2 as $v2 ) {
		$map2[$v2] = $line[0];
	}
}
print_r ( $map2 );

/* look for fixed key and value
[0]                               [15]         [16]
000000118181,MN,02020,,,,,,,,,,,,,assessed_val,000000000000000
000000118181,MN,02020,,,,,,,,,,,,,land_acres,00000000000000000000
*/

$matrix=array();
$old_key = ""; $key_cnt =1; $old_cnt = -1; $recs=1;
$file = fopen('Brazoria.latest.csv', 'r');
while (($line = fgetcsv($file)) !== FALSE) {
   if ( count ($line) > 2 ) {
   	 if ( count ($line) != 17 ) { print ( "Line is bad got " . count ($line) . "\n"); exit (0); }
   	 $key = "";
   	 for ( $i =0 ; $i < 15 ; $i++ ) { $key .= $line[$i]; } // re-creat key
   	 $target = $line[15];
   	 $val = $line[16]; 
   	 //print ( "$key - $target - $val\n");
   	 if ( $old_key != $key ) {
   	 	//print ( "New $key after $key_cnt\n"); 
   	 	if ( $old_cnt != $key_cnt && $old_cnt > 1 ) { print ( "Records vary $old_cnt $key_cnt\n"); /*exit (0);*/ }
        $old_cnt = $key_cnt;
   	 	$old_key = $key;
   	 	$key_cnt = 1;
   	 }
   	 //process
     if ( isset ( $map2[$target])) {
     	//print ( "set $target with $val to " . $map2[$target] . "\n");
     	if ( isset ( $matrix[$key][$map2[$target]] ) ) {
     		// alreay have a value
     	} else {
           if ( ltrim($val, "0") != ""  ) {
           	 $matrix[$key][$map2[$target]] = ltrim($val, "0");
           }
     	}
     }
   	 $key_cnt++;
   	 $recs++;

   }
}
fclose($file);


foreach ( $matrix as $k => $v ) {

  }
print ( "Done at $recs \n");


// end