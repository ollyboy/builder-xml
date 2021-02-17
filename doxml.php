<?php

// XML extract, map & generatee results csv's and logs

const MAXRECS = 500000; // max lines to process
$sendConsole = false; // log to console if true
$getURLs = true; // make an new call for XML, false will process the existing xml if it exists

libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', 1); // Do send to output
ini_set('log_errors', 1 ); // send errors to log

$val=array(); // will be used globally for speed
$key=array();
$newKey=array();
$progressiveKeySub=array();  // list of progress
$currentKeySub=array();
$uniqueFoundKey=array();
$key_map=array();
$keyTrigger=array();


static $xml_source = array (
"David | https://www.davidweekleyhomes.com/feeds/sandbrockranch/sandbrockranch.xml",
"Perry | https://assets.perryhomes.com/_perrydatafeed/feed.xml",  
"Highland | http://admin.hhomesltd.com/xmlFeed/CommunityXml/290"
);

/*
static $perry_key_map = array (  // for level, find value, replace

"2,4,5,6,7,8,9,10,11,12|Corporation|2|CorporateBuilderNumber",
"6,7,8,9,10,11,12|Subdivision|6|SubdivisionNumber",
"4,5,6,7,8,9,10,11,12|Builder|4|BuilderNumber",
"8,9,10,11,12|Plan|8|PlanNumber"

);

static $david_key_map = array (  // for level, find value, replace

"2,4,5,6,7,8,9,10,11,12|Corporation|2|CorporateBuilderNumber",
"6,7,8,9,10,11,12|Subdivision|6|SubdivisionNumber",
"4,5,6,7,8,9,10,11,12|Builder|4|BuilderNumber",
"8,9,10,11,12|Plan|8|PlanNumber"

);
static $highland_key_map = array (  // for level, find value, replace

"2,4,5,6,7,8,9,10,11,12|Corporation|2|CorporateBuilderNumber",
"5,6,7,8,9,10,11,12|Subdivision|5|SubdivisionNumber",
"3,4,5,6,7,8,9,10,11,12|Builder|3|BuilderNumber",
"7,8,9,10,11,12|Plan|7|PlanNumber",
"8,9,10,11,12|Spec|8|SpecNumber,SpecMLSNumber"

);
*/

// Mainline, read scope,  loop the Corps gettng XML 
//
$tmpXmlsource = get_support_barLin ( "xml.client.source" ); // get the scope of work
if ( sizeof( $tmpXmlsource ) > 0 ) {
   $xml_source = $tmpXmlsource;  // done this way as we may want hard code above as backup
}

foreach ( $xml_source as $scope ) { // Loop - Perry, Highland , David etc 
  
  // control/limit output files
  //
  $firstRun = false;  // New XML source, assume false
  $strangeResult = false; // Stange combo of counters origonal identical deleted different
  $ProductionMode = false; // Just generate hints if false
  $jobAbandon = false;
  
  // whats todo for this job
  //
  $parts = array_map ( 'trim' , explode ("|" , $scope ));
  $name = $parts[0];
  $URL = $parts[1]; 
  if ( $name == "" || $URL == "" ) { $name="invalid"; $URL = "Not given"; $jobAbandon = true; }

  // open files, global file handles will be used
  //
  $errtmp = $name . ".error.log";
  if ( file_exists($errtmp)) unlink ( $errtmp );
  $errlog = fopen ( $errtmp , "w" );

  $protmp = $name . ".progress.log";
  if ( file_exists($protmp)) unlink ( $protmp );
  $prolog = fopen ( $protmp , "w" );

  $csvtmp = $name . ".latest.csv";
  if ( file_exists( $csvtmp ) && filesize( $csvtmp ) > 0 ) rename ( $csvtmp , $name . ".previous.csv" ); 
  if ( file_exists( $csvtmp )) unlink ( $csvtmp ); // should not need
  $csvlog = fopen ( $csvtmp , "w" );

  // from here we can use log files, get the scope of work
  //
  if ( sizeof ( $tmpXmlsource ) > 0 ) do_note ( "Got xml.client.source from file");
  else do_error ( "Using internal XML source list");

  $todoWork = array(); // for Send processes to read and action
  $key_map = array(); // XMLs have different levels and content, need a map
 
  /*
  if     ( $name ) == "Perry" )  { $key_map = $perry_key_map; 
  elseif ( $name == "Highland" ) { $key_map = $highland_key_map;
  elseif ( $name == "David" )    { $key_map = $perry_key_map;
  else do_error ( "No key map for " . $name );
  */
  $mapName = $name . ".key.map"; // ie Perry.key.map
  $tmp_key_map = array();
  $tmp_key_map = get_support_barLin ( $mapName );
  //if ( sizeof($tmp_key_map) == 0 ) $tmp_key_map = get_support_barLin ( strtolower($name) . ".key.map" );
  //if ( sizeof($tmp_key_map) == 0 ) $tmp_key_map = get_support_barLin ( ucwords ( strtolower($name) ) . ".key.map" );
  if ( sizeof($tmp_key_map) == 0 ) {
    do_error ( "Can't find " . $mapName . " Check name has correct case");
  } else {
    do_note ( "Found " . $mapName );
    $key_map = $tmp_key_map ;
    if ( identical_exist ( $mapName , $mapName . ".bak" )) {
      do_note ( "Previous map same which is good " . $mapName );
    } else {
      do_note ( "Map has changed! " . $mapName );
      $firstRun = true; 
      if ( !copy( $mapName, $mapName . ".bak") ) {
        do_error ( "Failed to backup " . $mapName );
      }
    }
  }

  do_note ( "Processing -- " . $name . " -- " . $URL);

  if ( $getURLs ) {
    //
    // Call the website, careful with redirects and
    $xmlstr = get_xml_from_url ( $URL );
    if ( is_string( $xmlstr ) && strlen ( $xmlstr ) > 0 ) {
      if ( file_exists($name . ".xml")) {
        rename ( $name . ".xml" , $name . ".xml" . ".old" ); // ie Perry.xml.old
      } else {
        do_note ( "First run for $name");
        $firstRun = true; 
      }
      if ( file_put_contents( $name . ".xml", $xmlstr ) === false ){
        do_error ( "Could not write xml file for " . $URL ); 
        $jobAbandon = true;
      }
    } else {
      do_error ( "Could not read from" . $URL ); 
      $jobAbandon = true;
    }
  }

  // Check the XML is ok
  //
  $objXmlDocument = simplexml_load_file( $name . ".xml"); 
  if ($objXmlDocument === false ) {
    do_error ( "Parsing XML file " . $name ) ;
    $jobAbandon = true;
    foreach(libxml_get_errors() as $error) {
        do_error ( "XML error: " . $error->message );
    }
  }
  // Convert XML to JSON and then into an array
  //
  $objJsonDocument = json_encode($objXmlDocument);
  if ( $objJsonDocument == false ) do_error ( "JSON encode failed" );
  $arrOutput = json_decode($objJsonDocument, TRUE);
  if ( $arrOutput == false ) do_error ( "JSON decode failed" );

  // Iterate through the JSON converted array, collect useful key:value pairs
  // Apply the pairs to build keys for lower layers
  //
  if ( is_array( $arrOutput ) && sizeof ( $arrOutput ) > 0 ) {

    build_key_trigger (); // build necessary arrays from map
    $depth = array_depth ( $arrOutput );
    do_note ( "XML " . $name . " has depth " . $depth );
    deep_loop ( $arrOutput ); // Hard work here
    //print_r ( $uniqueFoundKey );
    fixedcsv_from_array ( $name . ".hints.csv" , $uniqueFoundKey );
    //cache_set($name . ".progress.array", $uniqueFoundKey ); // read this to generate maps
    //print_r ( $progressiveKeySub );
    //print_r ( $currentKeySub );
  } else {
    do_error ( "XML>array " . $name . " failed" );
    $jobAbandon = true;
  }

  close_work_files ();

  // Do net change here
  if ( $jobAbandon == false ) add_change_csv ( $name, $name . ".latest.csv" , $name . ".previous.csv" );

  close_log_files ();

  if ( filesize( $errtmp ) == 0 ) unlink ( $errtmp ); // remove if not error logged

}

// --- end of mainline ---


function get_support_barLin ( $name ) {

  $out=array();
  // get things like Perry.key.map , xml.client.source
  if ( !file_exists( $name )) { do_note ( "Cant find support file " . $name ); return ( $out ); } // blank
  $out = explode( "\n", file_get_contents( $name ));
  foreach ( $out as $k => $v ) if ( strlen ( $v ) < 2 ) unset ($out[$k]); // get rid of junk
  return ( $out );
}

function fixedcsv_from_array ( $name , $array ) {

  $fh = fopen( $name , 'w' );
  if ( !$fh ) { do_error ( "fixed csv, cant open" . $name ); return (0); }
  foreach ( $array as $k => $v ) {
	  if (  trim($k) != "" && trim($v) != "" ) fputcsv ( $fh , make_fixed ( $k , $v )); 
  }
  fclose ( $fh );
}

function add_change_csv ( $name, $new , $old ) {  // assume the last column is data and files are same fixed width

  if ( !file_exists( $new ) ) { do_error ( "Does not exist " . $new ); return(0); }
  if ( !file_exists( $old ) ) { do_note ( "Does not exist " . $old ); return(0); }
  if ( filesize ( $new ) < 2 ) { do_error ( "No data in " . $new ); return(0); }
  if ( filesize ( $old ) < 2 ) { do_note ( "No data in " . $old ); return(0); }
  //
  $nf = fopen( $new , 'r' );
  if ( !$nf ) { do_error ( "cant open" . $new ); return (0); }
  $of = fopen( $old , 'r');
  if ( !$of ) { do_error ( "cant open" . $old ); fclose ( $nf) ; return(0); }

  $nc_file = $name . "." . time() . ".new.csv";
  $sc_file = $name . ".same.csv";
  $dc_file = $name . "." . time() . ".deleted.csv";
  $xc_file = $name . "." . time() . ".changed.csv";

  $nc = fopen( $nc_file , 'w');
  if ( !$nc ) { do_error ( "cant open new csv"); }
  $sc = fopen( $sc_file , 'w'); // note no time stamp
  if ( !$sc ) { do_error ( "cant open same csv"); }
  $dc = fopen( $dc_file , 'w');
  if ( !$dc ) { do_error ( "cant open delete csv"); }
  $xc = fopen( $xc_file , 'w');
  if ( !$xc ) { do_error ( "cant open change csv"); }

  $new_store = array(); $old_store = array ();
  $origonal = 0 ; $identical = 0 ; $deleted = 0; $different=0;
  //
  while (($data = fgetcsv($nf)) !== FALSE ) {
    if ( is_array( $data ) && sizeof ( $data ) > 1 ) {
      $last = array_pop($data); // get the value
      $tmp = trim ( implode( "^", $data ));
      $new_store[$tmp] = $last; // use the rest as a key
    }
  }
  while (($data = fgetcsv($of)) !== FALSE ) {
    if ( is_array( $data )  && sizeof ( $data ) > 1 ) {
      $last = array_pop($data); // get the value
      $tmp = trim ( implode( "^", $data ));
      $old_store[$tmp] = $last; // use the rest as a key
    }
  }
  // ok now do tests
  //
  foreach ( $new_store as $k => $v ) {
    if ( isset ( $old_store[$k] )) {  // have same key
      if ( $old_store[$k] == $v ) {
        $identical++;
        if ( $sc !== false ) fputcsv ( $sc , make_fixed ( $k , $v ));  // the same
      } else {
        if ( strpos ( $k , "DateGenerated" ) === false ) {
          $different++;
          do_note ( "DIFF [" . $k . "] New=" . $v . " Old=" . $old_store[$k] );
          if ( $xc !== false ) fputcsv ( $xc , make_fixed ( $k , $v ));  // the new different
        } else {
          do_note ( "Bypass Diff test [" . $k . "] New=" . $v . " Old=" . $old_store[$k] );
        }
      }
      unset ( $old_store[$k] ); // get rid of it 
    } else {
      // new key not seen
      $origonal++;
      do_note ( "NEW [" . $k . "] Val=" . $v );
      if ( $nc !== false ) fputcsv ( $nc , make_fixed ( $k , $v ));  // not seen before
    }
  }
  foreach ( $old_store as $k => $v ) {
    $deleted++;
    do_note ( "GONE [" . $k . "] Val=" . $v );
    if ( $dc !== false ) fputcsv ( $dc , make_fixed ( $k , $v ));  // still in old but not in new
    }
  //
  do_note ( "Compare " . $new . " to " . $old . " New=" . $origonal . " Same=" . $identical . " Deleted=" . $deleted . " Diff=" . $different );

  if ( $nc !== false ) { fclose ( $nc ); }
  if ( $sc !== false ) { fclose ( $sc ); }
  if ( $dc !== false ) { fclose ( $dc ); }
  if ( $xc !== false ) { fclose ( $xc ); }

  if ( file_exists( $nc_file ) && filesize( $nc_file ) == 0 ) unlink ( $nc_file );
  if ( file_exists( $sc_file ) && filesize( $sc_file ) == 0 ) unlink ( $sc_file );
  if ( file_exists( $dc_file ) && filesize( $dc_file ) == 0 ) unlink ( $dc_file );
  if ( file_exists( $xc_file ) && filesize( $xc_file ) == 0 ) unlink ( $xc_file );

  return (1);
}


function close_work_files () {

  global $csvlog;

  if ( isset ( $csvlog ) && $csvlog !== false ) fclose ( $csvlog ); 

}


function close_log_files () {

  global $errlog, $prolog;

  if ( isset ( $errlog ) && $errlog !== false ) fclose ( $errlog ); 
  if ( isset ( $prolog ) && $prolog !== false ) fclose ( $prolog ); 

}

function do_error ( $txt ) {

  global $errlog, $sendConsole;

  if ( $sendConsole ) print ( "ERROR: " . $txt . "\n");
  fwrite ( $errlog , "ERROR: " . $txt . "\n" );
}

function do_fatal ( $txt ) {

  global $errlog, $sendConsole;

  if ( $sendConsole ) print ( "FATAL: " . $txt . "\n");
  fwrite ( $errlog , "FATAL: " . $txt . "\n" );
  close_work_files ();
  close_log_files ();
  exit(0);
}

function do_note ( $txt ) {

  global $prolog, $sendConsole;

  if ( $sendConsole ) print ( "NOTE: " . $txt . "\n");
  fwrite ( $prolog , $txt . "\n" );
}

function build_key_trigger (){

  global $key_map, $keyTrigger;

  foreach ( $key_map as $k => $v ) { 
    $parts = array_map('trim', explode ( "|" , $v));
    if ( sizeof( $parts ) > 2 ) {
      if ( isset ( $keyTrigger [ $parts[2] ])) $keyTrigger [ $parts[2] ] .= " , " . $parts[3];
      else $keyTrigger [ $parts[2] ] = $parts[3];  
    }
  }
  foreach ( $keyTrigger as $k => $v) do_note ( "Trigger target [" . $v . "] from level [" . $k . "]" );
}


function get_prefered_key ( $name , $level ) {

  global $key_map, $currentKeySub;

  $combo = "";
  foreach ( $key_map as $k => $v ) { 
    $parts = array_map('trim', explode ( "|" , $v));
    if ( sizeof( $parts ) > 2 ) {
      $forLev = array_map ( 'trim', explode ( "," , $parts[0] )); // which levels
      $sources = array_map ( 'trim', explode ( "," , $parts[3] )); // which source triggers
      foreach ( $sources as $k1 => $v1 ) { 
        foreach ( $forLev as $k2 => $v2 ) { // for each level
          if ( count ( $parts ) > 3 && $v2 == $level && strval($name) == $parts[1] ) { // 
            if ( isset ( $currentKeySub[ $v1 ] )) {
              //print ( "Natural key for $name $level set to " . $currentKeySub[$parts[3]] . "\n");
              $combo .= $currentKeySub[$v1] . "~"; // get latest value
            }
          }
        }
      }
    }
  }
  if ( $combo == "" ) return ( $name ); // not found
  return ( substr( $combo, 0, -1) );
}

function get_xml_from_url($url){
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
    $xmlstr = curl_exec($ch);
    if ( is_string( $xmlstr )) {
      if ( strlen ( trim ( $xmlstr )) == 0 ) do_error ( "Curl response: [" . curl_error($ch) . "] Zero size retun results" );
      else do_note ( "Curl URL response size was " . strlen ( $xmlstr ) );
    } else {
      do_error ( "Curl response: [" . curl_error($ch) . "]" ); $xmlstr = "";
    }
    curl_close($ch);
    return $xmlstr;
}

function array_depth(array $array) {
    
    $max_depth = 1;
    foreach ($array as $v) {
        if (is_array($v)) {
            $depth = array_depth($v) + 1;
            if ($depth > $max_depth) {
                $max_depth = $depth;
            }
        }
    }
    return $max_depth;
}


function record_natural_key ( $level ) {

  global $val, $key, $newKey, $keyTrigger, $progressiveKeySub, $currentKeySub;

  // keep a list, we may use for table adjust
  if ( !isset ($progressiveKeySub[$key[$level]])) $progressiveKeySub[$key[$level]] = $newKey[$level] . " | " . $level; 
  else $progressiveKeySub[$key[$level]] .= " , " . $newKey[$level] . " | " . $level;

  $currentKeySub[$key[$level]] = $newKey[$level]; // latest only

}

function do_lev ( $level ) { // we are at level in XML array weher there are key:value pairs

  global $val, $key, $newKey, $keyTrigger, $progressiveKeySub, $currentKeySub, $uniqueFoundKey, $csvlog;

  $saveKey=""; $saveKeyNew ="";
  static $count = 0;

  $count++;
  if ( isset ( $keyTrigger[$level])) {
    //print ( "Dolevel lev=$level trig=" . $keyTrigger[$level] . " Key=" . $key[$level] . "\n");
    if ( strpos ( $keyTrigger[$level] , $key[$level] ) !== false ) {
      do_note ( "-- At " . $level . " hit NewKey [" . $key[$level] . "] settng val as [" . $val[$level] . "]" );
      $newKey[$level] = $val[$level];
      record_natural_key ( $level );
    } else {
      if ( !isset ($newKey[$level] )) {
        $newKey[$level] = $key[$level];
        record_natural_key ( $level );
      }
    }
  }
  // Build up the key
  for ( $i=1; $i<= $level; $i++ ) {
    $saveKey .= $key[$i] . "^"; 
    $saveKeyNew .= get_prefered_key ( $key[$i] , $level ). "^";
  }
  $saveKey = substr($saveKey , 0, -1); // get rid of excess delimiter
  $saveKeyNew = substr($saveKeyNew , 0, -1);

  // write the csv in a fixed column format
  //
  if ( strpos ( $saveKeyNew , "@attributes") === false ) { // cant use these as they come before key trigger
    fputcsv ( $csvlog , make_fixed ( $saveKeyNew , $val[$level] ));
  }

  if ( strpos ( $val[$level] , ".png") !== false || strpos ( $val[$level] , ".jpg") !== false ) {
    // found image asset
  } else {
    //
    if (strlen ( $val[$level] ) > 40 ) { $tmp = substr( $val[$level], 0, 40) . "..more.."; }
    else { $tmp = $val[$level]; }
    //
    do_note ( "[" . $level . "] " . $saveKey . " | " . $saveKeyNew . " -> " . $tmp );

    $tmpKey="Lev-" . $level . "^";
    $keyBits = explode ( "^" , $saveKey );
    foreach ( $keyBits as $k => $v) {
      if ( is_numeric($v) ) $tmpKey .= "##^";  //get rid of numbers just for reporting
      else $tmpKey .= $v . "^";
    }
    $tmpKey = substr($tmpKey, 0, -1); // strip last char
    if ( isset ( $uniqueFoundKey[$tmpKey] )) { 
      if ( strpos ( $uniqueFoundKey[$tmpKey] , $tmp ) === false && strlen ( $uniqueFoundKey[$tmpKey] ) < 60 ) { // dont repeat, not too many
        $uniqueFoundKey[$tmpKey] .= " , " . $tmp ;
      }
    } else { 
      $uniqueFoundKey[$tmpKey] = $tmp;
    }
  }

  if ( $count > MAXRECS ) {
    //print_r ( $uniqueFoundKey );
    //print_r ( $progressiveKeySub );
    //print_r ( $currentKeySub );
    do_fatal ( "Hit max records at " . $count );
  }
}

function make_fixed ( $keyString , $fact ) {

  // key in first 14 columns, variable in 15, value in 16
  $fixedFormat = array(); 
  $tmp = explode ( "^" , $keyString );
  $tmpsize = sizeof ($tmp);
  for ( $i=0; $i<15; $i++ ) {
    if ( $i < $tmpsize - 1 ) {
      $fixedFormat[$i] = $tmp[$i];
    } else {
      $fixedFormat[$i] = "";
    }
  }
  $fixedFormat[15] = $tmp[$tmpsize-1];
  $fixedFormat[16] = $fact;
  return ( $fixedFormat );
}

function go_deeper ( $level ) {

  global $val, $key, $newKey, $progressiveKeySub;

  if ( !isset ($newKey[$level] )) {
    do_note ( "-- No Key " . $level . " Set NewKey [" . $key[$level] . "]");
    $newKey[$level] = $key[$level];
    record_natural_key ( $level );
  }

  if ( is_array ( $val[ $level ] )) return ( true );
  return ( false );
}


function deep_loop ( $arrOutput ) {

global $val, $key;

$alt2="";
foreach ( $arrOutput as $key[1] => $val[1] ) {
 if ( go_deeper ( 1 )) {
  foreach ( $val[1] as $key[2] => $val[2] ) { 
   if ( go_deeper ( 2 )) {
    foreach ( $val[2] as $key[3] => $val[3] ) {
     if ( go_deeper ( 3 )) {
      foreach ( $val[3] as $key[4] => $val[4] ) {
       if ( go_deeper ( 4 )) {
        foreach ( $val[4] as $key[5] => $val[5] ) {
         if ( go_deeper ( 5 )) {
          foreach ( $val[5] as $key[6] => $val[6] ) {
           if ( go_deeper ( 6 )) {
            foreach ( $val[6] as $key[7] => $val[7] ) {
             if ( go_deeper ( 7 )) {
              foreach ( $val[7] as $key[8] => $val[8] ) {
               if ( go_deeper ( 8 )) {
                foreach ( $val[8] as $key[9] => $val[9] ) {
                 if ( go_deeper ( 9 )) {
                  foreach ( $val[9] as $key[10] => $val[10] ) {
                   if ( go_deeper ( 10 )) {
                    foreach ( $val[10] as $key[11] => $val[11] ) {
                     if ( go_deeper ( 11 )) {
                      foreach ( $val[11] as $key[12] => $val[12] ) {
                       if ( go_deeper ( 12 )) {
                        foreach ( $val[12] as $key[13] => $val[13] ) {
                         if ( go_deeper ( 13 )) {
                          do_error ( "XML structure is too deep" );
                         } else { do_lev ( 13 ); }
                        }
                       } else { do_lev ( 12 ); }
                      } 
                     } else { do_lev ( 11 ); }
                    }
                   } else { do_lev ( 10 ); }
                  } 
                 } else { do_lev ( 9 ); }
                }
               } else { do_lev ( 8 ); }
              }
             } else { do_lev ( 7 ); }
            }
           } else { do_lev ( 6 ); }
          }
         } else { do_lev ( 5 ); }
        }
       } else { do_lev ( 4 ); }
      }
     } else { do_lev ( 3 ); }
    }
   } else { do_lev ( 2 ); }
  }
 } else { do_lev ( 1 ); }
}
//
} // end function


function cache_set( $key, $val ) {  // Super fast Object store using PHPs file cache. 
   //
   $val = var_export($val, true);
   $val = str_replace('stdClass::__set_state', '(object)', $val);
   // Write to temp file first to ensure atomicity
   $tmp = "/tmp/$key." . uniqid('', true) . '.tmp';
   file_put_contents($tmp, '<?php $val = ' . $val . ';', LOCK_EX);
   rename($tmp, $key );
}
//
function cache_get( $key ) {
   
   @include "$key";
   return isset($val) ? $val : false;
}

function identical_exist ( $fileOne, $fileTwo ) { // faster than md5 as will return as soon as diff is found

    if (!file_exists($fileOne)) return false;
    if (!file_exists($fileTwo)) return false;
    if (filetype($fileOne) !== filetype($fileTwo)) return false;
    if (filesize($fileOne) !== filesize($fileTwo)) return false;
 
    if (! $fp1 = fopen($fileOne, 'rb')) return false;
    if (! $fp2 = fopen($fileTwo, 'rb')) { fclose($fp1); return false; }
 
    $same = true;
    while (! feof($fp1) and ! feof($fp2)) {
      if (fread($fp1, 4096) !== fread($fp2, 4096)) {
          $same = false;
          break;
      }
    }
    if (feof($fp1) !== feof($fp2)) $same = false; 
    fclose($fp1);
    fclose($fp2);
    return $same;
}

?>