<?php

ini_set("auto_detect_line_endings", true);
libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', 1); // Do send to output
ini_set('log_errors', 1 ); // send errors to log

ini_set('memory_limit', '512M');

// testing for community match which can be quite variable
//$hit_m = words_match ( "50 POMONA" , "50 POMONA 60" , "debug");
//exit;

/* see "hard maps" for legacy data key problems 
Perry Homes^50' | Pecan Square 50
*/

$runwaySource = get_support_barLin ( "runway.source" ); // get developers
if ( sizeof( $runwaySource ) == 0 ) {
  print ( "ERROR Can't find essential work scope file: runway.source\n" ); // will exit
  exit(0);
} 

$builderSource = get_support_barLin ( "builder.source" ); // get the scope of work, returns empty if not found
if ( sizeof( $builderSource ) == 0 ) {
  print ( "ERROR Can't find essential work scope file: builder.source\n" ); // will exit
  exit(0);
} 

// Get args passed. Check if limited to a small set of jobs
//
$revisedRunwaySource=array(); // we will not use unless valid developers are passed as args
foreach( $argv as $cnt => $v ) {
  $value =trim ( strtolower( $v )); 
  if ( $cnt == 0 ) {} // ignore prog name
  else {
    foreach ( $runwaySource as $scope ) {
      $parts = array_map ( 'trim' , explode ("|" , $scope ));
      if ( strtolower ( $parts[0] ) == $value ) { 
        $revisedRunwaySource[] = $scope; 
        unset ( $argv[$cnt] ); // found a developer override, remove so next test does not fail
      }
    }
  }
}
if ( count ( $revisedRunwaySource ) > 0 ) $runwaySource = $revisedRunwaySource;  // shorter list taken from command line
//
$env = "NA";
$debugModeArgv = false; 
$lotUpdateArgv = false;
$revisedClientSource=array(); // we will not use unless valid builders are passed as args
foreach( $argv as $cnt => $v ) {
  $value =trim ( strtolower( $v )); 
  if ( $cnt == 0 ) {} // ignore
  elseif ( $value  == "debug") $debugModeArgv = true; 
  elseif ( $value  == "prod-post") $env = "PROD"; 
  elseif ( $value  == "demo-post") $env = "DEMO"; 
  elseif ( $value  == "lot-update") $lotUpdateArgv = true;  // update lot budget values
  else {
    $hit = false;
    foreach ( $builderSource as $scope ) {
      $parts = array_map ( 'trim' , explode ("|" , $scope ));
      if ( strtolower ( $parts[0] ) == $value ) { $revisedClientSource[] = $scope; $hit = true; }// names match
    }
    if ( ! $hit ) {
      print ( "ERROR Unknown command line parameter [" . $v . "] Builders from builder.source or debug or lot-update allowed\n" );
      exit (0);
    }
  }
}

if ( count ( $revisedClientSource ) > 0 ) $builderSource = $revisedClientSource;  // shorter list taken from command line


// Loop through runway developers and all requested builders
//
$lotWork = array();  // min and max store by key
foreach ( $runwaySource as $runwayScope ) {

  $combined = array(); // here as they need to add for each builder


  $parts = array_map ( 'trim' , explode ("|" , $runwayScope ));
  $devName = $parts[0];

  // Get the Runway source data, make useful keys
  $planList = $devName . ".runway.planlist.csv";  // ie Hillwood.runway.planlist.csv
  $runwayPlans=array(); // reset
  print ( "\nNOTE --- Processing Runway developer --- $devName\n");
  build_plan_keys ( $devName, $planList, $runwayPlans );
 
  $fh=fopen ( $devName . ".match.csv", "w" ); // will delete old
  fwrite ( $fh, 
            "devName" . "," . "buildName" .",". "hitType" .",". "priceResult" .",". "sizeResult" .",". 
            "key_b_builder" .",". "key_b_plan" .",". "key_b_community" .",". 
            "key_r_builder" .",". "key_r_plan" .",". "key_r_community" .",". "r_plan_cnt" .",".
            "builderPrice" .",". "runwayPrice" .",".
            "builderSize" .",".  "runwaySize" . "\n");
  fclose($fh);

  foreach ( $builderSource as $scope ) {

    $parts = array_map ( 'trim' , explode ("|" , $scope ));
    $buildName = $parts[0];
    $latestCsv = $buildName . ".latest.csv";
    print ( "\nNOTE --- Processing builder --- $buildName from $latestCsv\n");

    // what fields will we collect 
    if ( file_exists ( $buildName . ".field.map" )) {
       $fieldMap =  $buildName . ".field.map"; 
    } else {
       $fieldMap =  "builder.field.map"; 
    }
    $mapArr = adj_map ( $fieldMap ); // get the field-map, rotate to useful format
    if ( !is_array( $mapArr ) || count($mapArr) == 0 ) {
      print ( "ERROR No $fieldMap or it's empty\n");
      exit (0);
    } else {
      print ( "NOTE Using $fieldMap to map price, size etc\n");
    }

    $builderData=array(); $priority=array(); $buildPlanCnt=array(); // reset these

    build_maxtix_from_csv ( $latestCsv , $mapArr , 
                          $builderData , $priorty, $buildPlanCnt ); // set these

   // print_r ( $builderData );
    //print_r ( $priorty);

    match_plans ( $devName, $buildName, $builderData , $runwayPlans, $buildPlanCnt , $lotWork  ); // update all these these
   
    /* 
    // NOTE Builder result [Price-diff | Size-diff | 412990] is 1 records - to much noise now
    // show impact on builder array
    $tmpArr=array(); 
    foreach ( $builderData as $k => $v ) {
      $tmp = $v["rec_status"];
      if ( isset ( $tmpArr[$tmp] ) ) $tmpArr[$tmp]++;
      else $tmpArr[$tmp]=1;
    }
    foreach ( $tmpArr as $k => $v ) {
      print ( "NOTE Builder result [$k] is $v records\n");
    }
    */
  }  
  

  //print_r ( $runwayPlans );

  // show impact on runway array after all builders processed
  print ( "\nNOTE --- Sending results to $devName.match.csv ---\n");
  $summary = array();
  foreach ( $runwayPlans as $k => $v ) {
    $tmp = explode ("^" , $k ); // [COVENTRY^5959]
    $bld = $tmp[0]; // r_builder
    $pln = $tmp[1]; // r_plan
    if ( !isset ( $summary[$bld]["miss"] )) { $summary[$bld]["miss"]=0; $summary[$bld]["which"]=""; } 
    if ( !isset ( $summary[$bld]["hit" ] )) { $summary[$bld]["hit" ]=0; }
    // 
    foreach ( $v as $k2 => $v2 ) { // Coventry 55 - Pomona^55
      $mod = runway_model ( $k2 , $bld ); 
      if ( $v2["rec_status"] == "no-match" ) {
        $summary[$bld]["miss"]++;
        $summary[$bld]["which"] .= "\nMissed: " . $k . ":" . $k2;

        $runwayPrice = $runwayPlans[$k][$k2]["price"];
        $runwaySize = $runwayPlans[$k][$k2]["size"];

        $fh=fopen ( $devName . ".match.csv" , "a" );
        fwrite( $fh, 
            $devName . "," . "na" .",". "Miss" .",". "na" .",". "na" .",". 
            "na" .",". "na" .",". "na" .",". 
            $bld .",". $pln .",". $mod .",". "0" .",".
            "0" .",". $runwayPrice .",".
            "0" .",".  $runwaySize . "\n");
        fclose($fh);

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
      print ( "NOTE For $k matches=$h misses=$m miss-list--->$w");
      print ( "\n--end for $k\n");
    }
  }
  //print_r ( $lotWork );
  //
  // get last run
  $f_lfp = $devName . '.lot.work.csv';
  $last_lotWork = [];
  if ( file_exists ( $f_lfp ) ) { 
    $lfp= fopen( $f_lfp , 'r');
    while (($line = fgetcsv($lfp , 0 , "^" , '"' , "\\" )) !== FALSE ) { // ^ delimiter
      $last_lotWork[ trim ( $line[0]) ] = trim ( $line[1]); 
    }
    fclose($lfp);
  }
  //print_r ( $last_lotWork );
  //print_r ( $lotWork );

  if ( $lotUpdateArgv ) {
    foreach ( $lotWork as $k88 => $v88 ) {
      if ( isset ($last_lotWork[$k88]) && $last_lotWork[$k88] == $v88 ) {
        print ( "NOTE $devName : Same lot vals no update $k88\n" );
      } else {
        $kBits = array_map('trim', explode ( "|" , $k88 ));
        $vBits = array_map('trim', explode ( "|" , $v88 ));
        $env     = $kBits[0];
        $devName = $kBits[1];
        $extra    = $kBits[2];
        $clientId = $kBits[3];
        $runwayEstates  = $kBits[4]; // changed to estates, was range
        $rawBuilder = $kBits[5];
        $lotGroup  = $kBits[6];
        $min = $vBits[0]; // min
        $max = $vBits[1]; 
        //
        $tmp417 = explode ( "," , $runwayEstates ); // plans maybe for mutiple esates
        foreach ( $tmp417 as $oneEstate ) {  
          $oneEstate = trim( $oneEstate );
          $res = lot_budget_update ( $env , $rawBuilder , $clientId , /*$runwayRange*/ $oneEstate , $lotGroup, $min , $max ); 
          if ( strpos ( $res, "FAIL" ) !== false ) {
            print ( "ERROR $devName : Lot Update $k88 with $v88 -- $res -- Builder[$rawBuilder] Community[$oneEstate] LotGroup[$lotGroup]\n");
          } else {
            print ( "NOTE $devName : Lot Update $k88 with $v88 -- $res -- Builder[$rawBuilder] Community[$oneEstate] LotGroup[$lotGroup]\n");
          }
        } //for each
      }
    }
  }
  // save this run
  $lfp = fopen( $f_lfp , 'w');  
  foreach ($lotWork as $k => $v ) {
    fwrite ( $lfp, $k . "^" . $v . "\n" );
  }
  fclose($lfp);
}
//print_r ( $summary );
// end of mainline


function lot_budget_update ( $env , $builderName , $clientId ,  $estateName , $lotGroup, $min , $max ) {

  $rtnMess = "";
  $tmp = explode ( "," , $estateName ); // plans maybe for mutiple esates
  foreach ( $tmp as $oneEstate ) {  
    // API end point
    if ( $env == "PROD") {
      //$url = "https://368u2vz15k.execute-api.us-west-1.amazonaws.com/prod/external/lotbudgetupdate"; // wrong
      $url = "https://368u2vz15k.execute-api.us-west-1.amazonaws.com/live/external/lotbudgetupdate";
    } else {
      $url = "https://368u2vz15k.execute-api.us-west-1.amazonaws.com/demo/external/lotbudgetupdate";
      $env = "DEMO"; // allows update without price update
    }
    //$x_api_key = "0CmmBaaTCr3thPCAEQ4rf3oHaS8cB8lw9rnKjQLx"; 
    $x_api_key = "OJ6CmJRgVd6ikQSsMv0c88xFmv8Xh1xC6AtJ6tCI";
    $data = array (

    "env" => $env, // DEMO or PROD
    "clientId" => $clientId,
    "estateName" => trim($oneEstate), // WAS $estateName,  // "Sandbrock Ranch"
    "builderName" => $builderName, // "Highland Homes"
    "lotGroup" => $lotGroup, // 60ft 60'
    "lotCpId" => "null",
    "minBudgetValue" => $min,
    "maxBudgetValue" => $max

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

    // convert to array
    if ( $response == false || strlen ( $response ) == 0 ) { // is still a json string
      $rtnMess .= "FAIL - $oneEstate - No Response | ";
    }
    $messArr=json_decode( $response, TRUE );
    if ( isset ( $messArr["success"])) {
      if ( $messArr["success"] == true ) $rtnMess .= "OK $oneEstate - Success | ";
      else $rtnMess .= "FAIL - $oneEstate - Not Success - " . $messArr["responseMessage"] . " | ";
    } else {
      $rtnMess .= "FAIL - $oneEstate - Unknown Response | ";
    }
  }
  return ( $rtnMess );
}


function send_price ( $env , $builderName , $planCpId , $clientId , $planCost ) {

  // API end point
  $url = "https://368u2vz15k.execute-api.us-west-1.amazonaws.com/demo/external/homedetailupdate";
  //$x_api_key = "0CmmBaaTCr3thPCAEQ4rf3oHaS8cB8lw9rnKjQLx"; 
  $x_api_key = "OJ6CmJRgVd6ikQSsMv0c88xFmv8Xh1xC6AtJ6tCI";
  $data = array (
    "env" => $env,
    "builderName" => $builderName,
    "planCpId" => $planCpId,
    "clientId" => $clientId,
    "planCost" => $planCost 
  );  
  // 
  $content = json_encode( $data);
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

  // convert to array
  if ( $response == false || strlen ( $response ) == 0 ) { // is still a json string
     return ( "FAIL - No Response" );
  }
  $messArr=json_decode( $response, TRUE );
  if ( isset ( $messArr["success"])) {
    if ( $messArr["success"] == true ) return ( "Success" );
    else return ( "FAIL - Not Success - " . $messArr["responseMessage"] );
  } else {
    return ( "FAIL - Unknown Response" );
  }

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
	  if ( count ( $line ) != 2) { print ( "ERROR Line $v is bad\n"); return ( $mapArr); }
	  $tmp2 = array_map ( "trim" , explode ( "," , $line[1] )); //  py_owner_name , appr_owner_name , legal_desc
	  foreach ( $tmp2 as $pos => $v2 ) {
		  $mapArr[$v2][0] = $line[0];
      $mapArr[$v2][1] = $pos;
	  }
  }
  foreach ( $mapArr as $k => $v ) {
    //print ( "DEBUG Source Key [$k] maps to [" . $v[0] . "] priority " . $v[1] . "\n" );
  }
  return ( $mapArr );
}

function build_plan_keys ( $devName , $planList, &$runwayPlans ) { // Get the Runway source data, make useful keys
 //
 //
 if ( !file_exists( $planList )) { print ( "ERROR No fixed Csv file $planList found\n"); return (0); }
 $file = fopen( $planList, 'r');
 $i=0; $j=0; $ownerList=array();
 while (($line = fgetcsv($file,0,"|",'"',"\\")) !== FALSE) {
  //
  /*
  6462583460707486222648312180001543571599232224252|Available|Perry Homes|2997|Perry 55 - Pomona|61' 9.5"|490900|55'||2997
  5427103505110052438524321084028326531599232375663|Available|Perry Homes|2999|Perry 55 - Pomona|69' 4.6"|516900|55'||2999
  7442764071122001811146627814426341431563782391223|Available|Perry Homes|2999W|Perry Homes|69' 3.0"|516900|50'||2999W
  8650470234284843631366440023637771871596171723351|Available|David Weekley Homes|Woodbank|David Weekley Homes 40 - Harvest|76' 9.0"|294990|40'||5433
  */
  $i++;
  if ( count ($line) > 20 ) {
    //
    //
    // must match to runway_get_plan.php
    $planCpId =   trim( $line[0]); //$v['clientproductid'] ."|". ---  $planCpId 
    $clientId =   trim( $line[1]); //$v['clientid'] . "|" --- $clientId for put
    $clientCpId = trim( $line[2]); //['clientcompanyid'] . "|".
    $status =  trim( $line[3]);  // $v['currentstatusname'] ."|".
    $owner =   trim( $line[4]);  // $v['ownername'] ."|".
    $design =  trim( $line[5]);  // $v['designproductname'] ."|".
    $range =   trim( $line[6]);  // $v['rangeproductname'] ."|".
    $branch  = trim( $line[7]);  // branch
    $estates = trim( $line[8]);  // comma seperated estates
    $frontTxt= trim( $line[9]);  // $v['productDepthFormatted'] ."|". // => 58' 1.0"
    $size =    trim( $line[10]);  // $v['productSizeFormatted']
    $price =   trim( $line[11]);  // $v['productprice'] ."|".   // => 279990
    $front =   trim( $line[12]); // $v['canfitonwidthFormatted'] ."|".
    $beds =    trim( $line[13]); // $v['noofbedrooms']  ."|".
    $baths =   trim( $line[14]); // $v['noofbathrooms']  ."|".
    $carParks= trim( $line[15]); // $v['noofcarparks']  ."|".
    $storeys = trim( $line[16]); // $v['noofstoreys']  ."|".
    $number =  trim( $line[17]); // $v['productnumber'] ."|".
    $name =    trim( $line[18]); //  $v['productname'] . "\n" );
    $l_f_nam =    trim( $line[19]);
    $l_f_alias =    trim( $line[20]);
    //
    if ( $status == "Available" ) {

      $j++;
      //
      if ( isset ( $ownerList[ $owner ])) { $ownerList[ $owner ]++; }
      else { $ownerList[ $owner ] = 1; }
      
      $rawOwner = $owner;
      // Homes is sometimes used and sometimes not
      $owner = trim ( str_replace ( " HOMES" , "" , strtoupper ( $owner )));
      if ( $owner == "" ) {
        print ( "ERROR $devName : Runway $design,$name,$range - No Owner generated from [$rawOwner]\n");
      }

      if ( $estates == "" ) {
        //print ( "WARN $devName : Runway $design,$name,$range - No estates generated from [$estates]\n");
      }

      $rawFront=$front; // remember it
      $front  = preg_replace("/[^0-9\.]/", '', $front); // ie 55' goes to 55
      if ( is_numeric ( $front ) ) { 
        $front = round($front / 5) * 5;
      } else {
        print ( "ERROR $devName : Runway $owner,$design,$name,$range - Bad front numeric convert- [$front] generated from [$rawFront]\n");
        $front = 0;
      }
      $l_f_alias = preg_replace("/[^0-9\.]/", '', $l_f_alias);
      if ( is_numeric ( $l_f_alias )) { 
        print ( "WARN $devName : Runway $owner,$design,$name,$range frontage $front will be replaced by alias $l_f_alias\n");
        $front =  $l_f_alias; 
      }
      if ( $front < 20 || $front > 120 ) {
        print ( "ERROR $devName : Runway $owner,$design,$name,$range - frontage out of scope - [$front] generated from [$rawFront]\n");
        $front ="";
      }

      // convert the range frontage to nearest 5
      $rawRange = $range; $rangeFront = -1;
      $tmp =  explode ( " " , trim($range) ); $newRange="";
      foreach ( $tmp as $bit) {
        if ( is_numeric ($bit ) && $bit > 20 && $bit < 120 ) {
          $bit = round($bit / 5) * 5;
          $rangeFront = $bit;
        }
        $newRange .= $bit . " ";
      }
      $range = trim ( $newRange);
      if ( $range == "" ) {
        print ( "ERROR $devName : Runway $owner,$design,$name - No Range generated from [$rawRange]\n");
      }
      if ( $rangeFront != -1 && $rangeFront != $front) {
        print ( "ERROR $devName : Runway $owner,$design,$name - Front in range [$rangeFront] not sames as fits on [$front]\n");
      }

      // check if branch or estates add extra value
      if ( $estates == "" ){
        // no estates
        if ( $branch != "" && strpos( strtoupper($range), strtoupper($branch) ) === false ) {
          $range = $range . " " . $branch; // branch exists
        }
      } else {
        // we have estates
        $tmp876 = array_map ( 'trim' , explode ( "," , $estates ));
        foreach ( $tmp876 as $a6 => $v6 ) {
          if ( strpos( strtoupper($range) , strtoupper($v6) ) === false ) {
            $range = $range . " , " . $v6;
          }
        }
      }
      // normally use the name but it may be a plan that has both desc and number
      if ( trim($name) != trim($design) ) {
        $newDesign = "";
        $tmp443 =  array_map ( "trim" , explode ( " ", $design ));
        foreach ( $tmp443 as $a8 => $k8 ){
          $wdtmp = preg_replace("/[^a-zA-Z]/", '', $k8 );
          //if ( !is_numeric ($k8 ) && strlen($k8) > 1 ) $newName .= $k8 . " ";
          if ( strlen($wdtmp) > 0 ) $newDesign .= $k8 . " ";
        }
        $newDesign = trim ( $newDesign );
        //
        if ( $newDesign != "" ) {
          print ( "WARN $devName : Runway $owner - Design[$design] not equal Name[$name] setting to [$newDesign]\n");
          $name = $newDesign  ;
        }
      }

      // key should be $owner + name.

      if ( $owner == "" || $name == "" || $range == "" || $front == "" ) {
        print ( "ERROR $devName : Runway owner [$owner] name [$name] range [$range] front [$front] - field empty\n");
      }
      $key = $owner . "^" . $name;
      //if ( $name != $design ) { print ( "WARN for $key plan design is $design\n"); }
      $key2 = $range . "^" . $front;
      
      // crap record - Available|Perry Homes|2694|Perry 55 - Pomona|65' 2.0"|478900|50'||2694
      $tmp =  explode ( " " , trim($range) );
      foreach ( $tmp as $bit) {
        if ( is_numeric ($bit ) && $bit > 30 && $bit < 100 ) {
          if ($bit != $front  &&  !is_numeric ( $l_f_alias ) ) {
            print ( "ERROR $devName : Runway $owner $name mixed frontage - [$range] [$front] generated from [$rawFront]\n");
            $key2 = $range; // override
          }
        }
      }
      // Fix up bad keying

      //if ( $key2 == "Perry Homes^50" ) $key2 = "Pecan Square 50"; // hard legacy map
      //
      $save=true;
      if ( isset ( $runwayPlans[$key]) ) { 
        // should get dups, we want this
        if ( isset ( $runwayPlans[$key][$key2] )) {
          print ( "WARN $devName : Runway duplicate owner+name+range+front key [$key][$key2]\n");
          $save=false;
        }
      } 
      if ( $save ) {
        $runwayPlans[$key][$key2]["planCpId"] = trim( $planCpId );
        $runwayPlans[$key][$key2]["clientId"] = trim( $clientId );
        $runwayPlans[$key][$key2]["rawBuilder"] = trim( $rawOwner );
        $runwayPlans[$key][$key2]["price"] = trim( $price );
        $runwayPlans[$key][$key2]["size"] = trim( $size );
        $runwayPlans[$key][$key2]["front"] = trim( $front );
        $runwayPlans[$key][$key2]["design"] = trim( $design );
        $runwayPlans[$key][$key2]["range"] = trim( $range );
        $runwayPlans[$key][$key2]["estates"] = trim( $estates );
        $runwayPlans[$key][$key2]["rec_status"] = "no-match";
        $runwayPlans[$key][$key2]["match_key"] = "NA";
      }
    } 
  } else {
   print ( "WARN $devName : Found short line $i in Runway $planList. Cnt=" . count ($line) . "\n");
  }
 }
 fclose($file);
 print ( "NOTE $devName : Found $i lines in Runway $planList, $j are available status\n");
 //
 foreach ( $ownerList as $k => $v ) {
   //print ( "DEBUG Runway Owner/Builder [" . $k . "] has $v recs\n");
 }
 $multi=0; $uniq=0;
 foreach ( $runwayPlans as $k => $v ) {
   $count = count ( $v );
   if ( $count > 1 ) {
     $multi++;
     //print ( "DEBUG Builder/Plan [" . $k . "] has $count recs\n");
  } else {
     $uniq++;
  } 
 }
 print ( "NOTE $devName : Found $uniq unique Runway Builder/Plan recs & $multi multi recs\n");

 return(1);
}

function build_maxtix_from_csv ( $latestCsv , $mapArr , &$matrix , &$priority , &$buildPlanCnt ) {

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
  $overRide=0; $newVal=0;
  if ( !file_exists( $latestCsv )) { print ( "ERROR No fixed Csv Builder file $latestCsv found\n"); return (0); }
  $file = fopen( $latestCsv, 'r');
  while (($line = fgetcsv($file)) !== FALSE) {
   if ( count ($line) > 2 ) {
      if ( count ($line) != 17 ) { print ( "ERROR Line $recs in Builder $latestCsv is bad got " . count ($line) . "\n"); }
      else {
        // only work on lines with 17 fields ie 16 keys + value
        //"David Weekley Homes","DavidWeekley~David Weekley Homes","Sandbrock Ranch",Belton,0,,,,,,,,,,,BasePrice,356990
   	    $key = "";
   	    for ( $i =0 ; $i < 15 ; $i++ ) { $key .= $line[$i] ."^"; } // re-create key
        $key = rtrim ( $key, "^" );
   	    $target = $line[15];
   	    $val = $line[16]; 
   	    //print ( "HACK $key - $target - $val\n");

   	    //process the record
        //
        if ( isset ( $mapArr[$target] ) ) { 

          if ( !isset ( $matrix[ $key ] )) {
            //set unprocessed status
            $matrix[ $key ][ "rec_status" ] = "no-match"; 
            $key_cnt++;

            // different builder feeds
            // key: PERRYCORP^PERRY~PERRY HOMES^1^740~Johnson Ranch 55'~Johnson Ranch^98^P2504S^15
            //      David Weekley Homes^DavidWeekley~David Weekley Homes^Sandbrock Ranch^Belton^0
            //      CORPHIGHLAND^37~Highland Homes~Highland^865~Sandbrock Ranch: 45ft. lots ^0^Plan Corby~Plan Corby^0
            //      0      1         2                   3               4     5    6
            //      CORP ^ BUILDER ^ multi-build-option ^MODEL/COMMUNITY^multi^PLAN^always multi-plan   // key: PERRYCORP^PERRY~PERRY HOMES^1^740~Johnson Ranch 55'~Johnson Ranch^98^P2504S^15
            //      [RAVENNAHOMES^Ravenna Homes^1990^0] is an XML with 4 keys, we have to use a dummy community and get that later

            $b_pos = 0; $m_pos = 0; $p_pos = 0; 
            $tmp = explode ( "^", $key);
            if ( count ($tmp ) == 7 ) { // perry style XML, multi builders, multi community
              $b_pos = 1; $m_pos = 3; $p_pos = 5; 
            } elseif ( count ($tmp ) == 5) { // David style XML, single builder, single community
              $b_pos = 1; $m_pos = 2; $p_pos = 3; 
            } elseif ( count ($tmp ) == 6) { // Highland SandBrock style XML, single builder, multi community
              $b_pos = 1; $m_pos = 2; $p_pos = 4; 
            } elseif ( count ($tmp ) == 4) { // Highland SandBrock style XML, single builder, multi community
              $b_pos = 1; $m_pos = 3; $p_pos = 2;   // XML with community as data field, m_pos is dummy int part of key
            } else {
              print ( "ERROR $latestCsv Unknown format builder feed for [$key]\n");
            }
        
            if ( $b_pos == 0 ||  $m_pos == 0 || $p_pos == 0 ) {
              print ( "ERROR $latestCsv Cant process builder key [$key]\n");
            } else {

              // tidy builder XML data
              //
              $b_builder = str_replace ( "HOMES" , "" , strtoupper ( $tmp[ $b_pos ]));
              $b_builder = trim ( get_unique_words ( $b_builder ));

              $b_plan = strtoupper ( $tmp[ $p_pos ] );
              $b_plan = trim( str_replace ( "PLAN", "" , $b_plan ));
              $b_plan = trim ( get_unique_words ( $b_plan)); 

              $b_model = str_replace ( "FT.", "" , strtoupper ($tmp[ $m_pos ] ));
              $b_model = str_replace ( " LOTS", "" , $b_model );

              // nasty taylor Morrison hack
              $b_model = str_replace ( " 40S", " 40" , $b_model );
              $b_model = str_replace ( " 45S", " 45" , $b_model );
              $b_model = str_replace ( " 50S", " 50" , $b_model );
              $b_model = str_replace ( " 55S", " 55" , $b_model );
              $b_model = str_replace ( " 60S", " 60" , $b_model );
              $b_model = str_replace ( " 65S", " 65" , $b_model );
              $b_model = str_replace ( " 70S", " 70" , $b_model );
              $b_model = str_replace ( " 74S", " 75" , $b_model );
              $b_model = str_replace ( " 80S", " 80" , $b_model );

              $b_model = trim ( get_unique_words ( $b_model)); 
              // get rid of "feet" and words like plan, convert numbers to nearest 5 
              $a = explode ( " " , $b_model );
              $out="";
              foreach ( $a as $k => $v ) {
                // in runway we round to nearest 5, for build go up to 10
                if ( is_numeric ($v) && $v >= 20 && $v <= 120 ) { $v=round($v/5) * 5; } // round to nearest 5
                //if ( is_numeric ($v) && $v >= 20 && $v <= 120 ) { $v=ceil($v/10) * 10; } // up to nearest 10
                $out .= $v . " ";
              }
              $b_model = trim ( $out );

              if ( $b_builder == "" )  { print ( "ERROR $latestCsv Empty builder field from [" . $tmp[ $b_pos ] . "]\n" ); $b_builder="NA"; }
              if ( $b_plan == "" )     { print ( "ERROR $latestCsv Empty plan field from [" . $tmp[ $p_pos ] . "]\n" ); $b_plan="NA"; }
              if ( $b_model == "" )    { print ( "ERROR $latestCsv Empty model field from [" . $tmp[ $m_pos ] . "]\n" ); $b_model="NA"; }

              $uniqBuldPlan = $b_builder . "+" . $b_plan;
              if ( isset ( $buildPlanCnt[ $uniqBuldPlan] )) { $buildPlanCnt[ $uniqBuldPlan]++; }
              else { $buildPlanCnt[ $uniqBuldPlan]=1; }

              $matrix[ $key ][ "tidy_builder" ] = $b_builder; 
              $matrix[ $key ][ "tidy_plan" ] =    $b_plan; 
              $matrix[ $key ][ "tidy_model" ] =   $b_model; 
            }
          }

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
              $overRide++;
            } else {
              print ( "ERROR $latestCsv Duplicate builder key [$key]\n");
            }
     	  } else { // new $key
            //print ( "HACK $key - $target > $dest_tag = $val\n");
            if ( ltrim($val, "0") != ""  ) {
              $matrix[$key][$dest_tag] = $val; // ltrim($val, "0"); // save the value
              $priority[$key][$dest_tag] = $dest_priority ; // save the value priority
              //print ( "$key -> " . $matrix[$key][$dest_tag] . " -> " . $priority[$key][$dest_tag] . "\n");
              $newVal++;
            } else {
              print ( "ERROR $latestCsv Empty builder data [$key] [$dest_tag] [$val]\n");
            }
          }
     	}
     }
     $recs++;
   }
  }
  fclose($file);
  print ( "SUMMARY Builder Matrix from $latestCsv done at $recs. Got $key_cnt Keys, $overRide OverRide, $newVal data pairs \n");
  return(1);
}


function get_unique_words ( $str ) {

  $s = preg_replace("/[^0-9A-Z ]/", " " , strtoupper ( $str ) );
  //$s = str_replace ( "HOMES" , "" , $s );
  $tmp = array_map ( 'trim' , explode (" " , $s));
  return ( trim( implode ( " " , array_unique($tmp))) );
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

  // get rid of useless words in search string
  $remove = array( " AT ", " ON ", " IN ", " THE ");
  $t = str_replace($remove, " ", $t );

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

function runway_model ( $mod ,  $bld ) {

  $r_model = get_unique_words ( $mod ); // input like - Coventry 55 - Pomona^55'
  $r_model  = preg_replace("/[^0-9A-Z ]/", " " , $r_model); // get rid of junk
  $tmp = array_map ( 'trim' , explode ( " " , $bld ));
  foreach ( $tmp as $part ) {
    $r_model = str_replace ( $part, "" , $r_model ); // get rid of builder name parts if its used
  }
  $r_model = str_replace ( "HOMES" , "" , $r_model ); // get rid of word HOMES
  $r_model = str_replace ( "LIVE SMART" , "" , $r_model ); // get rid of word HOMES
  $r_model = str_replace ( "RUNWAY INTEGRATION TEST", "" , $r_model );
  //$r_model  = trim( preg_replace('!\s+!', ' ', $r_model )); // get rid of extra spaces
  $tmp = array_map ( 'trim' , explode ( " " , $r_model )); $rebuild="";
  foreach ( $tmp as $part ) {
    if ( is_numeric ( $part ) || strlen($part) > 2 ) $rebuild .= $part . " "; // get rid of words not like community
  }
  return ( trim ( $rebuild ) );
}

function match_plans ( $devName, $buildName, &$matrix , &$runwayPlans, $buildPlanCnt , &$lotWork ) {

global $debugModeArgv;
global $lotUpdateArgv;
global $env;

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
$runPass = false; // first pass through the runway data
$b_model_list = array(); $r_model_good = array(); $r_model_unusable = array();
$b_builder_list= array(); $r_builder_list = array();
$b_plan_list=array(); $r_plan_list=array();
$hit_b_cnt=0; $hit_p_cnt=0; $hit_m_cnt=0;
//
$okUpdate = false;
if ( ( $env == "DEMO" || $env == "PROD" ) && strpos( strtoupper($devName), strtoupper ( $env )) !== false ) $okUpdate = true;
//
foreach ( $matrix as $b_k => $b_v ) {   

  $b_builder = $matrix[ $b_k ][ "tidy_builder" ]; 
  $b_plan    = $matrix[ $b_k ][ "tidy_plan" ];
  if ( isset( $matrix[ $b_k ][ "community" ] )) {
    $b_model   = $matrix[ $b_k ][ "community" ]; 
  } else {
    $b_model   = $matrix[ $b_k ][ "tidy_model" ]; 
  }
  $uniqBuldPlan = $b_builder . "+" . $b_plan;
  if ( isset ( $buildPlanCnt[ $uniqBuldPlan] )) { $b_planCnt = $buildPlanCnt[ $uniqBuldPlan]; }
  else { print ( "ERROR $devName : Can't find builder/plan key $uniqBuldPlan\n"); $b_planCnt=99; }

  //print ( "DEBUG ---- $uniqBuldPlan >> $b_planCnt\n");

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

    // Prep for min and max budgets
    foreach ( $r_v as $r_k2 => $r_v2 ) {
      $lotGroup = $runwayPlans[$r_k][$r_k2]["front"];
      $runwayRange = $runwayPlans[$r_k][$r_k2]["range"];
      $runwayEstates = $runwayPlans[$r_k][$r_k2]["estates"];
      $planCpId = $runwayPlans[$r_k][$r_k2]["planCpId"];
      $clientId = $runwayPlans[$r_k][$r_k2]["clientId"];
      $rawBuilder= $runwayPlans[$r_k][$r_k2]["rawBuilder"];
      $runwayPrice = $runwayPlans[$r_k][$r_k2]["price"];
      $runwaySize = $runwayPlans[$r_k][$r_k2]["size"];

      //
      $extra = "none"; // could be $res2 for size match filters
      $key = $env . "|" . $devName . "|" . $extra  . "|" . $clientId . "|" . /*$runwayRange*/ $runwayEstates . "|" . $rawBuilder . "|" . $lotGroup;
      if ( /* $okUpdate && $lotUpdateArgv && $res2 == "Size-Match" */ true ) {
        if ( isset ( $lotWork[$key] )) {
          $curVals = explode ( "|" , $lotWork[$key] ); // already have this key
          $minBud = $curVals[0];
          $maxBud = $curVals[1];
          if ( $runwayPrice > $maxBud ) $maxBud = $runwayPrice;
          if ( $runwayPrice < $minBud ) $minBud = $runwayPrice;
          $lotWork[$key] = $minBud . "|" . $maxBud;
        } else {
          $lotWork[$key] = $runwayPrice . "|" . $runwayPrice; // starting value
        }
        //print ( "DEBUG LotWork [" . $key . "] => [" . $lotWork[$key] . "]\n");
      }
    }
    //
    $hit_b = words_match ( "builder" , $r_builder , $b_builder ); // builders match ! runway is in builder

    if ( !$runPass && $hit_b ) { //only plans where builders match
      if ( isset ( $r_plan_list[$r_plan] )) { $r_plan_list[$r_plan]++; } 
      else { $r_plan_list[$r_plan]=1; }
    }
    $hit_p = false;
    if ( $hit_b ) {
       if ( $matrix[$b_k][ "rec_status" ] == "no-match" ) $matrix[$b_k]["rec_status"] = "Builder-match";
       $hit_p = words_match ( "plan" , $r_plan , $b_plan ); // runway plan is within builder plan or matches
       $hit_b_cnt++;
       //print ( "DEBUG Got R[$r_builder] == B[$b_builder] Model B[$b_model] Trying Plan R[$r_plan] == B[$b_plan]\n");
    }
    //
    if ( $hit_b && $hit_p ) {
      $hit_p_cnt++;
      //print ( "DEBUG Builder+Plan match! [$r_builder] == [$b_builder] [$r_plan] == [$b_plan]\n");
      if ( $matrix[$b_k]["rec_status"] == "Builder-match" ) $matrix[$b_k]["rec_status"] = "Builder+Plan-match";
       // ok at least the builder and plan OK
      $r_planCnt = count( $r_v ); // how many variants for Builder+Plan
      foreach ( $r_v as $r_k2 => $r_v2 ) {
        //
        $r_model = runway_model ( $r_k2 , $r_builder ); // input like - Coventry 55 - Pomona^55'
        //print ( "DEBUG run-model [$r_k2] converted to [$r_model]\n");
        //
        if ( count ( explode ( " " , $r_model )) < 2 && is_numeric ( $r_model )) {  // ie was "Perry 60" and Perry was removed
          $r_model_use = "poor"; // doesnt carry width and community
          $r_bad_key = $r_model . " " . $r_k;
          if (!isset ( $r_model_unusable[ $r_bad_key ] )) {
            $r_model_unusable[ $r_bad_key ]=true; // will get set lots of times
            print ( "WARN $devName : Runway Model [$r_model] for [$r_k][$r_k2] is likely un-useable\n");
          }   
        } else {
          $r_model_use = "ok";
          if (!isset ( $r_model_good[ $r_model ] )) { $r_model_good[ $r_model ] = true; }
        }
        //
        $hit_m = words_match ( "model r>b" , $r_model , $b_model);
        if ( $hit_m ) $hit_m_cnt++;
        if ( !$hit_m  /* && $r_planCnt == 1 */ ) {
          // maybe the builder does not state the frontage ie run[45 WOLF RANCH] == build[WOLF RANCH]
          $hit_m = words_match ( "model b>r" , $b_model , $r_model );
        }
        if ( $debugModeArgv && !$hit_m ) print ( "DEBUG Miss Model R[$r_model] == B[$b_model] Plan R[$r_plan] == B[$b_plan] r_plan_cnt=$r_planCnt b_plan_cnt=$b_planCnt model status=$r_model_use\n");
        //
        if ( ( $hit_m && $r_model_use == "ok" ) || ( $r_planCnt == 1 && $b_planCnt == 1 && $r_model_use != "ok")) {
          if ( !$hit_m ) { $htype="Risky"; } else { $htype="Hit!"; }
          if ( $matrix[$b_k]["rec_status"] ==  "Builder+Plan+Model-match" ) {
            print ( "FATAL $devName : Builder key [$b_k] already matched\n");
            if ( $debugModeArgv)  print ( "DEBUG Builder key [$b_k] already matched\n");
          } else {
             $matrix[$b_k]["rec_status"] = "Builder+Plan+Model-match";
          }
          if ( $debugModeArgv ) print ( "DEBUG Hit  Model R[$r_model] == B[$b_model] Plan R[$r_plan] == B[$b_plan] type=$htype hit status=$hit_m  r_plan_cnt=$r_planCnt b_plan_cnt=$b_planCnt model status=$r_model_use\n");
          //  should Hit: [PERRYCORP^PERRY HOMES^1^Pomona 50'^46^P2628W^20] [PERRY^2628] [Perry 50 - Pomona^50'] cnt=1
          $planCpId = $runwayPlans[$r_k][$r_k2]["planCpId"];
          $clientId = $runwayPlans[$r_k][$r_k2]["clientId"];
          $rawBuilder= $runwayPlans[$r_k][$r_k2]["rawBuilder"];
          $runwayPrice = $runwayPlans[$r_k][$r_k2]["price"];
          $runwaySize = $runwayPlans[$r_k][$r_k2]["size"];
	        //
	        $builderPrice=-1;$builderSize=-1;
          if ( isset ( $matrix[$b_k]["price"] ))  {
            $builderPrice = floatval ( str_replace(array("$", ","), "", $matrix[$b_k]["price"] )); // sometimes builders use $999,999
          }
          if ( isset ( $matrix[$b_k]["size"]  ))  {
            $builderSize= floatval ( str_replace(array("ft", ","), "", $matrix[$b_k]["size"] ));  // fix any junk in size
          }

          if ( $builderPrice == $runwayPrice ) { $res="Price-Match"; } else { $res="Price-diff"; }
          if ( abs ( $builderSize - $runwaySize ) <= 5000 ) { $res2="Size-Match"; } else { $res2="Size-diff"; }
          //print ( "DEBUG $htype $res $res2: B[$b_k] R[$r_k][$r_k2] cnt=$r_planCnt " . "B=$" . $builderPrice . " R=$" . $runwayPrice . " PriceGap=" . ( $builderPrice - $runwayPrice) . 
          //  " Bsiz=$builderSize Rsiz=$runwaySize\n");
          print ( "NOTE $htype $res $res2: B[$b_builder][$b_plan][$b_model] R[$r_builder][$r_plan][$r_model] cnt=$r_planCnt " . "B=$" . $builderPrice . " R=$" . $runwayPrice . 
            " PriceGap=" . ( $builderPrice - $runwayPrice) . " Bsiz=$builderSize Rsiz=$runwaySize\n");
          //
          $fh=fopen ( $devName . ".match.csv" , "a" );
          fwrite ( $fh, 
            $devName . "," . $buildName .",". $htype .",". $res .",". $res2 .",". 
            $b_builder .",". $b_plan .",". $b_model .",". 
            $r_builder .",". $r_plan .",". $r_model .",". $r_planCnt .",".
            $builderPrice .",". $runwayPrice .",".
            $builderSize .",".  $runwaySize . "\n");
          fclose($fh);

          if ( $okUpdate && $res2 == "Size-Match" && $res == "Price-diff") {
            // build or update best price array
            $rtn = send_price ( $env , $rawBuilder , $planCpId , $clientId , $builderPrice );
            if ( $debugModeArgv ) print ( "DEBUG POSTED $env , $rawBuilder , $planCpId , $clientId , $builderPrice => [$rtn]\n");
          }
          //
          if ( strpos ( $runwayPlans[$r_k][$r_k2]["rec_status"] , "Price" ) !== false ) {
            // Already has a Price assessment, not good
            $resBits = explode ( "|" , $runwayPlans[$r_k][$r_k2]["rec_status"] );
            if ( isset (  $resBits[2] )) {
              $lastPrice = trim ( $resBits[2]);
            } else {
              $lastPrice = 999;
              print ( "ERROR $devName : Bad rec status [" . $runwayPlans[$r_k][$r_k2]["rec_status"] . "]\n" ); 
            }
            if ( $lastPrice != $runwayPrice ) {
              print ( "ERROR $devName : Duplicate result $res: B[$b_k] R[$r_k][$r_k2] and different prices - last[" . $lastPrice . "] new[" . $runwayPrice . "]\n" );
            }
            $runwayPlans[$r_k][$r_k2]["rec_status"] .= " |AND DUP| " . $res . " | " . $res2 . " | " . $runwayPrice;
            $runwayPlans[$r_k][$r_k2]["match_key"] .= " (AND DUP) " . $b_builder . " - " . $b_model . " - " . $b_plan;
            $matrix[$b_k]["rec_status"] .= " (AND DUP) " . $res . " " . $res2;
            print ( "WARN $devName : Duplicate result $res: B[$b_k] R[$r_k][$r_k2] r_planCnt=$r_planCnt b_planCnt=$b_planCnt " . $runwayPlans[$r_k][$r_k2]["match_key"] . "\n");
          } else {
            $runwayPlans[$r_k][$r_k2]["rec_status"] = $res . " | " . $res2 . " | " . $runwayPrice;
            $runwayPlans[$r_k][$r_k2]["match_key"] = $b_builder . " - " . $b_model . " - " . $b_plan;
            $matrix[$b_k]["rec_status"] = $res . " | " . $res2 . " | " . $runwayPrice;
          }
          //print ( "Via: $r_builder , $b_builder | $r_model , $b_model | $r_plan , $b_plan \n");
        }
      }
    }
  }
  $runPass = true; // we have done one loop through runway data
}  
//print ( "--Builder Summary--\n");
foreach ( $r_builder_list as $k => $v ) print ( "SUMMARY: $devName, $buildName - Runway Builder  [$k] has $v recs\n");
//print ( "..\n");
foreach ( $b_builder_list as $k => $v ) print ( "SUMMARY: $devName, $buildName - Builder Builder [$k] has $v recs\n");
print ( "--Plans--\n");
//foreach ( $r_plan_list as $k => $v ) print   ( "Runway Plan [$k] has $v recs\n");
//print ( "..\n");
//:foreach ( $b_plan_list as $k => $v ) print   ( "Builder Plan [$k] has $v recs\n");
//print ( "--Model Summary--\n");
if ( count( $r_model_good) == 0 && count ( $r_model_unusable ) == 0 ) { print ( "SUMMARY: $devName, $buildName - No Runway models checked as no builder and plans matched\n"); }
else {
  foreach ( $r_model_good as $k => $v ) print   ( "SUMMARY: $devName, $buildName - Runway Model MATCH [$k] has $v recs\n");
  foreach ( $r_model_unusable as $k => $v ) print   ( "SUMMARY: $devName, $buildName - Runway Model POOR [$k] has $v recs\n");
  //print ( "..\n");
  foreach ( $b_model_list as $k => $v ) print   ( "SUMMARY: $devName, $buildName - Builder Model [$k] has $v recs\n");
}

print ( "NOTE Compare complete. Matches are: builder=$hit_b_cnt plans=$hit_p_cnt models=$hit_m_cnt\n");
return (1);
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
