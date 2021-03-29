<?php
// 
//$url = 'https://r6api.runwayproptech.com/runwaywsrest/landapi/landsummary/list?estatecpid=5461250835140678175472718324864485801591535604064';
//$url = 'https://r6api.runwayproptech.com/runwaywsrest/landapi/landsummary/list';
$url = 'https://r6api.runwayproptech.com/runwaywsrest/homeapi/homesummary/list';

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

$arrOutput = json_decode($response, TRUE);
 
//print_r ( $arrOutput );

foreach ( $arrOutput as $k => $v ) {
  //
  print (
  $v['clientproductid'] ."|".
  $v['currentstatusname'] ."|".
  $v['ownername'] ."|".
  $v['designproductname'] ."|".
  $v['rangeproductname'] ."|".
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
// end