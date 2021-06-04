<?php
// 
//$url = 'https://r6api.runwayproptech.com/runwaywsrest/landapi/landsummary/list?estatecpid=5461250835140678175472718324864485801591535604064';
$url = 'https://r6api.runwayproptech.com/runwaywsrest/landapi/landsummary/list';
//$url = 'https://r6api.runwayproptech.com/runwaywsrest/landapi/estatesummary/list';

ini_set('memory_limit', '512M');

// Initializes a new cURL session
$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // return data
curl_setopt($curl, CURLOPT_HTTPGET, 1); // use GET
// Set custom headers for Auth and Content-Type header
curl_setopt($curl, CURLOPT_FAILONERROR, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'securitytoken: 3081684162547503085016672155878528621570596915702',
  'Authorization: Basic aGlsbHdvb2RyNmFwaTpSNkhpbGx3b29kQXAx'
]);
// Execute cURL request with all previous settings
$response = curl_exec($curl);
if (curl_errno($curl)) {
    $error_msg = curl_error($curl);
    print ( "ERROR - $error_msg\n");
}
// Close cURL session
curl_close($curl);
// convert to array
if ( strlen ( $response ) == 0 ) {
   print ( "ERROR - No reponse\n");	
   exit;
}

//print ( "DEBUG - Strlen=" .  strlen( $response )  ._ "\n" );

$arrOutput = json_decode($response, TRUE);
 
//print_r ( $arrOutput );

foreach ( $arrOutput as $k => $v ) {
  //
  $status = $v['currentstatusname'];
  if ( $status == "Sold" || $status == "Closed") {
     print ( $v['currentstatusname'] ."|". 
		$v['estateproductname'] ."|".
		$v['productname'] ."|".  // SS-21
        $v['productnumber'] ."|". // 21
        $v['stageproductname'] ."|". //Phase 1
        $v['address']['unitnumber'] ."|".
        $v['address']['street1'] ."|". // 814 Lawndale Street
        $v['address']['suburb'] ."|".  // Celina
        $v['address']['city'] ."|". // Celina
        $v['address']['state'] ."|". //Texas
        $v['address']['district'] ."|".
        $v['address']['postcode'] ."|". //75009
        $v['specHome'] ."|". // false
        $v['allocatedBuilderName'] . "\n" );
  }
}
// end