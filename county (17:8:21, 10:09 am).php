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
$debugModeArgv = false; $prodModeArgv = false; $traceModeArgv = false; 
$callModeArgv = false; 
foreach( $argv as $cnt => $v ) {
  $value =trim ( strtolower( $v )); 
  if ( $cnt == 0 ) {} // ignore
  elseif ( $value  == "production") $prodModeArgv = true; // Just generate hints if false
  elseif ( $value  == "development") $prodModeArgv = false; 
  elseif ( $value  == "debug") $debugModeArgv = true; 
  elseif ( $value  == "trace") $traceModeArgv = true; 
  elseif ( $value  == "docall") $callModeArgv = true; 
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
      print ( "ERROR Unknown command line parameter [" . $v . "] Can use [developer] [County] production development debug trace docall\n" );
      exit (0);
    }
  }
}
if ( count ( $revisedClientSource ) > 0 ) $clientSource = $revisedClientSource;  // shorter list taken from command line
if ( count ( $revisedRunwaySource ) > 0 ) $runwaySource = $revisedRunwaySource; 
//print_r ( $clientSource );
//print_r ( $runwaySource );

$merged = array(); // for all devs for all countys
//
$mudMat=array();
$mudCodes = get_support_barLin ( "MUD.Codes.csv" );

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
  build_maxtix_from_csv ( $debugModeArgv, $latestCsv , $mapArr ,
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
      $combined = array(); // combined find methods
      $commWords = build_stock_keys ( $stockList, // in
                     $combined, $stock1, $stock2, $stock3 ); // set these
      print ( "NOTE $devName - Stock size is " . count($combined) . " Key sets are " . count($stock1) . " "  . count($stock2) ." " . count($stock3) . "\n");

      $noMatch = match_stock ( $commWords , $debugModeArgv , $traceModeArgv , $county , $stock1 , $stock2 , $stock3, // pass in these 
                           $combined ); // update this
      
      //print_r ( $combined );

      $i=0; $j=0; $noSolution = array(); $comCount= array();

      $fh=fopen ( $devName . "." . $countyName . ".link.csv", "w" ); // will delete old

      $headerRec = 
        "county" . "," .
        "match" . "," .
        "r_adds" . "," .
        "r_proj"  . "," .
        "r_phase"  . "," .
        "r_section"  . "," .
        "r_block"   . "," .
        "r_lot"   . "," .
        "community"  . "," .
        "owner"  . "," .
        "house"  . "," .
        "street" . "," .
        "suffix" . "," .
        "legal" . "," .
        "block" . "," .
        "lot" . "," .
        "appraised" . "," .
        "assessed" . "," .
        "market" . "," .
        "use" . "," .
        "land" . "," .
        "improved" . "," .
        "entities" . "," .
        "acreage"  . "," .
        "mud_d"  . "," . 
        "mud_c" . "\n";
      fwrite ( $fh, $headerRec );

      foreach ( $combined as $k => $v ) {

        //print_r ( $v);

        $r_adds = $k;
        $r_clientid = $v[7]; // key for write back
        $r_cpidstring = $v[8]; // key for write back
        $r_def = explode ( "^" , $v[0] ); // LIBERTY^2^na^1^C
        $r_proj  =   $r_def[0]; // community
        $r_phase =   $r_def[1];
        $r_section = $r_def[2];
        $r_block =   $r_def[3];
        $r_lot =     $r_def[4];
        $community="na";
        $owner = "na";
        $street ="na";
        $suffix ="";
        $legal = "na";
        $block = "na";
        $lot =   "na";
        $appraised = "na";
        $assessed =  "na";
        $market   =  "na";
        $use      =  "na";
        $land     =  "na";
        $improved =  "na";
        $acreage =  "na";
        $house =    "na";
        $entities = "na";

        if ( !isset($v[1]) && !isset($v[3]) && !isset($v[5]) ) {
          //print ( "No solution for " . $k . " [" . $v[0] . "]\n" ); 
          $i++;
          $noSolution[$k] = $v;
          $match="miss";
          // $community = $r_proj;
        } else {
          //    
          $match="hit"; 
          if ( isset($v[1]) )  $point = 1;   
          else if ( isset($v[3]) ) $point = 3; 
          else if ( isset($v[5]) ) $point = 5; 
          //
          $community= $v[ $point + 1 ][1];
          if ( strtolower ( $community ) != strtolower ( $r_proj )  ) print ( "WARN $k Community diff.  County[$community] Runway[$r_proj]\n");
          if ( isset ( $v[ $point ]['*owner'] )) $owner =     $v[ $point ]['*owner'];       // => BRUCE BRANDON JAMES & JOANNA LYNN SMITH
          if ( isset ( $v[ $point ]['street'] )) $street =    $v[ $point ]['street'];       //=> BLACKHAWK RIDGE
          if ( isset ( $v[ $point ]['suffix'] )) $suffix =    $v[ $point ]['suffix'];       // => LN
          if ( isset ( $v[ $point ]['legal']  )) $legal =     $v[ $point ]['legal'];        // => POMONA SEC 10 (A0298 HT&BRR) BLK 1 LOT 1
          if ( isset ( $v[ $point ]['*block'] )) $block =     $v[ $point ]['*block'];       //=> 1
          if ( isset ( $v[ $point ]['*lot']   )) $lot =       $v[ $point ]['*lot'];         // => 1
          if ( isset ( $v[ $point ]['appraised_val'] ))  $appraised = $v[ $point ]['appraised_val']; // => 59390
          if ( isset ( $v[ $point ]['assessed_val'] ))   $assessed =  $v[ $point ]['assessed_val'];  // => 59390
          if ( isset ( $v[ $point ]['market_val'] ))     $market   =  $v[ $point ]['market_val'];
          if ( isset ( $v[ $point ]['use_val'] ))        $use      =  $v[ $point ]['use_val'];
          if ( isset ( $v[ $point ]['land_val'] ))       $land     =  $v[ $point ]['land_val'];
          if ( isset ( $v[ $point ]['improved_val'] ))   $improved =  $v[ $point ]['improved_val'];
          if ( isset ( $v[ $point ]['acreage_val'] ))    $acreage =   $v[ $point ]['acreage_val'];   // => 2178
          if ( isset ( $v[ $point ]['*house'] ))         $house =     $v[ $point ]['*house'];        // => 2248
          if ( isset ( $v[ $point ]['entities'] ))       $entities =  str_replace( "," , "|" , $v[ $point ]['entities'] );
          //
          if ( isset($v[1]) && isset($v[5]) ) {
            if ( $v[2][0] != $v[6][0]) print ( "ERROR $k Address mismatch 2,6 \n");
            if ( $v[2][1] != $v[6][1]) print ( "ERROR $k community mismatch 2,6 \n");
            if ( $v[1]['appraised_val'] != $v[5]['appraised_val'] ) print ( "ERROR $k Appraised mismatch 2,6 \n");
          }
          if ( isset($v[1]) && isset($v[3]) ) {
            if ( $v[2][0] != $v[4][0]) print ( "ERROR $k Address mismatch 2,4 \n");
            if ( $v[2][1] != $v[4][1]) print ( "ERROR $k community mismatch 2,4 \n");
            if ( $v[1]['appraised_val'] != $v[3]['appraised_val'] ) print ( "ERROR $k Appraised mismatch 2,4 \n");
          }
          if ( isset($v[3]) && isset($v[5]) ) {
            if ( $v[4][0] != $v[6][0]) print ( "ERROR $k Address mismatch 4,6 \n");
            if ( $v[4][1] != $v[6][1]) print ( "ERROR $k community mismatch 4,6 \n");
            if ( $v[3]['appraised_val'] != $v[5]['appraised_val'] ) print ( "ERROR $k Appraised mismatch 4,6 \n");
          }
          if ( isset ( $comCount[ $community ] ) ) { 
            $comCount[ $community ]++; 
          } else { 
            $comCount[ $community ] =1; 
          }
        }
        $j++;
        $mud_d =  "NA";
        $mud_c =  "NA";
        foreach ($mudCodes as $mudLine ) {
          $bits = explode ( "|" , $mudLine );
          $mc = trim($bits[0]);
          $md = trim($bits[1]);
          if ( strpos ( $entities , $mc ) !== false ) {
            $mud_d =  $md; 
            $mud_c =  $mc;
          }
        }
        //
        $outRec = '"' . 
        // data here
        $countyName . '","' .
        $match . '","' .
        $r_adds . '","' .
        $r_proj  . '","' .
        $r_phase  . '","' .
        $r_section  . '","' .
        $r_block   . '","' .
        $r_lot   . '","' .
        $community  . '","' .
        $owner  . '","' .
        $house  . '","' .
        $street . '","' .
        $suffix . '","' .
        $legal . '","' .
        $block . '","' .
        $lot . '","' .
        $appraised . '","' .
        $assessed . '","' .
        $market . '","' .
        $use . '","' .
        $land . '","' .
        $improved . '","' .
        $entities . '","' .
        $acreage  . '","' .
        $mud_d . '","' . 
        $mud_c . '"' . "\n";   
        //
        fwrite ( $fh, $outRec ); 
 
        // build the overview
        if ( $match == "hit" ) {
          // write to runway
          $pl=array();
          $pl[0] = "na-yr"; // "taxYear" =>    $payload[0], // "2021",
          $pl[1] = $house . " " . $street; // "streetName" => $payload[1], //"1912 HOMESTEAD WAY",
          $pl[2] = "na-city";  // "cityName" =>   $payload[2],
          $pl[3] = "na-state"; //"stateName" =>  $payload[3], // "TX",
          $pl[4] = "na-zip";   // zipCode" =>    $payload[4], // "76226-1484",
          $pl[5] = "na-mail";  // "mailingAddress" =>    $payload[5], // "HARVEST MEADOWS PHASE ",
          $pl[6] = $legal ;    // "legalDescription" =>  $payload[6], // "HARVEST MEADOWS PHASE 1 BLK D LOT 4",
          $pl[7] = $block ;    // "blockName" =>  $payload[7], // "D",
          $pl[8] = $lot ;      // "lotName" =>    $payload[8], //"4",
          $pl[9] = $owner  ;   // "ownerName" =>  $payload[9], // "HARDWICK, TYLER & POLLY",
          $pl[10] = "na-pec" ;   // "ownershipPercentage" =>  $payload[10], // 100.00,
          $pl[11] =  $entities;  // "subDivision" =>  $payload[11], // "ESD1, G01",
          $pl[12] =  $mud_d; // "mudDistrict" =>  $payload[12], //"HS",
          $pl[13] =  $mud_c; // "mudDistrictClassification" =>  $payload[13], // "HARVEST MEADOWS",
          $pl[14] =  $land;      // "landValue" =>       $payload[14],
          $pl[15] =  $acreage;   // "acreageValue" =>    $payload[15],
          $pl[16] =  $improved;  // "improvedValue" =>   $payload[16],
          $pl[17] =  $appraised; // "appraisedValue" =>  $payload[17],
          $pl[18] =  $use;       // "usedValue" =>       $payload[18],
          $pl[19] =  $market;    // "marketValue" =>     $payload[19],
          $pl[20] =  $assessed ; // "assessedValue" =>   $payload[20],
          $pl[21] =  $acreage;   // "neighborhood" =>    $payload[21], TODO
          $pl[22] =  $community; // "communityName" =>   $payload[22] //"HARVEST MEADOWS"
          
          if ( $prodModeArgv ) { $env = "PROD"; } else { $env = "DEMO"; }
          if ( $callModeArgv ) {
            $api_res = county_update ( $env , $r_clientid , $r_cpidstring , $pl );
            if ( strpos( $api_res, "ERROR" ) !== false  ) {
               print ( "ERROR API SEND [$api_res] for $r_adds\n");
            } else {
               print ( "NOTE API SEND [$api_res] for $r_adds\n");
            }
          }

          if ( isset ( $merged[ $r_proj ][ $r_adds ][ "hit" ] )) {
            print ( "ERROR $devName $countyName - Hmm double hit for [ $community ][ $r_adds ][ $match ]\n");
          } else {
            $merged[ $r_proj ][ $r_adds ][ "hit"] = $outRec  ;
            unset ( $merged[ $r_proj ][ $r_adds ][ "miss" ] ); // may do nothing
          }
        } else { // miss
          if ( isset ( $merged[ $r_proj ][ $r_adds ][ "hit" ] )) {
            // do nothing we already have a hit
          } else {
            $merged[ $r_proj ][ $r_adds ][ "miss" ] = $outRec  ; // ok to record the miss
          }
        }
      }
      fclose($fh);

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
      //print_r ( $noSolution );
      foreach ( $noSolution as $k => $v ){
        //print ( "NOSOL :: $k :: " . $v[0] . "\n" );
      }
      if ( sizeof( $comCount ) == 0 ) {
        print ( "WARN $devName $countyName - No community match\n");
      } else {
        foreach ( $comCount as $k22 => $v22 ) {
          print ( "NOTE $devName $countyName - community [$k22] got $v22 matches\n");
        }
      }

    }
  }
  //print_r ( $merged );

  foreach  ( $merged as $com => $add ) {
    $file = str_replace( " " , "-" , trim($com) ) . ".link.csv";
    if ( file_exists( $file )) unlink ( $file); // bit redundent, remove what we will append to
  }
  $curComm = ""; $fh = false;
  foreach ( $merged as $com => $add ) {
    if ( $curComm != $com ) { // change
      $curComm = $com;
      $file = str_replace( " " , "-" , trim($com) ) . ".link.csv";
      if ( ! is_bool( $fh ) ) fclose ( $fh ) ;
      $fh = fopen ( $file , "a" );
      print ( "NOTE Writing [$com]\n");
    } 
    foreach ( $add as $line => $type ) {
      foreach ( $type as $res => $rec ) {
        fwrite ( $fh, $rec ); 
        //print ( ">> $com $line $res == $rec\n");
      }
    }
  } 
  fclose ( $fh );
}
// end of mainline


function addr_conv ( $addrs ) {

  // Denton e.g: DR , PL , LN , TRL , RD , PKWY , ST , CIR , CT , BLVD , RUN , BRK , FWY , AVE , DR S , DR N , BAY , CV , HLS , HOLW , WAY
  
  $addrs = strtoupper ( $addrs ); // uppercase
  $addrs = preg_replace("/[^0-9A-Z ]/", " "  , $addrs ); // anything but A-Z 0-9 with " " ie - , : etc
  $addrs = str_replace( " STREET" , " ST" , $addrs );
  $addrs = str_replace( " AVENUE" , " AV" , $addrs );
  $addrs = str_replace( " AVE"    , " AV" , $addrs );
  $addrs = str_replace( " DRIVE"  , " DR" , $addrs );
  $addrs = str_replace( " LANE"   , " LN" , $addrs );
  $addrs = str_replace( " COURT"  , " CT" , $addrs );
  $addrs = str_replace( " TRAIL"  , " TR" , $addrs );  // can have "walnut Trail lane"
  $addrs = str_replace( " TRL"    , " TR" , $addrs ); 
  $addrs = str_replace( " ROAD"   , " RD" , $addrs );
  $addrs = str_replace( " BOULEVARD" , " BL"      , $addrs );
  $addrs = str_replace( " BLV"       , " BL"      , $addrs );
  $addrs = str_replace( " BLVD"      , " BL"      , $addrs );
  $addrs = str_replace( " HIGHWAY"   , " HYW"     , $addrs );
  $addrs = str_replace( " CIRCLE"    , " CIR"     , $addrs );
  $addrs = str_replace( " PKWY"      , " PARKWAY" , $addrs );
  $addrs = str_replace( " XING"      , " CROSSING"      , $addrs );
  $addrs = str_replace( " PLACE"     , " PL"      , $addrs );
  $addrs = str_replace( " HOLW"      , " HOLLOW"  , $addrs );


  $addrs = preg_replace('!\s+!', ' ', $addrs); // convert mutiple spaces to single
  return ( trim( $addrs ));

  /* results
  4718 CEDAR BUTTE LN MANVEL TEXAS 77578
  2511 PECAN CREEK LN MANVEL TEXAS 77578
  */
}

function county_key_gen ( $commWords, $owner, $subdivision , $legal , $block , $lot ) {
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

  $owner = preg_replace("/[^0-9A-Z ]/", " "  ,       strtoupper ( $owner ) );  // Brazoria only
  $owner = preg_replace("/[^A-Z ]/", " "  ,          strtoupper ( $owner ) );  // Brazoria ok? plus Williamson
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
  $community = "";
  foreach ( $tmp as $pos => $bit ) {
    if ( strlen ( trim($bit) ) > 1 && strpos( $commWords , trim($bit) ) !== false ) $community .= trim($bit) . " ";
    //
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
  $community = trim ( $community);

  $tmp = explode ( " " , $owner);
  $community2 = "";
  foreach ( $tmp as $pos => $bit ) {
    if ( strlen ( trim($bit) ) > 1 && strpos( $commWords , trim($bit) ) !== false ) $community2 .= trim($bit) . " ";
    //
    if ( $bit == "PHASE" ) {
      if ( isset ( $tmp[$pos + 1 ] )) { $phase2 = $tmp[$pos + 1 ]; } else  { $phase2 = "out-of-range"; } // next word
      $proj2="";
      for ( $i=0 ; $i<$pos ; $i++ ) {
        $proj2 .= $tmp[$i] . " "; // all the words before phase
      }
      $proj2 = trim ( $proj2 );
      if ( $proj2 == "" ) $proj2 = "out-of-range";
    }
  }
  $community2 = trim ( $community2);
  if ( $community == "" ) $community = $community2;

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
  if ( $proj == "na" || $proj == "out-of-range" )  $proj = $proj2; // try the alternative 
  if ( $phase == "na" || $phase == "out-of-range" )  $phase = $phase2; 
  //
  if ( $proj == "na" || $proj == "out-of-range" )   $proj = $proj3; // and again
  if ( $phase == "na" || $phase == "out-of-range" ) $phase = $phase3; 

  $p_siz =  count ( explode ( " " , $proj  ));
  $p2_siz = count ( explode ( " " , $proj2 ));
  $p3_siz = count ( explode ( " " , $proj3 ));

  if ( $p2_siz > 0 && $p2_siz < 3 && $p_siz > 2 ) $proj = $proj2; // more likely
  if ( $p3_siz > 0 && $p3_siz < 3 && $p_siz > 2 ) $proj = $proj3;

  $p_siz =  count ( explode ( " " , $proj  ));
  if ( $p_siz > 2 && $community != "" ) $proj = $community;

  //print ( "TRACE community [$community] [$community2]\n");

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
  if ( count ($line) > 15 ) {
    //
    $j++;
    $section="na"; $block="na"; $lot="na"; // reset
    //
    $status =  trim( $line[0]); //     print ( $v['currentstatusname'] ."|". 
    $clientid =   trim( $line[1]); // keys
    $cpidstring = trim( $line[2]); // keys
    $project = trim( $line[3]); //    $v['estateproductname'] ."|".
    $product = trim( $line[4]); //    $v['productname'] ."|".  // SS-21 , 1A-A-34
    $productNo=trim( $line[5]); //    $v['productnumber'] ."|". // 21
    $stage =   trim( $line[6]); //    $v['stageproductname'] ."|". //Phase 1
    $unit =    trim( $line[7]); //    $v['address']['unitnumber'] ."|".
    $street =  trim( $line[8]); //    $v['address']['street1'] ."|". // 814 Lawndale Street
    $suburb =  trim( $line[9]); //    $v['address']['suburb'] ."|".  // Celina
    $city =    trim( $line[10]); //    $v['address']['city'] ."|". // Celina
    $state =   trim( $line[11]); //    $v['address']['state'] ."|". //Texas
    $district= trim( $line[12]); //   $v['address']['district'] ."|".
    $postcode= trim( $line[13]); //   $v['address']['postcode'] ."|". //75009
    $spec    = trim( $line[14]); //   $v['specHome'] ."|". // false
    $builder = trim( $line[15]); //   $v['allocatedBuilderName'] . "\n" );
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
      if ( $local == "" ) print ( "ERROR $stockList at $k - No city/suburb for street [$street]\n");
      //
      // $addrs = addr_conv ( $unit ." ". $street ." ". $local ." ". $state ." ". $postcode ); // full address
      $addrs = addr_conv ( $unit ." ". $street ." ". $local ." ". $postcode ); // full address without state, brazoria does not have state   
      $tmp = array_map('trim', explode ( " " , $addrs));
      $count = count ( $tmp );
      $key1=""; $key2="";
      if ( $count < 4 ) {
        print ( "ERROR $stockList at $k - Addrs [$addrs] is short, got " . count ($tmp) . " from [" . implode( "|", $line ) . "]\n"); 
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
        if ( isset ( $stock1[$key1][$key2])) { print ( "ERROR Runway $stockList at $k - Duplicate address key [$key1] + [$key2] exists [$addrs]\n"); }
        $stock1[$key1][$key2][0] = $addrs; $stock1[$key1][$key2][1] = $project; 
        $stock1[$key1][$key2][2] = $phase; $stock1[$key1][$key2][3] = $section; 
        $stock1[$key1][$key2][4] = $block; $stock1[$key1][$key2][5] = $lot; 
        $stock1[$key1][$key2][6] = "no-match-addrs";
      }

      $tmp2 = array_map ( "trim" , explode ( "-" , $product ));
      if ( count ($tmp2) == 4 ) {
        $section =  ltrim( strtoupper($tmp2[1]) , "0") ; // Section
        $block = ltrim( strtoupper($tmp2[2]) , "0" ); // Block
        $lot = ltrim( strtoupper($tmp2[3]) , "0" ); // Lot
      } elseif ( count ($tmp2) == 3 ) {
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
      if ( $section == "" ) print ( "ERROR $stockList at $k - No section for [$product] at $street\n");
      if ( $block == "" ) print ( "ERROR $stockList at $k - No block for [$product] at $street\n");
      if ( $lot == "" ) print ( "ERROR $stockList at $k - No Lot for [$product] at $street\n");
      // Filter the stock TODO Project vs County Map
      //
      if ( isset ( $community[ $project ])) { $community[ $project ]++; }
      else { $community[ $project ] = 1; }

      $key = $project ."^". $phase ."^". $section ."^". $block ."^". $lot; // redefine key
      $combined [ $addrs ][0] = $key; // used later to see if we got matches
      // note that 1 2 , 3 4 , 5 6 are used for county data
      $combined [ $addrs ][7] = $clientid; //keys for write back
      $combined [ $addrs ][8] = $cpidstring;


      if ( isset ( $stock2[$key])) { print ( "ERROR $stockList at $k - Duplicate full project/lot key $key exists [$addrs]\n"); }
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

 $communityWords = "";
 foreach ( $community as $key => $val ) {
   print ( "NOTE community [" . $key . "] has $val recs\n");
   $communityWords .= $key . " ";
 }
 return( $communityWords );
}

function build_maxtix_from_csv ( $debug , $latestCsv , $mapArr , &$matrix , &$priority ) {

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
              if ( $matrix[ $key ][ $dest_tag ] != ltrim($val, "0") ) {
                if ( $debug ) print ( "NOTE $key - Set different $target with $val to $dest_tag. old val was " . $matrix[ $key ][ $dest_tag ] . "\n");
                $matrix[ $key ][ $dest_tag ] = ltrim($val, "0"); // save the value
                $priority[ $key ][ $dest_tag ] = $dest_priority ; // save the value 
              }
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


function match_stock ( $commWords , $debug , $trace, $matrix , $stock1 , $stock2 , $stock3, &$combined ) {

 $noMatch = array();
 //
 //print_r( $stock1);
 foreach ( $matrix as $k => $v ) {    // where $v is array [ dest_tag ] => $value $k is non-usable key

  //print ( "Processing [" . $k . "]\n");
  $good_line = false; $result="";

  foreach ( $v as $k2 => $v2 ) {
    if ( strpos( $k2 , "*" ) !== false ) {  $good_line = true; }// ie has important fields
    $result .= $k2 . "^" . $v2 . " , ";
  }
  //
  if ( isset ( $v["appraised_val"] ) || isset ( $v["assessed_val"] ) || isset ( $v["market_val"])  ) {
    $hasValues = true; // dont want records that dont have valuation
  } else {
    $hasValues = false;
  }

  $hit=0; $found=false;
  if ( isset ( $v["*house"] )) { $c_h = $v["*house"] ; $hit++; } else { $c_h = ""; }
  if ( isset ( $v["prefix"] )) { $c_p = $v["prefix"] ; $hit++; } else { $c_p = ""; }
  if ( isset ( $v["street"] )) { $c_s = $v["street"] ; $hit++; } else { $c_s = ""; }
  if ( isset ( $v["suffix"] )) { $c_f = $v["suffix"] ; $hit++; } else { $c_f = ""; }
  if ( isset ( $v["*city" ] )) { $c_c = $v["*city"]  ; $hit++; } else { $c_c = ""; }
  if ( isset ( $v["zip" ] ))   { $c_z = $v["zip"]    ; $hit++; } else { $c_z = ""; }
  $CountKey1 = trim ( addr_conv ( $c_h . " " )); // house number
  $CountKey2 = trim ( addr_conv ( $c_s . " " . $c_f . " " )); // street and suffix
  $CountAddrs = trim ( addr_conv ( $c_h . " " . $c_p . " " . $c_s . " " . $c_f . " " . $c_c . " " . $c_z )); 
  if ( $trace) print ( "TRACE Addr: no[$CountKey1] st[$CountKey2] full[$CountAddrs] hit=$hit\n" );
  if ( $hit >= 2 && $hasValues && $CountKey1 != "" && $CountKey2 != "" ) { // enough data to test
    if ( $trace) print ( "TRACE Trying Addrs [$CountKey1] [$CountKey2] [$CountAddrs]\n" );
    if ( isset ( $stock1 [$CountKey1])) {
      if ( $debug ) ( "YAY hit house no [$CountKey1]\n");
      if ( isset ( $stock1 [$CountKey1][$CountKey2]  ) ) {
        $fullAddr = $stock1[$CountKey1][$CountKey2][0];
        if ( words_match ( "try1" , $fullAddr , $CountAddrs ) || 
             words_match ( "try2" , $CountAddrs , $fullAddr ) ) { 
          if ( $debug ) print ( "YAY hit addr start [$CountKey1][$CountKey2] R=[$fullAddr] C=[$CountAddrs]\n");
          $stock1[$CountKey1][$CountKey2][6] = "addrs-match";
          if ( isset ( $combined [ $fullAddr ][1])) {
            if ( $combined [ $fullAddr ][1] != $v ) {
              print ( "ERROR Duplicate Address DIFF county data - Runway[$fullAddr] County[$CountAddrs]\n" );
              if ( isset ( $v["appraised_val"] )) { $new_ap = $v["appraised_val"]; } else { $new_ap = 0; }
              if ( isset ( $combined [ $fullAddr ][1]["appraised_val"] )) { $old_ap = $combined [ $fullAddr ][1]["appraised_val"]; } else { $old_ap = 0; }
              if ( isset ( $v["improved_val"] )) { $new_ip = $v["improved_val"]; } else { $new_ip = 0; }
              if ( isset ( $combined [ $fullAddr ][1]["improved_val"] )) { $old_ip = $combined [ $fullAddr ][1]["improved_val"]; } else { $old_ip = 0; }
              print ( "---- OLD ----\n" );
              print_r ( $combined [ $fullAddr ][1] );
              print ( "---- NEW ----\n" );
              print_r ( $v );
              if ( $new_ap > $old_ap || $new_ip > $old_ip  ) {
                print ( "NOTE Used new county data as apparsied OR improved higher new_ap=$new_ap old_ap=$old_ap new_ip=$new_ip old_ip=$old_ip\n" );
                $combined [ $fullAddr ][1] = $v; 
              }
            } else {
              print ( "WARN Duplicate Address same county data - Runway[$fullAddr] County[$CountAddrs]\n" );
            }
          } else {
            $combined [ $fullAddr ][1] = $v; 
            $combined [ $fullAddr ][2] = $stock1[$CountKey1][$CountKey2];
          }
        } else {
          if ( $debug ) print ( "YAY NAH! hit addr start [$CountKey1][$CountKey2] R=[$fullAddr] C=[$CountAddrs]\n");
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
  if ( $trace) print ( "TRACE Site: own[$owner] sub[$subdivision] leg[$legal] blk[$block] lot[$lot]. $hit=$hit\n");
  if ( $hit >= 3 && $hasValues /* && found == false */) {
    $out = county_key_gen ( $commWords , $owner, $subdivision, $legal , $block , $lot ); // $owner, $subdivision , $legal , $block , $lot 
    if ( $trace ) print ( "TRACE Trying Key [" . $out . "] from :$owner,$subdivision,$legal,$block,$lot.\n");
    if ( isset ( $stock2 [ $out ])) {
      if ( $debug ) print ( "YAY hit ID for $out - " . $stock2 [$out][0] . "\n");
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
        print ( "WARN Easy Block/lot match [$out] - Duplicate Address Runway[$fullAddr] County[$CountAddrs]\n" );
        // TODO we get these due to dual ownership
        // [legal] => POMONA SEC 4 (A0298 HT&BRR & A0540 ACH&B) BLK 4 LOT 13, Undivided Interest 33.3400000000%
        //[appraised_val] => 114933
        //[assessed_val] => 114933)
        $cur = $combined [ $fullAddr ][5]["appraised_val"];
        $new = $v["appraised_val"];
        if ( $cur != $new) print ( "ERROR Diff apprasied $cur $new. Runway[$fullAddr] County[$CountAddrs]\n" );
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

function words_match ( $errtxt, $these , $areIn , $mode="" ) {

  // will match "60" to MUSTANG LAKES 60"
  // will match "1234" to "P1234W"
  $t = trim ( preg_replace("/[^0-9A-Z ]/", " " , strtoupper ( $these ))); // get rid of junk
  $a = trim ( preg_replace("/[^0-9A-Z ]/", " " , strtoupper ( $areIn )));
  //
  if ( $t == "" || $a == "") {
    print ( "ERROR word match $errtxt [$t] [$a]\n");
    return ( false );
  }
  if ( $mode != "" ) { print ( "DEBUG word match [$t] [$a]\n"); }

  $t= explode ( " " , preg_replace('!\s+!', ' ', $t )); // get rid of double spaces
  $a= explode ( " " , preg_replace('!\s+!', ' ', $a ));

  $rtn = true;
  foreach ( $t as $tv ) { //  [50 POMONA] 
    $hit=false;
    foreach ( $a as $av ) { // [POMONA 50]
      if ( strpos ( $av , $tv ) !== false ) {
        $hit=true;
        //print ( "-[$av]--[$tv]--hit\n");
      } else {
        //print ( "-[$av]--[$tv]--miss\n");
      }
    }
    if ( !$hit ) $rtn = false;
  }
  if ( $mode != "" ) {
    if ( $rtn ) { print ( "--good--\n"); } else { print ( "--bad--\n"); }
  }
  return ( $rtn );
}


function county_update ( $env , $clientId ,  $propertyKey, $payload ) {


    // True key is Pradeep
    //  [cpidstring] => 1303643628000225832823215561025643651625920083798
    //  [clientid] => 363312264
    // API end point - External Lot County Data Update REST API
    if ( $env == "PROD") {
      $url = "https://368u2vz15k.execute-api.us-west-1.amazonaws.com/live/external/lotcountyupdate";
    } else {
      $url = "https://368u2vz15k.execute-api.us-west-1.amazonaws.com/demo/external/lotcountyupdate";
      $env = "DEMO"; 
    }
    $x_api_key = "OJ6CmJRgVd6ikQSsMv0c88xFmv8Xh1xC6AtJ6tCI";
    $today = date ('Y-m-d');
    $data = array (

    "env" => $env, // DEMO or PROD
    "clientId" => $clientId,
    "propertyKey" => $propertyKey, // Use [cpidstring]
    "extractionDate" => $today, // Date of extraction in YYYY-MM-DD format
    //
    "taxYear" =>    $payload[0], // "2021",
    "streetName" => $payload[1], //"1912 HOMESTEAD WAY",
    "cityName" =>   $payload[2],
    "stateName" =>  $payload[3], // "TX",
    "zipCode" =>    $payload[4], // "76226-1484",
    "mailingAddress" =>    $payload[5], // "HARVEST MEADOWS PHASE ",
    "legalDescription" =>  $payload[6], // "HARVEST MEADOWS PHASE 1 BLK D LOT 4",
    "blockName" =>  $payload[7], // "D",
    "lotName" =>    $payload[8], //"4",
    "ownerName" =>  $payload[9], // "HARDWICK, TYLER & POLLY",
    "ownershipPercentage" =>  $payload[10], // 100.00,
    "subDivision" =>  $payload[11], // "ESD1, G01",
    "mudDistrict" =>  $payload[12], //"HS",
    "mudDistrictClassification" =>  $payload[13], // "HARVEST MEADOWS",
    "landValue" =>       $payload[14],
    "acreageValue" =>    $payload[15],
    "improvedValue" =>   $payload[16],
    "appraisedValue" =>  $payload[17],
    "usedValue" =>       $payload[18],
    "marketValue" =>     $payload[19],
    "assessedValue" =>   $payload[20],
    "neighborhood" =>    $payload[21],
    "communityName" =>   $payload[22] //"HARVEST MEADOWS"
    );  
    // 
    $content = json_encode( $data);
    // print ( "DEBUG : $content \n" ); // TODO REMOVE

    $curl = curl_init($url);
    //
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json', 
    'x-api-key: ' . $x_api_key
    ]);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
    //
    $response = curl_exec($curl);
    //
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ( $status != 200 ) {
      print ("ERROR: call to URL $url failed with status[$status], response[$response], curl_error[" . curl_error($curl) . "], curl_errno[" . curl_errno($curl) . "]\n" );
    /* print_r ( $data );
    print ( "\n");
    print_r ( $content);
    print ( "\n^--- array + json above ---^\n" ); */
    }
    //
    curl_close($curl);

    $rtnMess = "";
    // convert to array
    if ( $response == false || strlen ( $response ) == 0 ) { // is still a json string
      $rtnMess = "FAIL - No Response ";
    }
    $messArr=json_decode( $response, TRUE );

    if ( $messArr["success"] == true ) $rtnMess .= "OK - Success ";
    else $rtnMess .= "FAIL - Not Success - " . $messArr["responseMessage"] . " ";

    return ( $rtnMess );
}

// end