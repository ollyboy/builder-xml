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

// sort order of map and data file is essentual !

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
  $owner = preg_replace('!\s+!', ' ', $owner ); // convert mutiple spaces to single
  $legal = preg_replace('!\s+!', ' ', $legal );
  //
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
  if ( $proj == "na")  $proj = $proj2; // try the alternative 

return ( $proj ."^". $phase ."^". $section ."^". $block ."^". $lot );
}

// get the fieldmap, rotate to useful format
//
$map=array();
if ( !file_exists( $fieldMap )) { print ( "No map file $fieldMap found\n"); exit (0); }
$map = explode( "\n", file_get_contents( $fieldMap ));
foreach ( $map as $k => $v ) if ( strlen ( $v ) < 2 ) unset ($map[$k]); // get rid of junk
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
    $addrs = addr_conv ( $line[ ADDRS_POS ] );
    $project="na"; $phase="na"; $section="na"; $block="na"; $lot="na"; // reset
    $tmp = explode ( " " , $addrs);
    $count = count ( $tmp );
    if ( $count > 7 || $count < 6 ) print ( "Addrs [ $addrs ] in $stockList is bad got " . count ($tmp) . "\n"); 
    if ( $count == 6 ) { unset ( $tmp[5]); unset ( $tmp[4]); unset ( $tmp[3]);} // get rid of state and zip & city!
    if ( $count == 7 ) { unset ( $tmp[6]); unset ( $tmp[5]); unset ( $tmp[4]);} // get rid of state and zip & city!
    $key = implode ( " " , $tmp); // just 2204 MULBERRY RIDGE CT MANVEL
    if ( isset ( $stock[$key])) { print ( "Error duplicate key $key exists\n"); }
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
    // first key is short address
    $stock[ $key ][0] = $addrs; $stock[ $key ][1] = $project; 
    $stock[ $key ][2] = $phase; $stock[ $key ][3] = $section; 
    $stock[ $key ][4] = $block; $stock[ $key ][5] = $lot; 
    //
    $key = $project ."^". $phase ."^". $section ."^". $block ."^". $lot; // redefine key
    if ( isset ( $stock[$key])) { print ( "Error duplicate key $key exists\n"); }
    $stock[ $key ][0] = $addrs; $stock[ $key ][1] = $project; // same again
    $stock[ $key ][2] = $phase; $stock[ $key ][3] = $section; 
    $stock[ $key ][4] = $block; $stock[ $key ][5] = $lot; 
    //
    $key = $project ."^". "na" ."^". $section ."^". $block ."^". $lot; // redefine key again, for trying match without phase
    if ( isset ( $stock[$key])) { 
      print ( "Error duplicate key $key exists\n"); 
      unset ( $stock[$key] ); // we cant use it
    } else {
      $stock[ $key ][0] = $addrs; $stock[ $key ][1] = $project;
      $stock[ $key ][2] = $phase; $stock[ $key ][3] = $section; // do keep phase in payload
      $stock[ $key ][4] = $block; $stock[ $key ][5] = $lot; 
    }
  }
  $i++;
}
fclose($file);
print ( "Found $i lines in $stockList\n");

foreach ( $stock as $k => $v ) {
  print ( "[" . $k . "] - " . $v[0] . " Proj:" . $v[1] . " Phase:" . $v[2] . " Sec:" . $v[3] . " Blk:" . $v[4] ." Lot:" . $v[5] ."\n" );
}

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
   	  for ( $i =0 ; $i < 15 ; $i++ ) { $key .= $line[$i]; } // re-creat key
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

/*
owner^HAPPY GROUP LIMITED LIABILITY COMPANY , street^SMITH RANCH , suffix^RD , *city^PEARLAND , legal^A0304 H T & B R R BLOCK 1 TRACT 1 (PT) (PEARLAND OFFICE PARK) 4.7619% COMMON AREA BLDG 1 UNIT 103 , land_val^15100 , improved_val^57980 , appraised_val^73080 , assessed_val^73080 , acreage_val^3102 , *house^2743
*/


foreach ( $matrix as $k => $v ) {    // wher $v is array [ dest_tag ] => $value $k is non-usable key
  //sort ($v);
  //$tmp = implode ( "," , $v );
  //print ( $tmp . "\n");
  $good_line = false; $result="";
  foreach ( $v as $k2 => $v2 ) {
    if ( strpos( $k2 , "*" ) !== false ) {  $good_line = true; }// ie has important fields
    $result .= $k2 . "^" . $v2 . " , ";
  }
  //
  $testKey = ""; $hit=0;
  if ( isset ( $v["*house"] )) { $testKey .= $v["*house"] ; $hit++; }
  if ( isset ( $v["prefix"] )) { $testKey .= " " . $v["prefix"] ; $hit++; }
  if ( isset ( $v["street"] )) { $testKey .= " " . $v["street"] ; $hit++; }
  if ( isset ( $v["suffix"] )) { $testKey .= " " . $v["suffix"] ; $hit++; }
  //if ( isset ( $v["*city" ] )) { $testKey .= " " . $v["*city"] ; $hit++; }
  if ( $hit >= 3 ) {
    $testKey = addr_conv ( $testKey );
    //print ( "trying [" . $testKey . "]\n");
    if ( isset ( $matrix [$testKey])) {
      print ( "Yay hit addrs for $testKey\n");
    }
  }
  $owner="" ; $legal="" ; $lot="" ; $block = "" ;
  if ( isset ( $v["owner"] )) { $owner = $v["owner"]; }
  if ( isset ( $v["*lot"] ))  { $lot = $v["*lot"]; }
  if ( isset ( $v["legal"] )) { $legal = $v["legal"]; }
  if ( isset ( $v["*block"] )) { $block = $v["*block"]; }
  //
  $out = brazoria_key_gen ( $owner, $legal , $block , $lot );

  if ( isset ( $matrix [$out])) {
    print ( "Yay hit ID for $out\n");
  }

  if ( $good_line ) print ( $result . " " . $out . "\n");
}
print ( "Done at $recs \n");


// end