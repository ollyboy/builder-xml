<?php
// 
$url = 'https://r6api.runwayproptech.com/runwaywsrest/landapi/estatesummary/list';
//$SEC = '3081684162547503085016672155878528621570596915702';
//$AUTH = 'Basic aGlsbHdvb2RyNmFwaTpSNkhpbGx3b29kQXAx';

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

  $parts = array_map ( 'trim' , explode ("|" , $scope ));
  if ( count ( $parts ) < 4 ) {
    print ( "ERROR Bad Developer source record [$scope]\n" );
    exit(0);
  }
  $name = $parts[0];
  //$url =  $parts[1]; // dont use this
  $SEC =  $parts[2];
  $AUTH = $parts[3];

  $arrOutput = get_runway_data ( $url , $SEC , $AUTH ); // home summary
  if ( $arrOutput == false ) {
    print ( "ERROR Bad URL call for Developer record [$scope]\n" );
    $arrOutput=array();
  }

  //print_r ( $arrOutput );

  foreach ( $arrOutput as $k => $v ) {
    //
    $status = $v['active'];
    if ( $status == "1" ) {
      print ( $v['cpidstring'] ."|". 
		$v['ownername'] ."|".
		$v['productname'] ."|". 
        $v['street1']  ."|". // => Celina
        $v['street2']  ."|". // =>
        $v['addressstate']  ."|". //  => Texas
        $v['suburb']  ."|". // => Celina
        $v['postcode']  . "\n" // => 75009 
      );
    }
  }
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