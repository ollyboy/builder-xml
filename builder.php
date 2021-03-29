<?php

ini_set("auto_detect_line_endings", true);
libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', 1); // Do send to output
ini_set('log_errors', 1 ); // send errors to log

//$hit_m = words_match ( "50 POMONA" , "50 POMONA 60" , "debug");
//exit;

/* hard maps 
Perry Homes^50' | Pecan Square 50
*/

$clientSource = get_support_barLin ( "builder.source" ); // get the scope of work, returns empty if not found
if ( sizeof( $clientSource ) == 0 ) {
  print ( "ERROR Can't find essential work scope file: builder.source\n" ); // will exit
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
$planList = "runway.plans.csv";
$runwayPlans=array(); // reset
$combined = array();
build_plan_keys ( $planList, $runwayPlans );

$fieldMap =  "builder.field.map";
$mapArr = array();
$mapArr = adj_map ( $fieldMap ); // get the fieldmap, rotate to useful format
print ( "NOTE Field map size is " . count($mapArr) ."\n");

$builderData=array(); $priority=array(); $combined = array();// here as they need to add for each builder

foreach ( $clientSource as $scope ) {

  $parts = array_map ( 'trim' , explode ("|" , $scope ));
  $name = $parts[0];
  $latestCsv = $name . ".latest.csv";
 
  //print ( "NOTE Stock size is " . count($combined) . " Key sets are " . count($runwayPlans) . "\n");

  build_maxtix_from_csv ( $latestCsv , $mapArr , 
                          $builderData , $priorty, ); // set these

  print ( "NOTE Builder [$name] size is " . count( $builderData ) ."\n");

  $noMatch = match_plans ( $builderData , $runwayPlans, // pass in these 
                           $combined ); // update this
}

//print_r ( $builderData ); 
//print_r ( $combined );
//print_r ( $runwayPlans );
  
foreach ( $builderData as $k => $v ) {
  if ( $v["rec_status"] == "Builder-match" || $v["rec_status"] == "Builder+Plan-match" ) {
    //print ( "Missed $k\n");
  }
}  
$summary = array();
foreach ( $runwayPlans as $k => $v ) {
  $tmp = explode ("^" , $k ); // [COVENTRY^5959]
  $bld = $tmp[0];
  if ( !isset ( $summary[$bld]["miss"] )) { $summary[$bld]["miss"]=0; $summary[$bld]["which"]="|"; } 
  if ( !isset ( $summary[$bld]["hit" ] )) { $summary[$bld]["hit" ]=0; }
  // 
  foreach ( $v as $k2 => $v2 ) { // Coventry 55 - Pomona^55
    if ( $v2["rec_status"] == "no-match" ) {
      $summary[$bld]["miss"]++;
      $summary[$bld]["which"] .= $k . ":" . $k2 . "|";
    } else {
      $summary[$bld]["hit"]++;
    }
  }
} 
foreach ( $summary as $k => $v ) {
  $h = $summary[$k]["hit"];
  $m = $summary[$k]["miss"];
  $w = $summary[$k]["which"];
  if ( $h > 0 ) {
    print ( "For $k matches=$h misses=$m miss-list=$w\n");
  }
}
//print_r ( $summary );
// end of mainline


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

function build_plan_keys ( $planList, &$runwayPlans ) { // Get the Runway source data, make useful keys
 //
 //
 if ( !file_exists( $planList )) { print ( "ERROR No fixed Csv file $planList found\n"); return (0); }
 $file = fopen( $planList, 'r');
 $i=0; $j=0;
 while (($line = fgetcsv($file,0,"|",'"',"\\")) !== FALSE) {
  //
  /*
  6462583460707486222648312180001543571599232224252|Available|Perry Homes|2997|Perry 55 - Pomona|61' 9.5"|490900|55'||2997
  5427103505110052438524321084028326531599232375663|Available|Perry Homes|2999|Perry 55 - Pomona|69' 4.6"|516900|55'||2999
  7442764071122001811146627814426341431563782391223|Available|Perry Homes|2999W|Perry Homes|69' 3.0"|516900|50'||2999W
  8650470234284843631366440023637771871596171723351|Available|David Weekley Homes|Woodbank|David Weekley Homes 40 - Harvest|76' 9.0"|294990|40'||5433
  */
  if ( count ($line) > 14 ) {
    //
    $i++;
    //
    // must match to runway_get_plan.php
    $ID =      trim( $line[0]); //$v['clientproductid'] ."|".
    $status =  trim( $line[1]); // $v['currentstatusname'] ."|".
    $owner =   trim( $line[2]); // $v['ownername'] ."|".
    $design =  trim( $line[3]); // $v['designproductname'] ."|".
    $range =   trim( $line[4]); // $v['rangeproductname'] ."|".
    $frontTxt= trim( $line[5]); // $v['productDepthFormatted'] ."|". // => 58' 1.0"
    $size =    trim( $line[6]); // $v['productSizeFormatted']
    $price =   trim( $line[7]); // $v['productprice'] ."|".   // => 279990
    $front =   trim( $line[8]); // $v['canfitonwidthFormatted'] ."|".
    $beds =    trim( $line[9]);  // $v['noofbedrooms']  ."|".
    $baths =   trim( $line[10]); // $v['noofbathrooms']  ."|".
    $carParks= trim( $line[11]); // $v['noofcarparks']  ."|".
    $storeys = trim( $line[12]); // $v['noofstoreys']  ."|".
    $number =  trim( $line[13]); // $v['productnumber'] ."|".
    $name =    trim( $line[14]); //  $v['productname'] . "\n" );
    //
    if ( $status == "Available" ) {

      $j++;
      //
      if ( isset ( $ownerList[ $owner ])) { $ownerList[ $owner ]++; }
      else { $ownerList[ $owner ] = 1; }
      
      // Homes is sometimes used and sometimes not
      $owner = str_replace ( " HOMES" , "" , strtoupper ( $owner ));

      $front = preg_replace("/[^0-9\.]/", '', $front); // ie 55' goes to 55
      $front = round($front / 5) * 5;

      // convert the range frontage to nearest 5
      $tmp =  explode ( " " , trim($range) ); $newRange="";
      foreach ( $tmp as $bit) {
        if ( is_numeric ($bit ) && $bit > 30 && $bit < 100 ) {
          $bit = round($bit / 5) * 5;
        }
        $newRange .= $bit . " ";
      }
      $range = trim ( $newRange);

      // key should be $owner + name.
      $key = $owner . "^" . $name;
      //if ( $name != $design ) { print ( "WARN for $key plan design is $design\n"); }
      $key2 = $range . "^" . $front;
      
      // crap record - Available|Perry Homes|2694|Perry 55 - Pomona|65' 2.0"|478900|50'||2694
      $tmp =  explode ( " " , trim($range) );
      foreach ( $tmp as $bit) {
        if ( is_numeric ($bit ) && $bit > 30 && $bit < 100 ) {
          if ($bit != $front ) {
            print ( "WARN mixed frontage - [$range] [$front]\n");
            $key2 = $range; // override
          }
        }
      }
      // Fix up bad keying

      if ( $key2 == "Perry Homes^50" ) $key2 = "Pecan Square 50"; // hard legacy map
      //
      $save=true;
      if ( isset ( $runwayPlans[$key]) ) { 
        //print ( "WARN duplicate owner+name key [$key] exists\n");
        if ( isset ( $runwayPlans[$key][$key2] )) {
          print ( "ERROR duplicate owner+name+range+front key [$key][$key2]\n");
          $save=false;
        }
      } 
      if ( $save ) {
        $runwayPlans[$key][$key2]["ID"] = $ID;
        $runwayPlans[$key][$key2]["price"] = $price;
        $runwayPlans[$key][$key2]["size"] = $size;
        $runwayPlans[$key][$key2]["front"] = $front;
        $runwayPlans[$key][$key2]["design"] = $design;
        $runwayPlans[$key][$key2]["rec_status"] = "no-match";
        $runwayPlans[$key][$key2]["match_key"] = "NA";
      }
    } 
  } else {
   print ( "ERROR Found short line $i in $planList\n");
  }
 }
 fclose($file);
 print ( "NOTE Found $i lines in $planList, $j are avaiable status\n");
 //
 foreach ( $ownerList as $k => $v ) {
   print ( "NOTE Runway Builder [" . $k . "] has $v recs\n");
 }
 $multi=0; $uniq=0;
 foreach ( $runwayPlans as $k => $v ) {
   $count = count ( $v );
   if ( $count > 1 ) {
     $multi++;
     //print ( "NOTE Builder/Plan [" . $k . "] has $count recs\n");
  } else {
     $uniq++;
  } 
 }
 print ( "NOTE Found $uniq unique Runway Builder/Plan recs & $multi multi recs\n");

 return(1);
}

function build_maxtix_from_csv ( $latestCsv , $mapArr , &$matrix , &$priority ) {

  /* look for fixed key and value in latestCsv
  [0]                               [15]         [16]
   000000118181,MN,02020,,,,,,,,,,,,,assessed_val,000000000000000
   000000118181,MN,02020,,,,,,,,,,,,,land_acres,00000000000000000000

   [PERRYCORP^PERRY~PERRY HOMES^1^770~Devonshire 60'~Devonshire   Reserve^133^P3397W^19] => Array
        (
            [price] => 498900
            [size] => 3397
        )
  */
  $old_key = ""; $key_cnt =1; $old_cnt = -1; $recs=1;
  if ( !file_exists( $latestCsv )) { print ( "ERROR No fixed Csv file $latestCsv found\n"); exit (0); }
  $file = fopen( $latestCsv, 'r');
  while (($line = fgetcsv($file)) !== FALSE) {
   if ( count ($line) > 2 ) {
   	  if ( count ($line) != 17 ) { print ( "ERROR Line $recs in $latestCsv is bad got " . count ($line) . "\n"); }
      else {
        // only work on lines with 17 fields ie 16 keys + value
        //"David Weekley Homes","DavidWeekley~David Weekley Homes","Sandbrock Ranch",Belton,0,,,,,,,,,,,BasePrice,356990
   	    $key = "";
   	    for ( $i =0 ; $i < 15 ; $i++ ) { $key .= $line[$i] ."^"; } // re-create key
        $key = rtrim ( $key, "^" );
   	    $target = $line[15];
   	    $val = $line[16]; 
   	    //print ( "$key - $target - $val\n");

   	    //process the record
        //
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
     	    } else { // new $key
            //print ( "$key - $target - $val\n");
            if ( ltrim($val, "0") != ""  ) {
           	  $matrix[$key][$dest_tag] = $val; // ltrim($val, "0"); // save the value
              $priority[$key][$dest_tag] = $dest_priority ; // save the value priority
              //print ( "$key -> " . $matrix[$key][$dest_tag] . " -> " . $priority[$key][$dest_tag] . "\n");
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


function get_unique_words ( $str ) {

  $s = preg_replace("/[^0-9A-Z ]/", " " , strtoupper ( $str ) );
  //$s = str_replace ( "HOMES" , "" , $s );
  $tmp = array_map ( 'trim' , explode (" " , $s));
  return ( trim( implode ( " " , array_unique($tmp))) );
}

function words_match ( $these , $areIn , $mode="" ) {

  // will match "60" to MUSTANG LAKES 60"
  // will match "1234" to "P1234W"
  $t = trim ( preg_replace("/[^0-9A-Z ]/", " " , strtoupper ( $these ))); // get rid of junk
  $a = trim ( preg_replace("/[^0-9A-Z ]/", " " , strtoupper ( $areIn )));
  //
  if ( $t == "" || $a == "") {
    print ( "ERROR word match [$t] [$a]\n");
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

function match_plans ( &$matrix , &$runwayPlans, &$combined ) {

 /* matrix...
     [PERRYCORP^PERRY~PERRY HOMES^1^770~Devonshire 60'~Devonshire   Reserve^133^P3393W^17] => Array
        (
            [price] => 506900
            [size] => 3393
        )
    runway...
      [COVENTRY^5959] => Array
        (
            [Coventry 55 - Pomona^55'] => Array
                (
                    [price] => 446990
                    [front] => 55'
                    [design] => Worth
                    [status] => no-match
                )

        )
 */

$noMatch = array();
$runPass = false; // first pass through the runway data
$b_model_list = array(); $r_model_list = array(); 
$b_builder_list= array(); $r_builder_list = array();
$b_plan_list=array(); $r_plan_list=array();
//
foreach ( $matrix as $b_k => $b_v ) {   

  // key: PERRYCORP^PERRY~PERRY HOMES^1^740~Johnson Ranch 55'~Johnson Ranch^98^P2504S^15
  //      David Weekley Homes^DavidWeekley~David Weekley Homes^Sandbrock Ranch^Belton^0
  //      CORPHIGHLAND^37~Highland Homes~Highland^865~Sandbrock Ranch: 45ft. lots ^0^Plan Corby~Plan Corby^0
  //      0      1         2                   3               4     5    6
  //      CORP ^ BUILDER ^ multi-build-option ^MODEL/COMMUNITY^multi^PLAN^always multi-plan 
  $tmp = explode ( "^", $b_k );
  if (!$runPass ) {
    if ( count ($tmp ) == 7 ) { // perry style XML, multi builders, multi community
      $b_pos = 1; $m_pos = 3; $p_pos = 5; 
    } elseif ( count ($tmp ) == 5) { // David style XML, single builder, single community
      $b_pos = 1; $m_pos = 2; $p_pos = 3; 
    } elseif ( count ($tmp ) == 6) { // Highland SandBrock style XML, single builder, multi community
      $b_pos = 1; $m_pos = 2; $p_pos = 4; 
    } else {
      print ( "ERROR Unkown format builder feed for [$b_k]\n");
      exit;
    }
  }
  $matrix[ $b_k ][ "rec_status" ] = "no-match"; 

  // tidy builder XML data
  //
  $b_builder = str_replace ( "HOMES" , "" , strtoupper ($tmp[ $b_pos ]));
  $b_builder = get_unique_words ( $tmp[ $b_pos ]);
  //
  $b_model = str_replace ( "FT.", "" , strtoupper ($tmp[ $m_pos ] ));
  $b_model = str_replace ( " LOTS", "" , $b_model );
  $b_model = get_unique_words ( $b_model); 
  //
  $b_plan = strtoupper ( $tmp[ $p_pos ] );
  $b_plan = trim( str_replace ( "PLAN", "" , $b_plan ));
  $b_plan = get_unique_words ( $b_plan); 
  // get rid of "feet" and words like plan, convert numbers to nearest 5 
  $a = explode ( " " , $b_model );
  $out="";
  foreach ( $a as $k => $v ) {
    if ( is_numeric ($v) && $v >= 30 && $v <= 100 ) { $v=round($v/5) * 5; } 
    $out .= $v . " ";
  }
  $b_model = trim ( $out );
  if ( !isset ( $model_u_list [ $tmp[ $m_pos ] ])) {
    print ( "DEBUG Unique Builder Model is [$b_model] from [" . $tmp[ $m_pos ] . "]\n");
    $model_u_list [ $tmp[ $m_pos ] ]=true;
  }

  // keep a unique list off builder models/communities for debug
  //
  if (isset ( $b_model_list[$b_model] )) { $b_model_list[$b_model]++; } else { $b_model_list[$b_model]=1; }
  if (isset ( $b_builder_list[$b_builder] )) { $b_builder_list[$b_builder]++; } else { $b_builder_list[$b_builder]=1; }
  if (isset ( $b_plan_list[$b_plan] )) { $b_plan_list[$b_plan]++; } else { $b_plan_list[$b_plan]=1; }
  //
  foreach ( $runwayPlans as $r_k => $r_v ) { 
    //
    $tmp = explode ( "^" , $r_k );
    $r_builder = strtoupper ( trim ( $tmp[0]));
    $r_plan = strtoupper ( trim ( $tmp[1]));
    if ( !$runPass ) { // log all runway builders
      if ( isset ( $r_builder_list[$r_builder] )) { $r_builder_list[$r_builder]++; } else { $r_builder_list[$r_builder]=1; }
    }
    //
    $hit_b = words_match ( $r_builder , $b_builder ); // builders match !

    if ( !$runPass && $hit_b ) { //only plans where builders match
      if ( isset ( $r_plan_list[$r_plan] )) { $r_plan_list[$r_plan]++; } 
      else { $r_plan_list[$r_plan]=1; }
    }
    $hit_p = false;
    if ( $hit_b ) {
       $hit_p = words_match ( $r_plan , $b_plan ); // runway plan is within builder plan or matches
    }
    //
    if ( $hit_b && $hit_p ) {
      print ( "DEBUG Builder+Plan match! [$r_builder] == [$b_builder] [$r_plan] == [$b_plan]\n");
      $matrix[$b_k]["rec_status"] = "Builder+Plan-match";
       // ok at least the builder and plan OK
      $r_planCnt = count( $r_v ); // how many varients for Builder+Plan
      foreach ( $r_v as $r_k2 => $r_v2 ) {
        $r_model = get_unique_words ( $r_k2 ); // input like - Coventry 55 - Pomona^55'
        $r_model = str_replace ( $r_builder, "" , $r_model ); // get rid of builder if its used
        $r_model = str_replace ( "HOMES" , "" , $r_model ); // get rid of builder if its used
        $r_model  = preg_replace("/[^0-9A-Z ]/", " " , $r_model); // get rid of junk
        $r_model  = trim( preg_replace('!\s+!', ' ', $r_model )); // get rid of extra spaces
        //print ( "DEBUG run-model [$r_k2] converted to [$r_model]\n");
        //
        if ( count ( explode ( " " , $r_model )) < 2 && is_numeric ( $r_model )) {  // ie was "Perry 60" and Perry was removed
          $r_model_use = "poor"; // doesnt carry width and community
          $r_bad_key = $r_model . "^" . $r_k;
          if (!isset ( $r_model_unusable[ $r_bad_key ] )) {
            $r_model_unusable[ $r_bad_key ]=true; // will get set lots of times
            print ( "ERROR Runway Model [$r_model] for [$r_k][$r_k2] is likely un-useable\n");
          }   
        } else {
          $r_model_use = "ok";
          if (!isset ( $r_model_list[ $r_model ] )) { $r_model_list[ $r_model ] = true; }
        }
        //
        print ( "DEBUG Runway>Model [$r_model] == [$b_model]\n");
        $hit_m = words_match ( $r_model , $b_model);
        if ( !$hit_m  && $r_planCnt == 1 ) {
          // maybe the builder does not state the frontage ie run[45 WOLF RANCH] == build[WOLF RANCH]
          $hit_m = words_match ( $b_model , $r_model );
        }
        //
        if ( $hit_m && $r_model_use == "ok" ) {
          //  should Hit: [PERRYCORP^PERRY HOMES^1^Pomona 50'^46^P2628W^20] [PERRY^2628] [Perry 50 - Pomona^50'] cnt=1
          $runwayPrice = $runwayPlans[$r_k][$r_k2]["price"];
          $builderPrice= $matrix[$b_k]["price"];
          $runwaySize = $runwayPlans[$r_k][$r_k2]["size"];
          $builderSize= $matrix[$b_k]["size"];
          if ( $builderPrice == $runwayPrice ) { $res="Price-Match"; } else { $res="Price-diff"; }
          if ( $builderSize == $runwaySize ) { $res2="Size-Match"; } else { $res2="Size-diff"; }
          print ( "Hit $res $res2: [$b_k] [$r_k] [$r_k2] cnt=$r_planCnt " . "B=" . $builderPrice . " R=" . $runwayPrice . " Gap=" . ( $builderPrice - $runwayPrice) . " $builderSize == $runwaySize\n");
          //
          if ( strpos ( $runwayPlans[$r_k][$r_k2]["rec_status"] , "Price" ) !== false ) {
            // Already has a Price assesement, not good
            print ( "ERROR Duplicate result $res: [$b_k] [$r_k] [$r_k2]\n");
            $runwayPlans[$r_k][$r_k2]["rec_status"] .= " AND DUP " . $res;
            $runwayPlans[$r_k][$r_k2]["match_key"] .= " AND DUP " . $b_builder . " - " . $b_model . " - " . $b_plan;
            $matrix[$b_k]["rec_status"] .= " AND DUP " . $res;
          } else {
            $runwayPlans[$r_k][$r_k2]["rec_status"] = $res;
            $runwayPlans[$r_k][$r_k2]["match_key"] = $b_builder . " - " . $b_model . " - " . $b_plan;
            $matrix[$b_k]["rec_status"] = $res;
          }
          //print ( "Via: $r_builder , $b_builder | $r_model , $b_model | $r_plan , $b_plan \n");
        }
      }
    }
  }
  $runPass = true; // we have done one loop through runway data
}  
print ( "--Builders--\n");
foreach ( $r_builder_list as $k => $v ) print ( "Builder Runway [$k] has $v recs\n");
print ( "..\n");
foreach ( $b_builder_list as $k => $v ) print ( "Builder Source [$k] has $v recs\n");
print ( "--Plans--\n");
foreach ( $r_plan_list as $k => $v ) print   ( "Plan Runway   [$k] has $v recs\n");
print ( "..\n");
foreach ( $b_plan_list as $k => $v ) print   ( "Plan Builder  [$k] has $v recs\n");
print ( "--Models--\n");
foreach ( $r_model_list as $k => $v ) print   ( "Model Runway   [$k] has $v recs\n");
print ( "..\n");
foreach ( $b_model_list as $k => $v ) print   ( "Model Builder  [$k] has $v recs\n");

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