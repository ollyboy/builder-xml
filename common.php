<?php

ini_set("auto_detect_line_endings", true);
libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', 1); // Do send to output
ini_set('log_errors', 1 ); // send errors to log

/*
000000687740|R|02020|000000000000||||7100-0121-010|000001158936|"POMONA PHASE 4  LLC"|F|000000000000||"9800 HILLWOOD PKWY"|"STE 300"|"FORT WORTH"|TX||76177|||F|F|Y||"BAYLEAF MANOR"|DR|||"POMONA SEC 12 (A0298 HT&BRR) BLK 1 LOT 10"||0000000000000000|S7100-012|S7100|1|10|
*/

/*
stocklist

Phase 4, Section 12, Block, 01, lot 23

"","Package","Builder","Project - Phase - Lot","Design - Plan","Elevation","P2P","Address","Title Date","Lot Area (sq ft)","Home Area (sq ft)","Frontage (ft)","Lot Depth (ft)","Portals","Bed","Bath","Car Parks","Storey","Price","Available Date","Status","Marketing Status","Campaign","Sales Rep","Package Type","Preview"

"","1950W 1950W - Lot 12-01-23 Pomona","Perry Homes","Pomona - Phase 4 - 12-01-23","1950W - 1950w","E1","P","2213 Forest Trace Ln Manvel Texas 77578","","","1950.0","50'","","","3","2","2","1","$ 297,510.00","","Closed","","","","Builder Portal",""
*/
const ADDRS_POS = 7; // which filed is the address in. Format "2213 Forest Trace Ln Manvel Texas 77578"
const PROJ_PHASE_SEC_BLK_LOT_POS = 3; // 

// convert to common format

// sort order of map and data file is essential !

$stockList = "client.stocklist.csv";
$latestCsv = 'Brazoria.latest.csv';
$fieldMap =  "global.field.map";

function addr_conv ( $addrs ) {

  $addrs = strtoupper ( $addrs ); // uppercase
  $addrs = preg_replace("/[^0-9A-Z ]/", " "  , $addrs ); // anything but A-Z 0-9 with " " ie - , : etc
  $addrs = str_replace( " STREET " , " ST "  , $addrs );
  $addrs = str_replace( " AVENUE " , " AVE " , $addrs );
  $addrs = str_replace( " AV "     , " AVE " , $addrs );
  $addrs = str_replace( " DRIVE " , " DR " , $addrs );
  $addrs = str_replace( " LANE "  , " LN " , $addrs );
  $addrs = str_replace( " COURT " , " CT " , $addrs );
  $addrs = str_replace( " TR " , " TRAIL " , $addrs );  // can have "walnut Trail lane"
  $addrs = str_replace( " ROAD "  , " RD " , $addrs );
  $addrs = str_replace( " BOULEVARD " , " BLVD " , $addrs );
  $addrs = str_replace( " BLV " , " BLVD " , $addrs );
  $addrs = str_replace( " BL " , " BLVD "  , $addrs );
  $addrs = str_replace( " HIGHWAY " , " HYW "  , $addrs );
  $addrs = str_replace( " CIRCLE " , " CIR "   , $addrs );
  $addrs = str_replace( " PKWY " , " PARKWAY " , $addrs );

  $addrs = preg_replace('!\s+!', ' ', $addrs); // convert mutiple spaces to single
  return ( trim( $addrs ));

  /* results
  4718 CEDAR BUTTE LN MANVEL TEXAS 77578
  2511 PECAN CREEK LN MANVEL TEXAS 77578
  */
}

function brazoria_key_gen ( $owner, $legal , $block , $lot ) {
  // legal = POMONA SEC 15 (A0563 HT&BRR) BLK 1 LOT 14
  // *owner= POMONA PHASE 2A LLC,  POMONA PHASE 4  LLC
  // *owner^POMONA PHASE 3 LLC , legal^POMONA SEC 9 (A0298 HT&BRR) LOT RESERVE A (OPEN SPACE / LANDSCAPE) ACRES 0.065 , acreage_val^650 , *lot^RESERVE A
  // *owner^POMONA PHASE 2A LLC , street^COUNTY ROAD 84 , suffix^OFF , legal^A0417 A C H & B TRACT 15C ACRES 0.255 , acreage_val^2550 , *lot^15C
  // POMONA SEC 4 (A0298 HT&BRR & A0540 ACH&B) BLK 4 LOT 14 , *block^4 , *lot^14
  // *owner^POMONA PHASE 2A LLC , street^CROIX PKWY/COUNTY ROAD 84 , legal^A0417 A C H & B TRACT 27B ACRES 3.682 , acreage_val^36820 , *lot^27B
  $proj="na";
  $proj2="na"; // second/alt definition
  $phase="na";
  $section="na";
  $maybe_block="na";
  $maybe_lot="na";

  $owner = strtoupper ( preg_replace("/[^0-9A-Z ]/", " "  , $owner ) );
  $legal = strtoupper ( preg_replace("/[^0-9A-Z ]/", " "  , $legal ) );
  $block = strtoupper ( preg_replace("/[^0-9A-Z ]/", " "  , $block ) );
  $lot =   strtoupper ( preg_replace("/[^0-9A-Z )(\/]/", " "  ,   $lot ) );

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
    if ( $bit == "BLK") if ( isset ( $tmp[$pos + 1 ] ))  { $maybe_block = $tmp[$pos + 1 ]; } else { $maybe_block = "out-of-range"; }
    if ( $bit == "LOT") if ( isset ( $tmp[$pos + 1 ] ))  { $maybe_lot   = $tmp[$pos + 1 ]; } else { $maybe_lot = "out-of-range"; }
  }

  $tmp = explode ( " " , $owner);
  foreach ( $tmp as $pos => $bit ) {
    if ( $bit == "PHASE" ) {
      if ( isset ( $tmp[$pos + 1 ] )) { $phase = $tmp[$pos + 1 ]; } else  { $phase = "out-of-range"; } // next word
      $proj2="";
      for ( $i=0 ; $i<$pos ; $i++ ) $proj2 .= $tmp[$i] . " "; // all the words before phase
      $proj2 = trim ( $proj2 );
      if ( $proj2 == "" ) $proj2 = "out-of-range2";
    }
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

return ( $proj ."^". $phase ."^". $section ."^". $block ."^". $lot );
}

// get the fieldmap, rotate to useful format
//
$map=array();
if ( !file_exists( $fieldMap )) { print ( "No map file $fieldMap found\n"); exit (0); }
$map = explode( "\n", file_get_contents( $fieldMap ));
foreach ( $map as $k => $v ) { if ( strlen ( $v ) < 2 ) unset ($map[$k]); }  // get rid of junk
//print_r ( $map ); // array of lines like 6,7,8,9|Plan|6|PlanNumber,PlanName
foreach ( $map as $v ) {
	$line = array_map ( "trim" , explode ( "|" , $v));
	if ( count ( $line ) != 2) { print ( "Line $v is bad\n"); exit (0); }
	$tmp2 = array_map ( "trim" , explode ( "," , $line[1] )); //  py_owner_name , appr_owner_name , legal_desc
	foreach ( $tmp2 as $pos => $v2 ) {
		$mapArr[$v2][0] = $line[0];
    $mapArr[$v2][1] = $pos;
	}
}
foreach ( $mapArr as $k => $v ) {
  print ( "Source Key [$k] maps to [" . $v[0] . "] priority " . $v[1] . "\n" );
}

// Get the Runway source data
//
if ( !file_exists( $stockList )) { print ( "No fixed Csv file $stockList found\n"); exit (0); }
$file = fopen( $stockList, 'r');
$i=0;
while (($line = fgetcsv($file)) !== FALSE) {
  if ( count ($line) > ADDRS_POS && strtoupper ( trim ($line[ADDRS_POS])) != "ADDRESS" ) {
    //
    $addrs = addr_conv ( $line[ ADDRS_POS ] ); // full address
    $project="na"; $phase="na"; $section="na"; $block="na"; $lot="na"; // reset
    $tmp = explode ( " " , $addrs);
    $count = count ( $tmp );
    if ( $count > 7 || $count < 6 ) print ( "Addrs [ $addrs ] in $stockList is bad got " . count ($tmp) . "\n"); 
    if ( $count == 6 ) { unset ( $tmp[5]); unset ( $tmp[4]); unset ( $tmp[3]);} // get rid of state and zip & city!
    if ( $count == 7 ) { unset ( $tmp[6]); unset ( $tmp[5]); unset ( $tmp[4]);} // get rid of state and zip & city!
    $key = implode ( " " , $tmp); // just 2204 MULBERRY RIDGE CT MANVEL

    //
    // note does have - in data so must use " - "
    $tmp = array_map ( "trim" , explode ( " - " , $line[ PROJ_PHASE_SEC_BLK_LOT_POS ])); 
    if ( count ($tmp) != 3 ) { 
      print ( "Proj/Phase/ID " . $line[ PROJ_PHASE_SEC_BLK_LOT_POS ] . " in $stockList is bad, got " . count ($tmp) . "\n");  
    } else {
      $project = strtoupper( $tmp[0] ); // Project
      $phase = trim( str_replace( "phase" , "" , strtolower ( $tmp[1] ) ) ); // Phase stripped out
      //
      $tmp2 = array_map ( "trim" , explode ( "-" , $tmp[2]));
      if ( count ($tmp2) != 3 ) { // Section, Block, lot
        print ( "Sec/Block/Lot in $stockList " . $tmp[2]  . " is bad, got " . count ($tmp2) . "\n");      
      } else {
        $section =  ltrim( strtoupper($tmp2[0]) , "0") ; // Section
        $block = ltrim( strtoupper($tmp2[1]) , "0" ); // Block
        $lot = ltrim( strtoupper($tmp2[2]) , "0" ); // Lot
      }
    }
    $combined [ $addrs ][0] = $v; 
    // first key is short address
    if ( isset ( $stock1[$key])) { print ( "Error duplicate key $key exists\n"); }
    $stock1[ $key ][0] = $addrs; $stock1[ $key ][1] = $project; 
    $stock1[ $key ][2] = $phase; $stock1[ $key ][3] = $section; 
    $stock1[ $key ][4] = $block; $stock1[ $key ][5] = $lot; $stock1[ $key ][6] = "no-match-addrs";
    //
    $key = $project ."^". $phase ."^". $section ."^". $block ."^". $lot; // redefine key
    $combined [ $addrs ][0] = $key; // used later to see if we got matches
    if ( isset ( $stock2[$key])) { print ( "Error duplicate key $key exists\n"); }
    $stock2[ $key ][0] = $addrs; $stock2[ $key ][1] = $project; // same again
    $stock2[ $key ][2] = $phase; $stock2[ $key ][3] = $section; 
    $stock2[ $key ][4] = $block; $stock2[ $key ][5] = $lot; $stock2[ $key ][6] = "no-match-full-ID";
    //
    $key = $project ."^". "na" ."^". $section ."^". $block ."^". $lot; // redefine key again, for trying match without phase
    if ( isset ( $stock3[$key])) { 
      print ( "Error duplicate key $key exists\n"); 
      unset ( $stock3[$key] ); // we cant use it
    } else {
      $stock3[ $key ][0] = $addrs; $stock3[ $key ][1] = $project;
      $stock3[ $key ][2] = $phase; $stock3[ $key ][3] = $section; // do keep phase in payload
      $stock3[ $key ][4] = $block; $stock3[ $key ][5] = $lot; $stock3[ $key ][6] = "no-match-Part_ID";
    }
  }
  $i++;
}
fclose($file);
print ( "Found $i lines in $stockList\n");


/* look for fixed key and value in latestCsv
[0]                               [15]         [16]
 000000118181,MN,02020,,,,,,,,,,,,,assessed_val,000000000000000
 000000118181,MN,02020,,,,,,,,,,,,,land_acres,00000000000000000000
*/
$matrix=array();
$old_key = ""; $key_cnt =1; $old_cnt = -1; $recs=1;
if ( !file_exists( $latestCsv )) { print ( "No fixed Csv file $latestCsv found\n"); exit (0); }
$file = fopen( $latestCsv, 'r');
while (($line = fgetcsv($file)) !== FALSE) {
   if ( count ($line) > 2 ) {
   	  if ( count ($line) != 17 ) { print ( "Line in $latestCsv is bad got " . count ($line) . "\n"); exit (0); }
      // only work on lines with 17 fields ie 16 keys + value
   	  $key = "";
   	  for ( $i =0 ; $i < 15 ; $i++ ) { $key .= $line[$i]; } // re-create key
   	  $target = $line[15];
   	  $val = $line[16]; 
   	  //print ( "$key - $target - $val\n");
   	  if ( $old_key != $key ) {
   	 	//print ( "New $key after $key_cnt\n"); 
   	 	if ( $old_cnt != $key_cnt && $old_cnt > 1 ) { print ( "Records in $latestCsv vary $old_cnt $key_cnt\n"); /*exit (0);*/ }
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
            print ( "Set higher $target with $val to $dest_tag\n");
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
   	 $key_cnt++;
   	 $recs++;
   }
}
fclose($file);
print ( "Done at $recs \n");

/*
owner^HAPPY GROUP LIMITED LIABILITY COMPANY , street^SMITH RANCH , suffix^RD , *city^PEARLAND , legal^A0304 H T & B R R BLOCK 1 TRACT 1 (PT) (PEARLAND OFFICE PARK) 4.7619% COMMON AREA BLDG 1 UNIT 103 , land_val^15100 , improved_val^57980 , appraised_val^73080 , assessed_val^73080 , acreage_val^3102 , *house^2743
*/

//print_r ( $matrix );

foreach ( $matrix as $k => $v ) {    // where $v is array [ dest_tag ] => $value $k is non-usable key
  //sort ($v);
  //$tmp = implode ( "," , $v );
  //print ( $tmp . "\n");
  $good_line = false; $result="";
  foreach ( $v as $k2 => $v2 ) {
    if ( strpos( $k2 , "*" ) !== false ) {  $good_line = true; }// ie has important fields
    $result .= $k2 . "^" . $v2 . " , ";
  }
  //
  $testKey = ""; $hit=0; $found=false;
  if ( isset ( $v["*house"] )) { $testKey .= $v["*house"] ; $hit++; }
  if ( isset ( $v["prefix"] )) { $testKey .= " " . $v["prefix"] ; $hit++; }
  if ( isset ( $v["street"] )) { $testKey .= " " . $v["street"] ; $hit++; }
  if ( isset ( $v["suffix"] )) { $testKey .= " " . $v["suffix"] ; $hit++; }
  //if ( isset ( $v["*city" ] )) { $testKey .= " " . $v["*city"] ; $hit++; }

  if ( $hit >= 3 ) {
    $testKey = addr_conv ( $testKey );
    //print ( "trying [" . $testKey . "]\n");
    if ( isset ( $stock1 [$testKey])) {
      print ( "Yay hit addrs for $testKey - " . $stock1 [$testKey][0] . "\n");
      $found=true;
      $stock1[ $testKey ][6] = "addrs-match";
      $fullAddr = $stock1[ $testKey ][0];
      if ( isset ( $combined [ $fullAddr ][1])) {
        print ( "error1 duplicate full address " . $fullAddr . "\n" );
      } else {
        $combined [ $fullAddr ][1] = $v; 
        $combined [ $fullAddr ][2] = $stock1 [$testKey];
      }
    }
  }
  
  $owner="" ; $legal="" ; $lot="" ; $block = "" ; $hit=0;
  if ( isset ( $v["*owner"] )) { $owner = $v["*owner"]; $hit++; }
  if ( isset ( $v["*lot"] ))   { $lot =   $v["*lot"];   $hit++; }
  if ( isset ( $v["legal"] ))  { $legal = $v["legal"];  $hit++; }
  if ( isset ( $v["*block"] )) { $block = $v["*block"]; $hit++; }
  //
  $out="";
  if ( $hit >= 3 /* && found == false */) {
    $out = brazoria_key_gen ( $owner, $legal , $block , $lot );
    //print ( "trying [" . $out . "]\n");
    if ( isset ( $stock2 [$out])) {
      print ( "Yay hit ID for $out - " . $stock2 [$out][0] . "\n");
      $found=true;
      if ( strpos ( $stock2[ $out ][6] , "no-match" ) !== false ) { $stock2[ $out ][6] = "ID-match"; }
      else { $stock2[ $out ][6] .= " , ID-match";}
      //
      $fullAddr = $stock2[ $out ][0];
      if ( isset ( $combined [ $fullAddr ][3])) { // not the [2] we are looking for dups here
        print ( "error2 duplicate full address " . $fullAddr . "\n" );
      } else {
        $combined [ $fullAddr ][3] = $v; 
        $combined [ $fullAddr ][4] = $stock2 [$out];
      }
    }
    if ( isset ( $stock3 [$out])) {
      print ( "Yay hit ID for $out - " . $stock3 [$out][0] . "\n");
      $found=true;
      if ( strpos ( $stock3[ $out ][6] , "no-match" ) !== false ) { $stock3[ $out ][6] = "ID-match"; }
      else { $stock3[ $out ][6] .= " , ID-match";}
      //
      $fullAddr = $stock3[ $out ][0];
      if ( isset ( $combined [ $fullAddr ][5])) { // not the [2] we are looking for dups here
        print ( "error3 duplicate full address " . $fullAddr . "\n" );
      } else {
        $combined [ $fullAddr ][5] = $v; 
        $combined [ $fullAddr ][6] = $stock3 [$out];
      }
    }
  }
  if ( $good_line ) print ( $result . " [" . $out . "] own:" . $owner . "\n");
}

//print_r ( $combined );

$i=0; $j=0;
foreach ( $combined as $k => $v ) {

  if ( !isset($v[1]) && !isset($v[3]) && !isset($v[5]) ) {
    print ( "No solution for $k " . $v[0] . "\n" ); $i++;
  } elseif ( isset($v[1]) && !isset($v[3]) && !isset($v[5]) ) {
    print ( "Single Addrs solution for $k " . $v[0] . "\n" ); 
  } elseif ( !isset($v[1]) && isset($v[3]) && !isset($v[5]) ) {
    print ( "Single Full ID solution for $k " . $v[0] . "\n" );
  } elseif ( !isset($v[1]) && !isset($v[3]) && isset($v[5]) ) {
    print ( "Single Part ID solution for $k " . $v[0] . "\n" );
  } else {
    print ( "Mixed solution for $k " . $v[0] . "\n" );
    print_r ( $v );
    /*
        [6] => Array
        (
            [0] => 4718 MESQUITE TERRACE DR MANVEL TEXAS 77578
            [1] => POMONA
            [2] => 2b
            [3] => 7
            [4] => 1
            [5] => 9
            dont compare 6 status*/
  }
  $j++;
}
print ( "Total $j stock. $i with no solution\n");
/*
$i=0; $j=0;
foreach ( $stock1 as $k => $v ) {
  print ( "[" . $k . "] - " . $v[0] . " Proj:" . $v[1] . " Phase:" . $v[2] . " Sec:" . $v[3] . 
          " Blk:" . $v[4] ." Lot:" . $v[5] . " STATUS-1:" . $v[6] ."\n" );
  $i++;
  if ( strpos ( $stock1[ $k ][6] , "no-match" ) !== false ) $j++;
  else 
}
print ( "Processed $i addrs records. $j had no match\n");

$i=0; $j=0;
foreach ( $stock2 as $k => $v ) {
  print ( "[" . $k . "] - " . $v[0] . " Proj:" . $v[1] . " Phase:" . $v[2] . " Sec:" . $v[3] . 
          " Blk:" . $v[4] ." Lot:" . $v[5] . " STATUS-2:" . $v[6] ."\n" );
  $i++;
  if ( strpos ( $stock2[ $k ][6] , "no-match" ) !== false ) $j++;
}
print ( "Processed $i ID full records. $j had no match\n");

$i=0; $j=0;
foreach ( $stock3 as $k => $v ) {
  print ( "[" . $k . "] - " . $v[0] . " Proj:" . $v[1] . " Phase:" . $v[2] . " Sec:" . $v[3] . 
          " Blk:" . $v[4] ." Lot:" . $v[5] . " STATUS-3:" . $v[6] ."\n" );
  $i++;
  if ( strpos ( $stock3[ $k ][6] , "no-match" ) !== false ) $j++;
}
print ( "Processed $i ID Part records. $j had no match\n");
*/

// end