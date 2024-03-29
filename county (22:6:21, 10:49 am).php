<?php

ini_set("auto_detect_line_endings", true);
libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', 1); // Do send to output
ini_set('log_errors', 1 ); // send errors to log
ini_set('memory_limit', '16000M');

/*
000000687740|R|02020|000000000000||||7100-0121-010|000001158936|"POMONA PHASE 4  LLC"|F|000000000000||"9800 HILLWOOD PKWY"|"STE 300"|"FORT WORTH"|TX||76177|||F|F|Y||"BAYLEAF MANOR"|DR|||"POMONA SEC 12 (A0298 HT&BRR) BLK 1 LOT 10"||0000000000000000|S7100-012|S7100|1|10|
*/

$clientSource = get_support_barLin ( "county.source" ); // get the scope of work, returns empty if not found
if ( sizeof( $clientSource ) == 0 ) {
  print ( "ERROR Can't find essential scope file: county.source\n" ); // will exit
  exit(0);
} 

$runwaySource = get_support_barLin ( "runway.source" ); // get developers
if ( sizeof( $runwaySource ) == 0 ) {
  print ( "ERROR Can't find essential work scope file: runway.source\n" ); // will exit
  exit(0);
} 

// set flags and see if run is limited to a small set of jobs
//
$revisedClientSource=array(); $revisedRunwaySource=array();
foreach( $argv as $cnt => $v ) {
  $value =trim ( strtolower( $v )); 
  if ( $cnt == 0 ) {} // ignore
  elseif ( $value  == "production") $prodModeArgv = true; // Just generate hints if false
  elseif ( $value  == "development") $prodModeArgv = false; 
  else {
    $hit = false;
    foreach ( $clientSource as $scope ) {
      $parts = array_map ( 'trim' , explode ("|" , $scope ));
      if ( strtolower ( $parts[0] ) == $value ) { 
        $revisedClientSource[] = $scope; 
        $hit = true; 
        unset ( $argv[$cnt] );
      }// names match
    }
    foreach ( $runwaySource as $scope ) {
      $parts = array_map ( 'trim' , explode ("|" , $scope ));
      if ( strtolower ( $parts[0] ) == $value ) { 
        $revisedRunwaySource[] = $scope; 
        $hit = true; 
        unset ( $argv[$cnt] ); // found a developer override, remove so next test does not fail
      }
    }
    if ( ! $hit ) {
      print ( "ERROR Unknown command line parameter [" . $v . "]\n" );
      exit (0);
    }
  }
}
if ( count ( $revisedClientSource ) > 0 ) $clientSource = $revisedClientSource;  // shorter list taken from command line
if ( count ( $revisedRunwaySource ) > 0 ) $runwaySource = $revisedRunwaySource; 
//print_r ( $clientSource );
//print_r ( $runwaySource );

foreach ( $clientSource as $clientScope ) { // ----- for each County

  $parts = array_map ( 'trim' , explode ("|" , $clientScope ));
  $countyName = $parts[0];
  $latestCsv = $countyName . ".latest.csv";
  //
  $fieldMap =  $countyName . ".field.map"; 
  $mapArr = array();
  $mapArr = adj_map ( $fieldMap ); // get the fieldmap, rotate to useful format
  print ( "NOTE Field map $fieldMap size is " . count($mapArr) ." - Reading in $countyName\n");

  // Get the county data etc
  $county=array(); $priority=array(); // reset
  build_maxtix_from_csv ( $latestCsv , $mapArr ,
                          $county , $priority ); // set these
  $c_siz = count( $county );
  if ( $c_siz == 0 ) {
    print ( "ERROR $countyName -  County size is $c_siz\n");
  } else {
    print ( "NOTE $countyName -  County size is $c_siz\n");

    foreach ( $runwaySource as $runwayScope ) { // ----- for each Developer

      $parts = array_map ( 'trim' , explode ("|" , $runwayScope ));
      $devName = $parts[0];
      // sort order of map and data file is essential !
      $stockList = $devName .".address.csv";

      print ( "NOTE -- Processing $latestCsv against $stockList --\n" );

      // Get the Runway source data, make useful keys
      $stock1=array(); $stock2=array(); $stock3=array(); // reset
      $combined = array();
      build_stock_keys ( $stockList, // in
                     $combined, $stock1, $stock2, $stock3 ); // set these
      print ( "NOTE $devName - Stock size is " . count($combined) . " Key sets are " . count($stock1) . " "  . count($stock2) ." " . count($stock3) . "\n");

      $noMatch = match_stock ( $county , $stock1 , $stock2 , $stock3, // pass in these 
                           $combined ); // update this
      //print_r ( $combined );

      $i=0; $j=0; $noSolution = array(); $comCount= array();
      foreach ( $combined as $k => $v ) {

        if ( !isset($v[1]) && !isset($v[3]) && !isset($v[5]) ) {
          //print ( "No solution for " . $k . " [" . $v[0] . "]\n" ); 
          $i++;
          $noSolution[$k] = $v;
        } else {
          //     
          if ( isset($v[1]) )      $community= $v[2][1];
          else if ( isset($v[3]) ) $community= $v[4][1];
          else if ( isset($v[5]) ) $community= $v[6][1];
          else $community="";
          //
          if ( isset($v[1]) && isset($v[5]) ) {
            if ( $v[2][0] != $v[6][0]) print ( "ERROR $k Address mismatch 2,6 \n");
            if ( $v[2][1] != $v[6][1]) print ( "ERROR $k Community mismatch 2,6 \n");
            if ( $v[1]['appraised_val'] != $v[5]['appraised_val'] ) print ( "ERROR $k Appraised mismatch 2,6 \n");
          }
          if ( isset($v[1]) && isset($v[3]) ) {
            if ( $v[2][0] != $v[4][0]) print ( "ERROR $k Address mismatch 2,4 \n");
            if ( $v[2][1] != $v[4][1]) print ( "ERROR $k Community mismatch 2,4 \n");
            if ( $v[1]['appraised_val'] != $v[3]['appraised_val'] ) print ( "ERROR $k Appraised mismatch 2,4 \n");
          }
          if ( isset($v[3]) && isset($v[5]) ) {
            if ( $v[4][0] != $v[6][0]) print ( "ERROR $k Address mismatch 4,6 \n");
            if ( $v[4][1] != $v[6][1]) print ( "ERROR $k Community mismatch 4,6 \n");
            if ( $v[3]['appraised_val'] != $v[5]['appraised_val'] ) print ( "ERROR $k Appraised mismatch 4,6 \n");
          }
          if ( isset ( $comCount[ $community ] ) ) { $comCount[ $community ]++; } else { $comCount[ $community ] =1; }
        }
        $j++;
      }
      print ( "NOTE $devName $countyName - Total $j stock. $i with no solution\n");
      //print_r ( $noSolution );
      //print_r ( $noMatch );
      $noMatchProj = array();
      foreach ( $noMatch as $k => $v ){
        $tmp = explode ( "^" , $k );
        if ( isset ( $noMatchProj [ $tmp [0] ])) { $noMatchProj [ $tmp [0] ]++; }
        else { $noMatchProj [ $tmp [0] ] = 1;} 
      }
      foreach ( $noMatchProj as $k => $v ){
        //if ( $v > 10 ) print ( "NOMATCH :: $k :: $v\n" );
      }
      foreach ( $noSolution as $k => $v ){
        //print ( "NOSOL :: $k :: " . $v[0] . "\n" );
      }
      print_r ( $comCount );
    }
  }
}
// end of mainline


function addr_conv ( $addrs ) {

  // Denton e.g: DR , PL , LN , TRL , RD , PKWY , ST , CIR , CT , BLVD , RUN , BRK , FWY , AVE , DR S , DR N , BAY , CV , HLS , HOLW , WAY
  
  $addrs = strtoupper ( $addrs ); // uppercase
  $addrs = preg_replace("/[^0-9A-Z ]/", " "  , $addrs ); // anything but A-Z 0-9 with " " ie - , : etc
  $addrs = str_replace( " STREET " , " ST "  , $addrs );
  $addrs = str_replace( " AVENUE " , " AVE " , $addrs );
  $addrs = str_replace( " AV "     , " AVE " , $addrs );
  $addrs = str_replace( " DRIVE " , " DR " , $addrs );
  $addrs = str_replace( " LANE "  , " LN " , $addrs );
  $addrs = str_replace( " COURT " , " CT " , $addrs );
  $addrs = str_replace( " TR " , " TRAIL " , $addrs );  // can have "walnut Trail lane"
  $addrs = str_replace( " TRL " , " TRAIL " , $addrs ); 
  $addrs = str_replace( " ROAD "  , " RD " , $addrs );
  $addrs = str_replace( " BOULEVARD " , " BLVD " , $addrs );
  $addrs = str_replace( " BLV " , " BLVD " , $addrs );
  $addrs = str_replace( " BL " , " BLVD "  , $addrs );
  $addrs = str_replace( " HIGHWAY " , " HYW "  , $addrs );
  $addrs = str_replace( " CIRCLE " , " CIR "   , $addrs );
  $addrs = str_replace( " PKWY " , " PARKWAY " , $addrs );
  $addrs = str_replace( " PLACE " , " PL " , $addrs );

  $addrs = preg_replace('!\s+!', ' ', $addrs); // convert mutiple spaces to single
  return ( trim( $addrs ));

  /* results
  4718 CEDAR BUTTE LN MANVEL TEXAS 77578
  2511 PECAN CREEK LN MANVEL TEXAS 77578
  */
}

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

  $owner = preg_replace("/[^0-9A-Z ]/", " "  ,       strtoupper ( $owner ) );
  $subdivision = preg_replace("/[^0-9A-Z ]/", " "  , strtoupper ( $subdivision ) );
  $legal = preg_replace("/[^0-9A-Z ]/", " "  ,       strtoupper ( $legal ) );
  $block = preg_replace("/[^0-9A-Z ]/", " "  ,       strtoupper ( $block ) );
  $lot =   preg_replace("/[^0-9A-Z )(\/]/", " "  ,   strtoupper (  $lot ) );

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


function build_stock_keys ( $stockList, &$combined , &$stock1, &$stock2, &$stock3 ) { // Get the Runway source data, make useful keys
 /*
 Closed|Union Park|1A-A-34|34|Phase 1||4600 Pavilion Way|Little Elm||Texas||76227|1|Drees Custom Homes
 Closed|Union Park|1A-A-4|4|Phase 1||4212 Canopy Street|Little Elm||Texas||76227||Coventry
 Closed|Pecan Square|1B-1B-18|1B-1B-18|1B||2612 LITTLE WONDER LANE|Northlake||Texas||76247||Pulte Homes
 Sold|Pecan Square|1B-1II-27|1B-1II-27|1B||2612 ATTICUS WAY|Northlake||Texas||76247||Pulte Homes
 Closed|Pecan Square|1B-1J-6|1B-1J-6|1B||2612 LAZY DOG LANE|Northlake||Texas||76247|1|Perry Homes
 Closed|Pecan Square|1B-1KK-26|1B-1KK-26|1B||2612 JACK RABBIT WAY|Northlake||Texas||76247|1|DR Horton - Emerald
 Closed|Pecan Square|1D-A-10|1D-A-10|1D||2612 WOODHILL WAY|Northlake||Texas||76247|1|Perry Homes
 Closed|Liberty|A-7|7|Phase 1||2612 Independence Drive|Melissa|Melissa|Texas||75454||CalAtlantic
 Closed|Liberty|B-22|22|Phase 1||2612 Katie Trail|Melissa|Melissa|Texas||75454||CalAtlantic
 Closed|Liberty|E-28|28|Phase 1||2612 Patriot Drive|Melissa|Melissa|Texas||75454||CalAtlantic
 */
 //
 if ( !file_exists( $stockList )) { print ( "ERROR No fixed Csv file $stockList found\n"); return (0); }
 $file = fopen( $stockList, 'r');
 $j=0; $k=0; $l=0;
 while (($line = fgetcsv($file,0,"|",'"',"\\")) !== FALSE) {
  //
  $k++;
  if ( count ($line) > 13 ) {
    //
    $j++;
    $section="na"; $block="na"; $lot="na"; // reset
    //
    $status =  trim( $line[0]); //     print ( $v['currentstatusname'] ."|". 
    $project = trim( $line[1]); //    $v['estateproductname'] ."|".
    $product = trim( $line[2]); //    $v['productname'] ."|".  // SS-21 , 1A-A-34
    $productNo=trim( $line[3]); //    $v['productnumber'] ."|". // 21
    $stage =   trim( $line[4]); //    $v['stageproductname'] ."|". //Phase 1
    $unit =    trim( $line[5]); //    $v['address']['unitnumber'] ."|".
    $street =  trim( $line[6]); //    $v['address']['street1'] ."|". // 814 Lawndale Street
    $suburb =  trim( $line[7]); //    $v['address']['suburb'] ."|".  // Celina
    $city =    trim( $line[8]); //    $v['address']['city'] ."|". // Celina
    $state =   trim( $line[9]); //    $v['address']['state'] ."|". //Texas
    $district= trim( $line[10]); //   $v['address']['district'] ."|".
    $postcode= trim( $line[11]); //   $v['address']['postcode'] ."|". //75009
    $spec    = trim( $line[12]); //   $v['specHome'] ."|". // false
    $builder = trim( $line[13]); //   $v['allocatedBuilderName'] . "\n" );
    //
    if ( ( $status == "Closed" || $status == "Sold" ) /* && $project =="POMONA" */ ) {
      $l++;
      if ( $project == "" ) print ( "ERROR $stockList at $i - No Project name\n");
      $project = strtoupper($project); // ie POMONA
      //
      if ( $stage == "" ) {
        $phase = "na";
      } else {
        $phase = trim ( str_replace( "PHASE" , "" , strtoupper ( $stage )));  // get rid of word "Phase"
        $phase = trim ( str_replace( " - " , "" , strtoupper ( $phase ))); // get rid of "- "
      }
      if ( $phase == "" ) print ( "ERROR $stockList at $k - No Phase name\n");
      $tmp = array_map ( 'trim' , explode ( " " , $phase)); 
      $count = count ( $tmp );
      if ( $count == 2 ) { $phase = $tmp[1]; $project = $project . " " . $tmp[0]; }
      elseif ( $count == 3 ) { $phase = $tmp[2]; $project = $project . " " . $tmp[0] . " " . $tmp[1]; }
      elseif ( $count == 4 ) { $phase = $tmp[3]; $project = $project . " " . $tmp[0] . " " . $tmp[1] . " " . $tmp[1]; }
      elseif ( $count > 4  ) { print ( "ERROR $stockList at $k, to many words [" . $phase . "]\n"); }

      if ( $city == $suburb ) { $local = trim( $suburb); }
      else { $local = trim ( $suburb . " " . $city ); }
      if ( $local == "" ) print ( "ERROR $stockList at $k - No city/suburb\n");
      //
      // $addrs = addr_conv ( $unit ." ". $street ." ". $local ." ". $state ." ". $postcode ); // full address
      $addrs = addr_conv ( $unit ." ". $street ." ". $local ." ". $postcode ); // full address without state, brazoria does not have state
    
      $tmp = array_map('trim', explode ( " " , $addrs));
      $count = count ( $tmp );
      $key1=""; $key2="";
      if ( $count < 4 ) {
        print ( "ERROR $stockList at $k - Addrs [ $addrs ] is short, got " . count ($tmp) . "\n"); 
      } else {
        // street is like "1712 Coronet Ave" - get the number for fast lookup
        $short = addr_conv ( $unit . " " . $street . " " );
        $tmp = array_map('trim', explode ( " " , $short));
        $i=0; 
        foreach ( $tmp as $bit ) {
          if ( $i == 0 ) { $key1 = $bit; }
          else { $key2 .= " " . $bit; }
          $i++;
        }
        $key2 = trim ( $key2 );
        //print ( "DEBUG -> $addrs - $key1 - $key2\n");
        if ( isset ( $stock1[$key1][$key2])) { print ( "ERROR $stockList at $k - Duplicate address key [$key1] + [$key2] exists\n"); }
        $stock1[$key1][$key2][0] = $addrs; $stock1[$key1][$key2][1] = $project; 
        $stock1[$key1][$key2][2] = $phase; $stock1[$key1][$key2][3] = $section; 
        $stock1[$key1][$key2][4] = $block; $stock1[$key1][$key2][5] = $lot; 
        $stock1[$key1][$key2][6] = "no-match-addrs";
      }

      $tmp2 = array_map ( "trim" , explode ( "-" , $product ));
      if ( count ($tmp2) == 3 ) {
        $section =  ltrim( strtoupper($tmp2[0]) , "0") ; // Section
        $block = ltrim( strtoupper($tmp2[1]) , "0" ); // Block
        $lot = ltrim( strtoupper($tmp2[2]) , "0" ); // Lot
      } elseif ( count ($tmp2) == 2 ) {
        $section =  "na"; // Section
        $block = ltrim( strtoupper($tmp2[0]) , "0" ); // Block
        $lot = ltrim( strtoupper($tmp2[1]) , "0" ); // Lot
      } else { // Section, Block, lot
        print ( "ERROR $stockList at $k - Sec/Block/Lot " . $product  . " is bad, got " . count ($tmp2) . "\n");      
      } 
      if ( $section == $phase ) { /* print ( "WARN at $i - Phase and section same - $phase\n"); */ $section = "na" ; } // nasty hack
      //
      if ( $section == "" ) print ( "ERROR $stockList at $k - No section\n");
      if ( $block == "" ) print ( "ERROR $stockList at $k - No block\n");
      if ( $lot == "" ) print ( "ERROR $stockList at $k - No Lot\n");
      // Filter the stock TODO Project vs County Map
      //
      if ( isset ( $community[ $project ])) { $community[ $project ]++; }
      else { $community[ $project ] = 1; }

      $key = $project ."^". $phase ."^". $section ."^". $block ."^". $lot; // redefine key
      $combined [ $addrs ][0] = $key; // used later to see if we got matches
      if ( isset ( $stock2[$key])) { print ( "ERROR $stockList at $k - Duplicate full project/lot key $key exists\n"); }
      $stock2[ $key ][0] = $addrs; $stock2[ $key ][1] = $project; // same again
      $stock2[ $key ][2] = $phase; $stock2[ $key ][3] = $section; 
      $stock2[ $key ][4] = $block; $stock2[ $key ][5] = $lot; $stock2[ $key ][6] = "no-match-full-ID";
      //
      $key = $project ."^". "na" ."^". $section ."^". $block ."^". $lot; // redefine key again, for trying match without phase
      if ( $section != "na" ) { // no point storing key, ts not strong enough
        if ( isset ( $stock3[$key])) { 
          print ( "ERROR $stockList at $k - Duplicate part project/lot key $key exists\n"); 
          unset ( $stock3[$key] ); // we cant use it
        } else {
          $stock3[ $key ][0] = $addrs; $stock3[ $key ][1] = $project;
          $stock3[ $key ][2] = $phase; $stock3[ $key ][3] = $section; // do keep phase in payload
          $stock3[ $key ][4] = $block; $stock3[ $key ][5] = $lot; $stock3[ $key ][6] = "no-match-Part_ID";
        }
      }
    }
  } else {
   print ( "WARN Found short line $k in $stockList " . implode( "|" , $line ) . "\n");
  }
 }
 fclose($file);
 print ( "NOTE Found $k lines in $stockList, $j were ok, $l were Closed/Sold\n");
 //
 foreach ( $community as $key => $val ) {
   print ( "NOTE Community [" . $key . "] has $val recs\n");
 }
 return(1);
}

function build_maxtix_from_csv ( $latestCsv , $mapArr , &$matrix , &$priority ) {

  /* look for fixed key and value in latestCsv
  [0]                               [15]         [16]
   000000118181,MN,02020,,,,,,,,,,,,,assessed_val,000000000000000
   000000118181,MN,02020,,,,,,,,,,,,,land_acres,00000000000000000000
  */
  $old_key = ""; $key_cnt =1; $old_cnt = -1; $recs=1; $hits=0;
  if ( !file_exists( $latestCsv )) { print ( "ERROR No fixed Csv file $latestCsv found\n"); exit (0); }
  $file = fopen( $latestCsv, 'r');
  //
  while (($line = fgetcsv($file)) !== FALSE) {
    $cnt = count ($line);
    if ( $cnt  > 2 ) {
   	  if ( $cnt != 17 ) { print ( "ERROR Line $recs in $latestCsv is bad got " . count ($line) . "\n"); }
      else {
        // only work on lines with 17 fields ie 16 keys + value
        $target = trim( $line[15] );
        if ( isset ( $mapArr[$target] ) ) {         // ---- process the record
          $hits++;
   	      $key = "";
   	      for ( $i =0 ; $i < 15 ; $i++ ) { $key .= $line[$i]; } // re-create key
   	      $val = $line[16]; 

          /*
   	      //print ( "$key - $target - $val\n");
   	      if ( $old_key != $key ) {
   	 	      //print ( "New $key after $key_cnt\n"); 
   	 	      if ( $old_cnt != $key_cnt && $old_cnt > 1 ) { print ( "WARN Records in $latestCsv vary $old_cnt $key_cnt\n"); }
            $old_cnt = $key_cnt;
   	 	      $old_key = $key;
   	 	      $key_cnt = 1;
   	      }
          */
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
  print ( "NOTE Matrix from $latestCsv done at $recs. $hits were used.\n");
}

function same_words ( $s1 , $s2 ) {
  print ( "-> $s1 : $s2\n");
  $hit = 0;
  $a1 = array_map('trim', explode(',', $s1));
  $a2 = array_map('trim', explode(',', $s2));
  foreach ( $a1 as $b1 ) {
    foreach ( $a2 as $b2 ) {
      if ( $b2 == $b1 ) $hit++;
    }
  }
  return ( $hit );
}


function match_stock ( $matrix , $stock1 , $stock2 , $stock3, &$combined ) {

 print_r ( $matrix );
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

  $hit=0; $found=false;
  if ( isset ( $v["*house"] )) { $c_h = $v["*house"] ; $hit++; } else { $c_h = ""; }
  if ( isset ( $v["prefix"] )) { $c_p = $v["prefix"] ; $hit++; } else { $c_p = ""; }
  if ( isset ( $v["street"] )) { $c_s = $v["street"] ; $hit++; } else { $c_s = ""; }
  if ( isset ( $v["suffix"] )) { $c_f = $v["suffix"] ; $hit++; } else { $c_f = ""; }
  if ( isset ( $v["*city" ] )) { $c_c = $v["*city"]  ; $hit++; } else { $c_c = ""; }
  if ( isset ( $v["zip" ] ))   { $c_z = $v["zip"]    ; $hit++; } else { $c_z = ""; }
  $CountKey1 = trim ( addr_conv ( $c_h . " " )); // house number
  $CountKey2 = trim ( addr_conv ( $c_s . " " . $c_f . " " )); // street and suffix
  $CountAddrs = $c_h . " " . $c_p . " " . $c_s . " " . $c_f . " " . $c_c . " " . $c_z ; 

  print ( "Trying Addrs [$CountAddrs]\n" );
  if ( $hit >= 3 && $hasValues && $CountKey1 != "" && $CountKey2 != "" ) { // enough data to test
    if ( isset ( $stock1 [$CountKey1])) {
      //print ( "Yay hit house no [$CountKey1]\n");
      if ( isset ( $stock1 [$CountKey1][$CountKey2] )) {
        $fullAddr = $stock1[$CountKey1][$CountKey2][0];
        //print ( "Yay hit [$CountKey1][$CountKey2] R=[$fullAddr] C=[$CountAddrs]\n");
        $stock1[$CountKey1][$CountKey2][6] = "addrs-match";
        if ( isset ( $combined [ $fullAddr ][1])) {
          print ( "ERROR match 1 - duplicate full address " . $fullAddr . "\n" );
        } else {
          $combined [ $fullAddr ][1] = $v; 
          $combined [ $fullAddr ][2] = $stock1[$CountKey1][$CountKey2];
        }
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
    print ( "Trying Key [" . $out . "]\n");
    if ( isset ( $stock2 [ $out ])) {
      //print ( "Yay hit ID for $out - " . $stock2 [$out][0] . "\n");
      $found=true;
      if ( strpos ( $stock2[ $out ][6] , "no-match" ) !== false ) { $stock2[ $out ][6] = "ID-match"; }
      else { $stock2[ $out ][6] .= " , ID-match";}
      //
      $fullAddr = $stock2[ $out ][0];
      if ( isset ( $combined [ $fullAddr ][3]) ) { // not the [2] we are looking for dups here
        $cur = $combined [ $fullAddr ][3]["appraised_val"];
        $new = $v["appraised_val"];
        if ( $cur != $new) print ( "ERROR Diff apprasied $cur $new. Address " . $fullAddr . "\n" );
        //print ( "WARN match 2 duplicate full address " . $fullAddr . "\n" );
      } else {
        $combined [ $fullAddr ][3] = $v; 
        $combined [ $fullAddr ][4] = $stock2 [$out];
      }
    }
    if ( isset ( $stock3 [ $out ])) {
      //print ( "Yay hit ID for $out - " . $stock3 [$out][0] . "\n");
      $found=true;
      if ( strpos ( $stock3[ $out ][6] , "no-match" ) !== false ) { $stock3[ $out ][6] = "ID-match"; }
      else { $stock3[ $out ][6] .= " , ID-match";}
      //
      $fullAddr = $stock3[ $out ][0];
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
        $combined [ $fullAddr ][6] = $stock3 [$out];
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