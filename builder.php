<?php

ini_set("auto_detect_line_endings", true);
libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', 1); // Do send to output
ini_set('log_errors', 1 ); // send errors to log


$clientSource = get_support_barLin ( "client.source" ); // get the scope of work, returns empty if not found
if ( sizeof( $clientSource ) == 0 ) {
  print ( "ERROR Can't find essential work scope file: client.source\n" ); // will exit
  exit(0);
} 

// set flags and see if run is limited to a small set of jobs
//
$revisedClientSource=array();
foreach( $argv as $cnt => $v ) {
  $value =trim ( strtolower( $v )); 
  if ( $cnt == 0 ) {} // ignore
  elseif ( $value  == "production") $prodModeArgv = true; // Just generate hints if false
  elseif ( $value  == "development") $prodModeArgv = false; 
  else {
    $hit = false;
    foreach ( $clientSource as $scope ) {
      $parts = array_map ( 'trim' , explode ("|" , $scope ));
      if ( strtolower ( $parts[0] ) == $value ) { $revisedClientSource[] = $scope; $hit = true; }// names match
    }
    if ( ! $hit ) {
      print ( "ERROR Unknown command line parameter [" . $v . "]\n" );
      exit (0);
    }
  }
}
if ( count ( $revisedClientSource ) > 0 ) $clientSource = $revisedClientSource;  // shorter list taken from command line
//
// Get the Runway source data, make useful keys
$planList = "builder.plans.csv";
$plan1=array(); // reset
$combined = array();
build_plan_keys ( $planList, $plan1 );

foreach ( $clientSource as $scope ) {

  $parts = array_map ( 'trim' , explode ("|" , $scope ));
  $name = $parts[0];

  // sort order of map and data file is essential !
  $latestCsv = $name . ".latest.csv";
  $fieldMap =  "builder.field.map";
 
  //print ( "NOTE Stock size is " . count($combined) . " Key sets are " . count($plan1) . "\n");

  $builderData=array(); $priority=array();
  build_maxtix_from_csv ( $latestCsv , $mapArr , $priorty,
                          $builderData ); // set these

  print ( "NOTE Builder $name size is " . count( $builderData ) ."\n");

  $noMatch = match_plans ( $builderData , $plan1, // pass in these 
                           $combined ); // update this

  //print_r ( $combined );


  $i=0; $j=0; $noSolution = array();
  foreach ( $combined as $k => $v ) {

    if ( !isset($v[1]) && !isset($v[3]) && !isset($v[5]) ) {
      //print ( "No solution for " . $k . " [" . $v[0] . "]\n" ); $i++;
      $noSolution[$k] = $v;
    } elseif ( isset($v[1]) && !isset($v[3]) && !isset($v[5]) ) {
      //print ( "Single Addrs solution for $k " . $v[0] . "\n" ); 
    } elseif ( !isset($v[1]) && isset($v[3]) && !isset($v[5]) ) {
      //print ( "Single Full ID solution for $k " . $v[0] . "\n" );
    } elseif ( !isset($v[1]) && !isset($v[3]) && isset($v[5]) ) {
      //print ( "Single Part ID solution for $k " . $v[0] . "\n" );
    } else {
      unset ( $v[2][6] ) ; unset ( $v[4][6] ) ; unset ( $v[6][6] ) ; // dont compare 6 status
      //
      if ( isset($v[1]) && isset($v[5]) ) {
        if ( $v[2] != $v[6]) print ( "ERROR mismatch 2,6 \n");
      }
      if ( isset($v[1]) && isset($v[3]) && !isset($v[5]) ) {
        if ( $v[2] != $v[4]) print ( "ERROR mismatch 2,4 \n");
      }
      if ( isset($v[3]) && isset($v[5]) && isset($v[5]) ) {
        if ( $v[4] != $v[6]) print ( "ERROR mismatch 4,6 \n");
      }
    }
    $j++;
  }
  print ( "NOTE Total $j stock. $i with no solution\n");
  //print_r ( $noSolution );
  //print_r ( $noMatch );
  foreach ( $noMatch as $k => $v ){
    $tmp = explode ( "^" , $k );
    if ( isset ( $noMatchProj [ $tmp [0] ])) { $noMatchProj [ $tmp [0] ]++; }
    else { $noMatchProj [ $tmp [0] ] = 1;} 
  }
  foreach ( $noMatchProj as $k => $v ){
    if ( $v > 10 ) print ( "NOMATCH :: $k :: $v\n" );
  }
  foreach ( $noSolution as $k => $v ){
    print ( "NOSOL :: $k :: " . $v[0] . "\n" );
  }
}
// end of mainline


function brazoria_key_gen ( $owner, $subdivision , $legal , $block , $lot ) {
  // legal = POMONA SEC 15 (A0563 HT&BRR) BLK 1 LOT 14
  // *owner= POMONA PHASE 2A LLC,  POMONA PHASE 4  LLC
  // *owner^POMONA PHASE 2A LLC , street^COUNTY ROAD 84 , suffix^OFF , legal^A0417 A C H & B TRACT 15C ACRES 0.255 , acreage_val^2550 , *lot^15C
  // POMONA SEC 4 (A0298 HT&BRR & A0540 ACH&B) BLK 4 LOT 14 , *block^4 , *lot^14
  // *owner^POMONA PHASE 2A LLC , street^CROIX PKWY/COUNTY ROAD 84 , legal^A0417 A C H & B TRACT 27B ACRES 3.682 , acreage_val^36820 , *lot^27B
  // subdivision^COLONY NO 20 , *block^138 , *lot^3 , legal^COLONY NO 20 BLK 138 LOT 3
  $proj="na";
  $proj2="na"; // second/alt definition
  $proj3="na";
  $phase="na";
  $phase2="na";
  $phase3="na";
  $section="na";
  $maybe_block="na";
  $maybe_lot="na";

  $owner = strtoupper ( preg_replace("/[^0-9A-Z ]/", " "  , $owner ) );
  $subdivision = strtoupper ( preg_replace("/[^0-9A-Z ]/", " "  , $subdivision ) );
  $legal = strtoupper ( preg_replace("/[^0-9A-Z ]/", " "  , $legal ) );
  $block = strtoupper ( preg_replace("/[^0-9A-Z ]/", " "  , $block ) );
  $lot =   strtoupper ( preg_replace("/[^0-9A-Z )(\/]/", " "  ,   $lot ) );

  $subdivision = trim( preg_replace('!\s+!', ' ', $subdivision ));
  $owner = trim( preg_replace('!\s+!', ' ', $owner )); // convert multiple spaces to single
  $legal = trim( preg_replace('!\s+!', ' ', $legal ));
  $block = trim( preg_replace('!\s+!', ' ', $block ));
  $lot =   trim( preg_replace('!\s+!', ' ',   $lot ));
  //

  //print ( "---- $owner ---- $legal ---- $block ---- $lot ---- \n");
  $tmp = explode ( " " , $legal);
  foreach ( $tmp as $pos => $bit ) {
    if ( $bit == "SEC" ) {
      if ( isset ( $tmp[$pos + 1 ] )) { $section = $tmp[$pos + 1 ]; } else { $section = "out-of-range"; }// next word
      $proj="";
      for ( $i=0 ; $i<$pos ; $i++ ) $proj .= $tmp[$i] . " "; // all the words before section
      $proj = trim ( $proj );
      if ( $proj == "" ) $proj = "out-of-range";
    }
    if ( $bit == "PHASE" ) {
      if ( isset ( $tmp[$pos + 1 ] )) { $phase = $tmp[$pos + 1 ]; } else { $phase = "out-of-range"; }// next word
      $proj="";
      for ( $i=0 ; $i<$pos ; $i++ ) $proj .= $tmp[$i] . " "; // all the words before phase
      $proj = trim ( $proj );
      if ( $proj == "" ) $proj = "out-of-range";
    }
    if ( $bit == "BLK") if ( isset ( $tmp[$pos + 1 ] ))  { $maybe_block = $tmp[$pos + 1 ]; } else { $maybe_block = "out-of-range"; }
    if ( $bit == "LOT") if ( isset ( $tmp[$pos + 1 ] ))  { $maybe_lot   = $tmp[$pos + 1 ]; } else { $maybe_lot = "out-of-range"; }
  }

  $tmp = explode ( " " , $owner);
  foreach ( $tmp as $pos => $bit ) {
    if ( $bit == "PHASE" ) {
      if ( isset ( $tmp[$pos + 1 ] )) { $phase2 = $tmp[$pos + 1 ]; } else  { $phase2 = "out-of-range"; } // next word
      $proj2="";
      for ( $i=0 ; $i<$pos ; $i++ ) $proj2 .= $tmp[$i] . " "; // all the words before phase
      $proj2 = trim ( $proj2 );
      if ( $proj2 == "" ) $proj2 = "out-of-range2";
    }
  }

  $tmp = explode ( " " , str_replace ( " NO " , " " , $subdivision )); // ie subdivision^COLONY NO 14
  if ( count ($tmp) == 1 ) { 
    $proj3 = $tmp[0]; 
  } elseif ( count ($tmp) == 2 ) {
    if ( is_numeric($tmp[1] )) { $proj3 = $tmp[0]; $phase3 = $tmp[1]; }
    else { $proj3 = $tmp[0] . " " . $tmp[1]; }  
  } elseif ( count ($tmp) == 3 ) {
    $proj3 = $tmp[0] . " " . $tmp[1]; 
    $phase3 = $tmp[2];
  } elseif ( count ($tmp) == 4 ) {
    $proj3 = $tmp[0] . " " . $tmp[1] . " " . $tmp[2]; 
    $phase3 = $tmp[3]; // subdivision^GARDEN RIDGE ESTATES 1
  } 

  if ( ( $block == "" || $block == "na" ) &&  $maybe_block != "na" ) $block = $maybe_block;
  if ( ( $lot == "" || $lot == "na" ) &&  $maybe_lot != "na" ) $lot = $maybe_lot;
  //
  $tmp = array_map( "trim" , explode ( " " , trim($lot) ));
  if ( count ($tmp ) > 1) {
    $newLot = "";
    foreach ( $tmp as $bit ) {
      if ( strpos ( $bit , "TO") !== false ) { // ie 12TO27
        $tmp2 = str_replace("TO", " ", $bit );
        $tmp3 = explode ( " ", trim($tmp2) );
        if ( count ( $tmp3 ) == 2 ) {
          $tmp4 = range ( $tmp3[0], $tmp3[1]);
          if ( count ($tmp4) < 50 ) { 
            $bit=""; // reset as we will rebuild it
            foreach ( $tmp4 as $i ) $bit .= $i . ","; // can be 1-30 A-D but not A1-
          }
          $bit = rtrim ( $bit , "," );
        }
      }
      // only allow one letter or the "," string above
      if ( strlen( preg_replace("/[0-9]/","", $bit)) < 2 || strpos ( $bit , ",") !== false ) {
        $newLot .= $bit . ",";
      } else {
        //print ( "WARN bad lot $bit ref\n" );
      }
    }
    $lot = rtrim ( $newLot , "," );
    //print ( "multi-lot [" . $lot . "] is [" . $newLot . "]\n");
  }
  if ( $proj == "na")  $proj = $proj2; // try the alternative 
  if ( $phase == "na")  $phase = $phase2; 

  if ( $proj == "na")  $proj = $proj3; // and again
  if ( $phase == "na")  $phase = $phase3; 

return ( $proj ."^". $phase ."^". $section ."^". $block ."^". $lot );
}


function adj_map ( $fieldMap ) { // get the fieldmap, rotate to useful format
  //
  $map=array(); $mapArr=array();
  if ( !file_exists( $fieldMap )) { print ( "ERROR No map file $fieldMap found\n"); return ( $mapArr ); }
  $map = explode( "\n", file_get_contents( $fieldMap ));
  foreach ( $map as $k => $v ) { if ( strlen ( $v ) < 2 ) unset ($map[$k]); }  // get rid of junk
  //print_r ( $map ); // array of lines like 6,7,8,9|Plan|6|PlanNumber,PlanName
  foreach ( $map as $v ) {
	  $line = array_map ( "trim" , explode ( "|" , $v));
	  if ( count ( $line ) != 2) { print ( "Line $v is bad\n"); return ( $mapArr); }
	  $tmp2 = array_map ( "trim" , explode ( "," , $line[1] )); //  py_owner_name , appr_owner_name , legal_desc
	  foreach ( $tmp2 as $pos => $v2 ) {
		  $mapArr[$v2][0] = $line[0];
      $mapArr[$v2][1] = $pos;
	  }
  }
  foreach ( $mapArr as $k => $v ) {
    print ( "NOTE Source Key [$k] maps to [" . $v[0] . "] priority " . $v[1] . "\n" );
  }
  return ( $mapArr );
}


function build_plan_keys ( $planList, &$plan1 ) { // Get the Runway source data, make useful keys
 //
 //
 if ( !file_exists( $planList )) { print ( "ERROR No fixed Csv file $planList found\n"); return (0); }
 $file = fopen( $planList, 'r');
 $i=0;
 while (($line = fgetcsv($file,0,"|",'"',"\\")) !== FALSE) {
  //
  /*
  6462583460707486222648312180001543571599232224252|Available|Perry Homes|2997|Perry 55 - Pomona|61' 9.5"|490900|55'||2997
  5427103505110052438524321084028326531599232375663|Available|Perry Homes|2999|Perry 55 - Pomona|69' 4.6"|516900|55'||2999
  7442764071122001811146627814426341431563782391223|Available|Perry Homes|2999W|Perry Homes|69' 3.0"|516900|50'||2999W
  */
  if ( count ($line) > 8 ) {
    //
    $i++;
    //
    $ID =     trim( $line[0]); //$v['clientproductid'] ."|".
    $status = trim( $line[1]); // $v['currentstatusname'] ."|".
    $owner =  trim( $line[2]); // $v['ownername'] ."|".
    $design = trim( $line[3]); // $v['designproductname'] ."|".
    $range =  trim( $line[4]); // $v['rangeproductname'] ."|".
    $frontTxt = trim( $line[5]); // $v['productDepthFormatted'] ."|". // => 58' 1.0"
    $price =  trim( $line[6]); // $v['productprice'] ."|".   // => 279990
    $front =  trim( $line[7]); // $v['canfitonwidthFormatted'] ."|".
    $number = trim( $line[8]); // $v['productnumber'] ."|".
    $name =   trim( $line[9]); //  $v['productname'] . "\n" );
    //
    //
    if ( $status == "Available" ) {
      //
      if ( isset ( $builderPlans[ $owner ])) { $builderPlans[ $owner ]++; }
      else { $builderPlans[ $owner ] = 1; }
      
      // Homes is sometimes used and sometimes not ie [DR Horton] verses 
      $owner = str_replace ( " HOMES" , "" , strtoupper ( $owner ));

      // key should be $owner + name.
      $key = $owner . "^" . $name;
      //if ( $name != $design ) { print ( "WARN for $key plan design is $design\n"); }
      $key2 = $range . "^" . $front;
      if ( isset ( $plan1[$key]) ) { 
        //print ( "WARN duplicate owner+name key [$key] exists\n");
        if ( isset ( $plan1[$key][$key2] )) {
          print ( "ERROR duplicate owner+name+range+front key [$key][$key2]\n");
        } else {
          $plan1[$key][$key2]["price"] = $price;
          $plan1[$key][$key2]["front"] = $front;
          $plan1[$key][$key2]["design"] = $design;
          $plan1[$key][$key2]["status"] = "no-match";
        }
      } else {
        $plan1[$key][$key2]["price"] = $price;
        $plan1[$key][$key2]["front"] = $front;
        $plan1[$key][$key2]["design"] = $design;
        $plan1[$key][$key2]["status"] = "no-match";
      }
    } 
  } else {
   print ( "WARN Found short line $i in $planList\n");
  }
 }
 fclose($file);
 print ( "NOTE Found $i lines in $planList\n");
 //
 foreach ( $builderPlans as $k => $v ) {
   print ( "NOTE Builder [" . $k . "] has $v recs\n");
 }
 $multi=0; $uniq=0;
 foreach ( $plan1 as $k => $v ) {
   $count = count ( $v );
   if ( $count > 1 ) {
     $multi++;
     print ( "NOTE Builder/Plan [" . $k . "] has $count recs\n");
  } else {
     $uniq++;
  } 
 }
 print ( "NOTE Found $uniq unique Builder/Plan recs & $multi multi recs\n");

 return(1);
}

function build_maxtix_from_csv ( $latestCsv , $mapArr , &$matrix , &$priority ) {

  /* look for fixed key and value in latestCsv
  [0]                               [15]         [16]
   000000118181,MN,02020,,,,,,,,,,,,,assessed_val,000000000000000
   000000118181,MN,02020,,,,,,,,,,,,,land_acres,00000000000000000000
  */
  $old_key = ""; $key_cnt =1; $old_cnt = -1; $recs=1;
  if ( !file_exists( $latestCsv )) { print ( "ERROR No fixed Csv file $latestCsv found\n"); exit (0); }
  $file = fopen( $latestCsv, 'r');
  while (($line = fgetcsv($file)) !== FALSE) {
   if ( count ($line) > 2 ) {
   	  if ( count ($line) != 17 ) { print ( "ERROR Line $recs in $latestCsv is bad got " . count ($line) . "\n"); }
      else {
        // only work on lines with 17 fields ie 16 keys + value
   	    $key = "";
   	    for ( $i =0 ; $i < 15 ; $i++ ) { $key .= $line[$i]; } // re-create key
   	    $target = $line[15];
   	    $val = $line[16]; 
   	    //print ( "$key - $target - $val\n");
   	    if ( $old_key != $key ) {
   	 	  //print ( "New $key after $key_cnt\n"); 
   	 	  if ( $old_cnt != $key_cnt && $old_cnt > 1 ) { print ( "WARN Records in $latestCsv vary $old_cnt $key_cnt\n"); /*exit (0);*/ }
          $old_cnt = $key_cnt;
   	 	    $old_key = $key;
   	 	    $key_cnt = 1;
   	    }
   	    //process the record
        if ( isset ( $mapArr[$target] ) ) { 
          // we found source ie situs_unit etc
          $dest_tag = $mapArr[$target][0];
          $dest_priority = $mapArr[$target][1];
     	    //print ( "set $target with $val to " . $mapArr[$target] . "\n");
     	    if ( isset ( $matrix[ $key ][ $dest_tag ] ) ) { // we already have this value stored
            if ( isset ( $priority[ $key ][ $dest_tag ] ) &&  $priority[ $key ][ $dest_tag ] >  $dest_priority ) {
              // this is a higher priority value store
              print ( "NOTE Set higher $target with $val to $dest_tag\n");
              $matrix[ $key ][ $dest_tag ] = ltrim($val, "0"); // save the value
              $priority[ $key ][ $dest_tag ] = $dest_priority ; // save the value priority
            }
     		  // alreay have a value
     	    } else {
            if ( ltrim($val, "0") != ""  ) {
           	  $matrix[$key][$dest_tag ] = ltrim($val, "0"); // save the value
              $priority[$key][$dest_tag ] = $dest_priority ; // save the value priority
            }
          }
     	  }
     }
   	 $key_cnt++;
   	 $recs++;
   }
  }
  fclose($file);
  print ( "NOTE Matrix from $latestCsv done at $recs \n");
}

/*
owner^HAPPY GROUP LIMITED LIABILITY COMPANY , street^SMITH RANCH , suffix^RD , *city^PEARLAND , legal^A0304 H T & B R R BLOCK 1 TRACT 1 (PT) (PEARLAND OFFICE PARK) 4.7619% COMMON AREA BLDG 1 UNIT 103 , land_val^15100 , improved_val^57980 , appraised_val^73080 , assessed_val^73080 , acreage_val^3102 , *house^2743
*/

//print_r ( $matrix );

function match_stock ( $matrix , $plan1 , $plan2 , $plan3, &$combined ) {

 $noMatch = array();
 foreach ( $matrix as $k => $v ) {    // where $v is array [ dest_tag ] => $value $k is non-usable key

  //print ( "Processing [" . $k . "]\n");
  $good_line = false; $result="";

  foreach ( $v as $k2 => $v2 ) {
    if ( strpos( $k2 , "*" ) !== false ) {  $good_line = true; }// ie has important fields
    $result .= $k2 . "^" . $v2 . " , ";
  }
  //
  if ( isset ( $v["appraised_val"] )) $hasValues = true; // dont want records that dont have valuation
  else $hasValues = false;

  $testKey = ""; $hit=0; $found=false;
  if ( isset ( $v["*house"] )) { $testKey .= $v["*house"] ; $hit++; }
  if ( isset ( $v["prefix"] )) { $testKey .= " " . $v["prefix"] ; $hit++; }
  if ( isset ( $v["street"] )) { $testKey .= " " . $v["street"] ; $hit++; }
  if ( isset ( $v["suffix"] )) { $testKey .= " " . $v["suffix"] ; $hit++; }
  //if ( isset ( $v["*city" ] )) { $testKey .= " " . $v["*city"] ; $hit++; }

  if ( $hit >= 3 && $hasValues ) {
    $testKey = addr_conv ( $testKey );
    //print ( "trying [" . $testKey . "]\n");
    if ( isset ( $plan1 [$testKey])) {
      //print ( "Yay hit addrs for $testKey - " . $plan1 [$testKey][0] . "\n");
      $found=true;
      $plan1[ $testKey ][6] = "addrs-match";
      $fullAddr = $plan1[ $testKey ][0];
      if ( isset ( $combined [ $fullAddr ][1])) {
        print ( "ERROR match 1 - duplicate full address " . $fullAddr . "\n" );
      } else {
        $combined [ $fullAddr ][1] = $v; 
        $combined [ $fullAddr ][2] = $plan1 [$testKey];
      }
    }
  }
  
  $owner="" ; $legal="" ; $lot="" ; $block = "" ; $hit=0; $subdivision="";
  if ( isset ( $v["*owner"] )) { $owner = $v["*owner"]; $hit++; }
  if ( isset ( $v["*lot"] ))   { $lot =   $v["*lot"];   $hit++; }
  if ( isset ( $v["legal"] ))  { $legal = $v["legal"];  $hit++; }
  if ( isset ( $v["*block"] )) { $block = $v["*block"]; $hit++; }
  if ( isset ( $v["subdivision"] )) { $subdivision = $v["subdivision"]; $hit++; }
  //

  $out="";
  if ( $hit >= 3 && $hasValues /* && found == false */) {
    $out = brazoria_key_gen ( $owner, $subdivision, $legal , $block , $lot ); // $owner, $subdivision , $legal , $block , $lot 
    //print ( "trying [" . $out . "]\n");
    if ( isset ( $plan2 [ $out ])) {
      //print ( "Yay hit ID for $out - " . $plan2 [$out][0] . "\n");
      $found=true;
      if ( strpos ( $plan2[ $out ][6] , "no-match" ) !== false ) { $plan2[ $out ][6] = "ID-match"; }
      else { $plan2[ $out ][6] .= " , ID-match";}
      //
      $fullAddr = $plan2[ $out ][0];
      if ( isset ( $combined [ $fullAddr ][3]) ) { // not the [2] we are looking for dups here
        $cur = $combined [ $fullAddr ][3]["appraised_val"];
        $new = $v["appraised_val"];
        if ( $cur != $new) print ( "ERROR Diff apprasied $cur $new. Address " . $fullAddr . "\n" );
        //print ( "WARN match 2 duplicate full address " . $fullAddr . "\n" );
      } else {
        $combined [ $fullAddr ][3] = $v; 
        $combined [ $fullAddr ][4] = $plan2 [$out];
      }
    }
    if ( isset ( $plan3 [ $out ])) {
      //print ( "Yay hit ID for $out - " . $plan3 [$out][0] . "\n");
      $found=true;
      if ( strpos ( $plan3[ $out ][6] , "no-match" ) !== false ) { $plan3[ $out ][6] = "ID-match"; }
      else { $plan3[ $out ][6] .= " , ID-match";}
      //
      $fullAddr = $plan3[ $out ][0];
      if ( isset ( $combined [ $fullAddr ][5]) ) { // not the [2] we are looking for dups here
        print ( "WARN match 3 duplicate full address " . $fullAddr . "\n" );
        // TODO we get these due to dual ownership
        // [legal] => POMONA SEC 4 (A0298 HT&BRR & A0540 ACH&B) BLK 4 LOT 13, Undivided Interest 33.3400000000%
        //[appraised_val] => 114933
        //[assessed_val] => 114933)
        $cur = $combined [ $fullAddr ][5]["appraised_val"];
        $new = $v["appraised_val"];
        if ( $cur != $new) print ( "ERROR Diff apprasied $cur $new. Address " . $fullAddr . "\n" );
        //print_r ( $combined [ $fullAddr ][5] );
        //print_r ( $v );
      } else {
        $combined [ $fullAddr ][5] = $v; 
        $combined [ $fullAddr ][6] = $plan3 [$out];
      }
    }
  }
  if ( $found == false && $good_line ) { 
    $noMatch[$out] = $result; 
    //print ( "MISSED " . $result . " [" . $out . "]\n");
  }
 }
 return ( $noMatch );
}


function get_support_barLin ( $name ) { // process support files, bar delimited, multiple elements via comma delimiter

  $out=array();
  // get things like Perry.key.map , xml.client.source
  if ( !file_exists( $name )) return ( $out );
  $out = explode( "\n", file_get_contents( $name ));
  foreach ( $out as $k => $v ) if ( strlen ( $v ) < 2 ) unset ($out[$k]); // get rid of junk
  return ( $out ); // array of lines like 6,7,8,9|Plan|6|PlanNumber,PlanName
}

// end