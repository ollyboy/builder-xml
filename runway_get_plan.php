<?php


function get_runway_data ( $url , $SEC , $AUTH ) {

  // Initializes a new cURL session
  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // return data
  curl_setopt($curl, CURLOPT_HTTPGET, 1); // use GET
  // Set custom headers for Auth and Content-Type header
  curl_setopt($curl, CURLOPT_FAILONERROR, true);
  curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json', 
    //'securitytoken: 3081684162547503085016672155878528621570596915702',
    'securitytoken: ' . $SEC ,
    //'Authorization: Basic aGlsbHdvb2RyNmFwaTpSNkhpbGx3b29kQXAx'
    'Authorization: ' . $AUTH
  ]);
  // Execute cURL request with all previous settings
  $response = curl_exec($curl);
  if (curl_errno($curl)) {
    $error_msg = curl_error($curl);
    print ( "ERROR $url - $error_msg\n");
  }
  // Close cURL session
  curl_close($curl);
  // convert to array
  if ( strlen ( $response ) == 0 ) {
     print ( "ERROR $url - No response\n"); 
     return ( false );
  }

  return ( json_decode($response, TRUE) );
}

// 
//$url = 'https://r6api.runwayproptech.com/runwaywsrest/landapi/landsummary/list?estatecpid=5461250835140678175472718324864485801591535604064';
//$url = 'https://r6api.runwayproptech.com/runwaywsrest/landapi/landsummary/list';
$urlPart_homeSum = '/homeapi/homesummary/list';

// API to fetch list of Branches:
// https://demo-pipeline.runwayproptech.com/runwaywsrest/crmapi/companysummary/list?clientid=363312264
// $url2= 'https://r6api.runwayproptech.com/runwaywsrest/crmapi/companysummary/list?clientid=';
$urlPart_compSum = '/crmapi/companysummary/list?clientid=';

// read in the source list
//
$clientArgv ="runway.source";  // get developers
$clientSource = get_support_barLin ( $clientArgv ); // get the scope of work, returns empty if not found
if ( sizeof( $clientSource ) == 0 ) {
  print ( "ERROR Can't find essential work scope file: $clientArgv\n" ); // will exit
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
      print ( "ERROR Unknown command line parameter [" . $v . "] \nAllowed: [Developer Name] production development\n" );
      exit (0);
    }
  }
}
if ( count ( $revisedClientSource ) > 0 ) $clientSource = $revisedClientSource;  // shorter list taken from command line

foreach ( $clientSource as $scope ) { //  Developer

  $compSum = array();  $u_ccid = array();

  $parts = array_map ( 'trim' , explode ("|" , $scope ));
  if ( count ( $parts ) < 4 ) {
    print ( "ERROR Bad Developer source record [$scope]\n" );
    exit(0);
  }
  $name = $parts[0];
  $url =  $parts[1]; 
  $SEC =  $parts[2];
  $AUTH = $parts[3];

  $arrOutput = get_runway_data ( $url . $urlPart_homeSum , $SEC , $AUTH ); // home summary
  if ( $arrOutput == false ) {
    print ( "ERROR Bad URL call for Developer record [$scope]\n" );
    $arrOutput=array();
  }
  //print_r ( $arrOutput );

  $fh = fopen ( $name . ".runway.planlist.csv" , "w" );
  foreach ( $arrOutput as $k => $v ) {
  //
  if ( isset ( $v['clientid'] ) && isset ( $v['clientcompanyid'] )) {
     $clientID = $v['clientid'];
     $companyID = $v['clientcompanyid'];
     if ( isset ( $u_ccid[ $companyID ])) { $u_ccid[ $companyID ]++; }
     else { 
       $u_ccid[ $companyID ]=1; 
       print ( "NOTE Found $name new ccid $companyID\n");
     }

  } else {
     $clientID="na";
     $companyID="na";
     print ( "ERROR $name can't find clientid\n" );
  }
  //
  if ( !isset ( $compSum[$clientID]) && $clientID != "na" ) {
    $fullUrl = $url . $urlPart_compSum . $clientID;
    // get a company summary list for this client == developer
    print ( "NOTE Calling $name $fullUrl\n" );
    $res = get_runway_data ( $fullUrl , $SEC , $AUTH );
    if ( is_array( $res)) {
       $compSum[$clientID] = $res;
    } else {
       print ( "ERROR $name $fullUrl no result\n" );
       $compSum[$clientID] = array(); // blank
    }
    foreach ( $res as $k3 => $v3) {
      print ( "DEBUG Found branch/HO " . $v3['clientcompanyid'] . " " . $v3['companyname'] . " " . $v3['companytypename'] . "\n" );
    }
  }

  // find the branch name
  $branch = ""; $hit="weak"; $type = ""; $count=0;
  if ( isset ( $compSum[$clientID] ) ) { 
    foreach ( $compSum[$clientID] as $k2 => $v2 ) {
      //
      if  ( $companyID == $v2['clientcompanyid'] ) {
        $count++;
        $type = $v2['companytypename'];
        if ( $v2['companytypename'] == "Branch" ) { $hit="strong"; }
        // messy data
        if ( $branch == "" && $hit == "weak" ) $branch = trim ( $v2['companyname'] );
        if ( $hit == "strong" ) $branch = trim ( $v2['companyname'] );
      }
    }
  }

  print ( "NOTE $name rec: " . $v['ownername'] . " | " . $v['designproductname'] . " | " . 
               $v['rangeproductname'] . " | " . $v['currentstatusname'] . " >> " . $branch . " | ". $hit . " | " . $type . " | " . $count . "\n" );
  if ( $branch == "") print ( "ERROR $name Cant find branch for $clientID $companyID\n");


  fprintf( $fh, 
  $v['clientproductid'] ."|".
  $v['currentstatusname'] ."|". // Draft, Avaiable
  $v['ownername'] ."|". // DR Horton, American Legion Homes
  $v['designproductname'] ."|". // plan number or name
  $v['rangeproductname'] . " " . $branch ."|". // Builder, community add the branch, may duplicate
  $v['productDepthFormatted'] ."|". // => 58' 1.0"
  $v['productSizeFormatted'] ."|".  //=> 2853.0
  $v['productprice'] ."|".   // => 279990
  $v['canfitonwidthFormatted'] ."|".
  $v['noofbedrooms']  ."|".
  $v['noofbathrooms']  ."|".
  $v['noofcarparks']  ."|".
  $v['noofstoreys']  ."|".
  $v['productnumber'] ."|".
  $v['productname'] . "\n" );

  }
  fclose ( $fh );
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