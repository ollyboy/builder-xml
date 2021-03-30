<?php

// XML JSON Zip extract, map & generate results csv's and logs

const MAXRECS = 50000000; // max lines to process
//const MAXRECS = 50000; // max lines to process
ini_set("auto_detect_line_endings", true);

libxml_use_internal_errors(TRUE);
error_reporting(E_ALL);
ini_set('display_errors', 1); // Do send to output
ini_set('log_errors', 1 ); // send errors to log

/* 

example Builder records from Runway

Perry Homes
Highland Homes
Coventry Homes
David Weekley Homes
Trendmaker
Toll Brothers

Project - Phase - Lot

Project - Phase - Lot Design - Plan Elevation P2P Address

Pomona - Phase 4 - 12-01-23 2213 Forest Trace Ln Manvel Texas 77578
Pomona - Phase 2A - 6-1-30  2329 Olive Forest Lane Manvel Texas 77578
Pomona - Phase 1 - 2-2-22   2811 Maple Oak Ln Manvel Texas 77578
Pomona - Phase 1 - 2-2-34   4319 Cottonwood Creek Ln Manvel Texas 77578
Pomona - Phase 2A - 5-1-17  2308 Ridgewood Manor Court Manvel Texas 77578
Pomona - Phase 4 - 11-1-13  2118 Plum Creek Dr Manvel Texas 77578
Pomona - Phase 1 - 2-1-13   4314 Cottonwood Creek Ln Manvel Texas 77578
Pomona - Phase 1 - 2-1-27   4423 Willow Crest Ln Manvel Texas 77578
Pomona - Phase 1 - 2-1-9    4330 Cottonwood Creek Ln Manvel Texas 77578
Pomona - Phase 1 - 2-2-10   2703 Maple Oak Ln Manvel Texas 77578
Pomona - Phase 1 - 2-2-54   4555 Juniper Ridge Ln Manvel Texas 77578
Pomona - Phase 2A - 5-1-13  2324 Ridgewood Manor Court Manvel Texas 77578
Pomona - Phase 2A - 5-1-23  2313 Ridgewood Manor Court Manvel Texas 77578
Pomona - Phase 2A - 5-1-9   4509 Hawthorne Crescent Lane Manvel Texas 77578
Pomona - Phase 2B - 8-1-10  2303 Olive Heights Manvel Texas 77578
Pomona - Phase 2B - 8-1-26  24726 Birch Knoll Trail Manvel Texas 77578

*/

// Unzip error codes
//
$unzip_errors = array(
  0 => 'all good',
  1 => 'eror but processing completed successfully anyway. Maybe unsupported compression method or unknown password.',
  2 => 'some broken zipfiles created by other archivers have simple work-arounds.',
  3 => 'a severe error in the zipfile format was detected. Processing probably failed immediately.',
  4 => 'unzip was unable to allocate memory for one or more buffers during program initialization.',
  5 => 'unzip was unable to allocate memory or unable to obtain a tty to read the decryption password(s).',
  6 => 'unzip was unable to allocate memory during decompression to disk.',
  7 => 'unzip was unable to allocate memory during in-memory decompression.',
  8 => '[currently not used]',
  9 => 'the specified zipfiles were not found.',
  10 => 'invalid options were specified on the command line.',
  11 => 'no matching files were found.',
  50 => 'the disk is (or was) full during extraction.',
  51 => 'the end of the ZIP archive was encountered prematurely.',
  80 => 'the user aborted unzip prematurely with control-C (or similar)',
  81 => 'testing or extraction of one or more files failed due to unsupported compression methods or unsupported decryption.',
  82 => 'no files were found due to bad decryption password(s).' 
);

/* EXAMPLE
static $clientSource = array (

"David | https://www.davidweekleyhomes.com/feeds/sandbrockranch/sandbrockranch.xml|XML",
"Perry | https://assets.perryhomes.com/_perrydatafeed/feed.xml|XML",  
"Highland | http://admin.hhomesltd.com/xmlFeed/CommunityXml/290|XML"

);
*/

/* EXAMPLE
static $highland_key_map = array (  // for level, find value, replace

"2,4,5,6,7,8,9,10,11,12|Corporation|2|CorporateBuilderNumber",
"5,6,7,8,9,10,11,12|Subdivision|5|SubdivisionNumber",
"3,4,5,6,7,8,9,10,11,12|Builder|3|BuilderNumber",
"7,8,9,10,11,12|Plan|7|PlanNumber",
"8,9,10,11,12|Spec|8|SpecNumber,SpecMLSNumber"

);
*/

/*
unit | situs_unit
house | situs_num
prefix | situs_street_prefx
street | situs_street
suffix | situs_street_suffix
city | situs_city
zip | situs_zip
state | 
owner | py_owner_name , appr_owner_name , legal_desc
acreage_val | legal_acreage, land_acres
land_val | land_hstd_val, land_non_hstd_val
improved_val | imprv_hstd_val, imprv_non_hstd_val
use_val | ag_use_val
market_val | ag_market
appraised_val | appraised_val
assessed_val | assessed_val
*/


// Mainline, read scope, loop the builders or counties getting XML/JSON 
//
$errlog = false;
$prodModeArgv = true; // Just generate hints if false
$sendConsArgv = false; // log to console if true
$getURLsArgv  = true; // make an new call for XML, false will process the existing xml if it exists
$excImageArgv = false; // don't include images in hints
$buildLogArgv = false; // maybe don't generate build log
$skipDiffArgv =false;
$skipHintArgv = false;
$keyMapSame =false; // internal check 

// read in the source list
//
$clientArgv ="builder.source";  // get data for builders unless instructed otherwise
foreach( $argv as $cnt => $v ) {
  if ( trim ( strtolower( $v )) == "county" ) $clientArgv = "county.source";
}
$clientSource = get_support_barLin ( $clientArgv ); // get the scope of work, returns empty if not found
if ( sizeof( $clientSource ) == 0 ) {
  do_fatal ( "Can't find essential work scope file: " .  $clientArgv ); // will exit
} 

// set flags and see if run is limited to a small set of jobs
//
$revisedClientSource=array();
foreach( $argv as $cnt => $v ) {
  $value =trim ( strtolower( $v )); 
  if ( $cnt == 0 ) {} // ignore
  elseif ( $value  == "production") $prodModeArgv = true; // Just generate hints if false
  elseif ( $value  == "development") $prodModeArgv = false; 
  elseif ( $value  == "console") $sendConsArgv = true;
  elseif ( $value  == "noimage") $excImageArgv = true;
  elseif ( $value  == "skipurl") $getURLsArgv = false;
  elseif ( $value  == "buildlog") $buildLogArgv = true;
  elseif ( $value  == "skipdiff") $skipDiffArgv =true;
  elseif ( $value  == "skiphints") $skipHintArgv =true;
  elseif ( $value  == "county") {} // already read in but dont want to error
  else {
    $hit = false;
    foreach ( $clientSource as $scope ) {
      $parts = array_map ( 'trim' , explode ("|" , $scope ));
      if ( strtolower ( $parts[0] ) == $value ) { $revisedClientSource[] = $scope; $hit = true; }// names match
    }
    if ( ! $hit ) {
      print ( "Unknown command line parameter [" . $v . "] \nAllowed: [Name] county production development console noimage skipurl buildlog skipdiff skiphints\n" );
      exit (0);
    }
  }
}

if ( count ( $revisedClientSource ) > 0 ) $clientSource = $revisedClientSource;  // shorter list taken from command line

$clientSourceOld = get_support_barLin ( $clientArgv . ".bak" );
$hit = array(); $sorceScopeUnchanged=false;
foreach ( $clientSource as $k => $v ) {
  foreach ( $clientSourceOld as $k2 => $v2 ) {
    if ( $v == $v2 ) $hit[$k] = true;
  }
}
if ( sizeof ( $revisedClientSource ) == sizeof ( $hit )) {
  $sorceScopeUnchanged=true;
} else {
  $sorceScopeUnchanged=false;
}

if ( identical_exist ( $clientArgv , $clientArgv . ".bak" )) {
  $peviousScopeUnchanged = true;
} else {
  $peviousScopeUnchanged = false;
  if ( !copy( $clientArgv , $clientArgv . ".bak" ) ) {
    do_error ( "Failed to backup $clientArgv" );
  }
}
/// main work loop
//
foreach ( $clientSource as $scope ) { // Perry, Highland , David etc, each must have unique name

  // These will be used globally for speed
  //
  $val=array(); 
  $key=array();
  $newKey=array();
  //$progressiveKeySub=array();  // list of progress
  $currentKeySub=array();
  $uniqueFoundKey=array();
  $key_map=array();
  $keyTrigger=array();
  $todoWork = array(); // for Send processes to read and action
  
  // control/limit output files
  //
  $firstRun = false;  // New XML source, assume false, we don't want to generate compare files
  $strangeResult = false; // Strange combo of counters original identical deleted different
  $jobAbandon = false;
  
  // whats todo for this job
  //
  // format can be xml, json, fixed , csvbar , csvcomma - fixed will need a rules file ie name.fixed.rules
  // flags can be none/null or zip, dont really need zip as the file extension will be checked
  // see v1,k1 etc for defining source columns in csv source
  //
  $parts = array_map ( 'trim' , explode ("|" , $scope ));
  $name = $parts[0];
  $URL = $parts[1]; 
  if ( isset ( $parts[2])) { $format = strtolower($parts[2]); } else { $format="xml"; }
  if ( isset ( $parts[3])) { $flags = $parts[3]; } else { $flags="none"; }
  if ( isset ( $parts[4])) { $filter = $parts[4]; } else { $filter=""; }
  check_filter ( "" , "" ); // reset the filter 

  if ( $name == "" || $URL == "" ) { 
    $name="invalid"; $URL = "Not given"; $jobAbandon = true; 
    do_error ( "bad line [" . $scope . "] in client.source" );
  }
  if ( strpos ( $URL , ".zip") !== false ) $flags .= ",.zip"; // maybe added twice
  if ( strpos ( $URL , ".Zip") !== false ) $flags .= ",.zip";
  if ( strpos ( $URL , ".ZIP") !== false ) $flags .= ",.zip";

  // open job files, global file handles will be used
  //
  $glblog = fopen ( "global.run.log" , "a" );

  $errorFile = $name . ".error.log";
  if ( file_exists($errorFile)) unlink ( $errorFile );
  $errlog = fopen ( $errorFile , "w" );

  $progFile = $name . ".progress.log";
  if ( file_exists($progFile)) unlink ( $progFile );
  $prolog = fopen ( $progFile , "w" );

  $buildFile = $name . ".build.log";
  if ( file_exists($buildFile)) unlink ( $buildFile );
  if ( $buildLogArgv ) $bldlog = fopen ( $buildFile , "w" );

  $csvResult = $name . ".latest.csv";
  if ( file_exists( $csvResult ) && filesize( $csvResult ) > 0 ) rename ( $csvResult , $name . ".previous.csv" ); 
  if ( file_exists( $csvResult )) unlink ( $csvResult ); // should not need
  $csv_out = fopen ( $csvResult , "w" );

  // Send start up messages - dont do before here
  //
  if ( $sorceScopeUnchanged ) {
    do_note ( "Revised scope from command line same which is good");
  } else {
    do_note ( "Revised scope from command line different");
  }
  if ( $peviousScopeUnchanged ) {
    do_note ( "Previous work scope file same which is good");
  } else {
    do_note ( "Overall Work scope file $clientArgv changed or is new!" );
  }

  // Get the maps for convert JSON/XML to csv
  //
  $tmp = explode ( "-" , trim($name) ); // ie David-SandBrock, Perry
  $mapName = $tmp[0] . ".key.map"; // ie Perry.key.map David.key.map
  $tmpKeyMap = array(); // reset each loop
  $tmpKeyMap = get_support_barLin ( $mapName ); // array of lines like 6,7,8,9|Plan|6|PlanNumber,PlanName
  if ( sizeof($tmpKeyMap) == 0 ) {
    if ( $format=="xml" || $format=="json" ) { do_error ( "Can't find " . $mapName . " Check name has correct case"); }
    // $jobAbandon = true; TODO maybe turn back on
    $key_map=array();
  } else {
    do_note ( "Found " . $mapName );
    $key_map = $tmpKeyMap ; // array of lines like 6,7,8,9|Plan|6|PlanNumber,PlanName
    if ( identical_exist ( $mapName , $mapName . ".bak" )) {
      do_note ( "Previous map same which is good " . $mapName );
      $keyMapSame=true;
    } else {
      do_note ( "Map has changed! " . $mapName );
      $firstRun = true; 
      if ( !copy( $mapName, $mapName . ".bak") ) {
        do_error ( "Failed to backup " . $mapName );
      }
    }
  }

  // Call the web resource
  //
  if ( strpos ( $URL, "http") === false ){
    global_note ( "No Web call -- " . $name . " -- " . $URL . " " .  date("Y-m-d H:i:s") );
    $getURLsArgv = false; // must be file provided?
  }
  if ( $jobAbandon == true ) $getURLsArgv = false; // override 
  //
  if ( $getURLsArgv && ( $format == "xml" ||  $format == "json" )) {
    global_note ( "Calling xml/json -- " . $name . " -- " . $URL . " " .  date("Y-m-d H:i:s") );
    //
    $xmlstr = get_from_url ( $URL ); // Allow redirects, pretend to be browser
    if ( is_string( $xmlstr ) && strlen ( $xmlstr ) > 0 ) {
      if ( file_exists($name . "." . $format )) {
        rename ( $name . "." . $format , $name . "." . $format . ".old" ); // ie Perry.xml.old
      } else {
        do_note ( "First run for " . $name . " " . $format);
        $firstRun = true; 
      }
      if ( file_put_contents( $name . "." . $format , $xmlstr ) === false ){
        do_error ( "Could not write " . $format . " file for " . $URL ); 
        $jobAbandon = true;
      }
      unset ( $xmlstr );
      if ( identical_exist ( $name . "." . $format , $name . "." . $format . ".old"  )) {
        do_note ( "URL source content is same as last call");
        $SourceContentSame=true;
      } else {
        do_note ( "URL source content has changed!");
      }
    } else {
      do_error ( "Could not read from" . $URL ); 
      $jobAbandon = true;
      unset ( $xmlstr );
    }
  }

  // Process XML or get csv
  //
  $objXmlDocument = false; $objJsonDocument = false; $arrOutput=array();
  if ( $jobAbandon == true ) $format="na-abandon"; // override, will stop format checks as there is no case
  //
  else  global_note ( "Processing " . $format . " -- " . $name . " -- " . $URL . " -- " .  date("Y-m-d H:i:s") );
  // 
  if ( $format == "xml" ) {
    //
    if ( !function_exists ( "simplexml_load_file" )) do_fatal ( "Missing function. Need: sudo apt-get install phpX.X-xml");
    $objXmlDocument = simplexml_load_file( $name . ".xml"); 
    if ($objXmlDocument === false ) {
      do_error ( "Parsing XML file " . $name ) ;
      foreach(libxml_get_errors() as $error) {
        do_error ( "XML error: " . $error->message );
      }
      $jobAbandon = true;
    }
  } elseif ( $format == "json" ) {
    //
    $xmlstr = file_get_contents( $name . "." . $format );
    if ( is_string( $xmlstr ) && strlen( $xmlstr) > 0 ) {
      $objJsonDocument = $xmlstr;  
      unset ( $xmlstr );    
    } else {
      do_error ( "No JSON returned" ); $objJsonDocument= false; $jobAbandon = true;
    }
  } elseif ( $format == "fixed" ) {

  } elseif ( $format == "csvbar" ||  $format == "csvcomma"  || $format == "csv") {
    //
    $extn = ".csv";
    if ( strpos ( $flags, ".zip" ) !== false ) $extn = ".zip";
    $target = '/tmp/' . $name . $extn;
    //
    // get the file
    //
    if ( !$jobAbandon && $getURLsArgv) {
      $cmd='wget -q -nv -O ' . $target . ' ' . $URL ; // bring file down
      exec( $cmd , $res , $err );
      $result = implode ( "|" , $res );
      // 0 No problems occurred, 1 Generic error code
      // 2 Parse error — for instance, when parsing command-line options, the .wgetrc or .netrc…
      // 3 File I/O error, 4 Network failure, 5 SSL verification failure, 6 Username/password authentication failure
      // 7 Protocol errors, 8 Server issued an error response
      if ( $err == 0 ) { do_note ( $cmd . " completed OK [" . $result . "] returned[" . $err . "]" ); }
      else { do_error ( $cmd . " Failed [" . $result . "] returned[" . $err . "]" ); $jobAbandon = true; }
      //
      if ( !$jobAbandon  && $extn == ".zip") {
        $cmd='unzip -qo ' . $target; // do the unzip, quite, overwrite
        exec( $cmd , $res , $err );
        $result = implode ( "|" , $res );
        if ( $err == 0 ) { do_note ( $cmd . " completed OK [" . $result . "] returned[" . $err . "]" ); }
        else { 
          if ( isset ( $unzip_errors[$err] )) $err = $unzip_errors[$err];
          do_error ( $cmd . " Failed [" . $result . "] returned[" . $err . "]" ); $jobAbandon = true; 
        }
      }
    }
    // find unzip file names
    //
    if ( !$jobAbandon && $extn == ".zip" && $getURLsArgv ) {
      $cmd='zipinfo -1 /tmp/' . $name . $extn;
      exec( $cmd , $res , $err );
      $result = implode ( "|" , $res );
      if ( $err == 0 ) { 
        do_note ( $cmd . " completed OK [" . $result . "] returned[" . $err . "]" ); 
        if ( sizeof ( $res ) == 1 ) {
          $target = trim ( $res[0]); // reset the target file name
          if ( file_exists($name . "." . $format )) {
            rename ( $name . "." . $format , $name . "." . $format . ".old" ); // ie Denton.csvbar.old
          } else {
            $firstRun = true; 
          }
          rename ( $target , $name . "." . $format ); 
          //
        } else { 
          if ( isset ( $unzip_errors[$err] )) $err = $unzip_errors[$err];
          do_error ( $cmd . " multiple files [" . $result . "] returned[" . $err . "]" ); $jobAbandon = true; $unzipname="na";
        }
      } else {
        do_error ( $cmd . " Failed [" . $result . "]" ); $jobAbandon = true; 
      }
    }  
  }

  // ok read the unzipped to array
  //
  if ( !$jobAbandon && ( $format == "csvbar" ||  $format == "csvcomma"  || $format == "csv") ) {
    if ( strpos ( $format, "bar" ) !== false ) $delim ='|'; else $delim =',';
    $header=""; // means get header from line 1 of csv - TODO read from file if no header in csv 
    $arrOutput = explode_csv ( $name . "." . $format , $flags , $delim , "" ); // make the raw csv look like a JSON converted array
  }

  // Convert XML to JSON if needed
  //
  if ( $format == "xml" ) {
    if ( $objXmlDocument === false ) do_error ( "JSON encode source empty " . $format );
    $objJsonDocument = json_encode($objXmlDocument);
    if ( $objJsonDocument === false ) { do_error ( "JSON encode from XML failed [" . json_last_error_msg ( ) . "]" ); $jobAbandon = true; }
  }
  unset ( $objXmlDocument );
  //
  if ( $format == "xml" || $format == "json" ) {
    if ( $objJsonDocument === false ) do_error ( "JSON decode source empty " . $format );
    $arrOutput = json_decode($objJsonDocument, TRUE);
    if ( $arrOutput === false ) { do_error ( "JSON decode failed [" . json_last_error_msg ( ) ."]" ); $jobAbandon = true; }
  }
  unset ( $objJsonDocument );

  // Iterate through the JSON converted array, collect useful key:value pairs
  // Apply the pairs to build keys for lower layers
  //
  if ( is_array( $arrOutput ) && sizeof ( $arrOutput ) > 0 && $jobAbandon == false ) {

    build_key_trigger (); // build necessary arrays from map
    $depth = array_depth ( $arrOutput );
    do_note ( $format . " " . $name . " has depth " . $depth );
    deep_loop ( $arrOutput ); // Hard work here
    unset ( $arrOutput );
    fixedcsv_from_array ( $name . ".hints.csv" , $uniqueFoundKey );
    //cache_set($name . ".progress.array", $uniqueFoundKey ); // read this to generate maps
  } else {
    do_error ( $format . " to array " . $name . " failed" );
    $jobAbandon = true;
  }

  close_work_files (); // ie latest.csv so diffs can work

  // Do net change assessment
  //
  if ( $jobAbandon == false && $firstRun == false && $skipDiffArgv == false ) {
    global_note ( "Calc Diffs " . $format . " -- " . $name . " -- " .  date("Y-m-d H:i:s") );
    $res=generate_change_csvs ( $name, $name . ".latest.csv" , $name . ".previous.csv" );
    if ( is_string($res)) {
      $listArr=explode ("|" , $res );
      $fp = fopen ( "global.todo" , "a");
      if ( $fp !== false && count($listArr) > 0 ){
        foreach ( $listArr as $line ) {
          fwrite ( $fp, $line . "\n" );
        } 
      }
      fclose($fp);
      do_note ( "Sent [" .$res. "] to global.todo for API processing");
    }
  }
  else {
    do_note ( "Add change csv's skipped " . $name );
  }

  if ( $firstRun == true  && $jobAbandon == false ) {
    copy ( $name . ".latest.csv" , $name . "." . time() . ".new.csv" ); // everything will be new
  }

  if ( $jobAbandon == false ) { 
    global_note ( "Completed " . $format . " -- " . $name . " -- " .  date("Y-m-d H:i:s") );
  } else {
    global_note ( "ABANDONED " . $format . " -- " . $name . " -- " .  date("Y-m-d H:i:s") );
  }
  
  close_log_files (); // does its own zero size clean up

  // remove anything opened but empty
  //
  if ( file_exists( $errorFile ) && filesize( $errorFile ) == 0 ) unlink ( $errorFile );
  if ( file_exists( $progFile  ) && filesize( $progFile ) == 0 )  unlink ( $progFile );
  if ( file_exists( $buildFile ) && filesize( $buildFile ) == 0 ) unlink ( $buildFile );
  if ( file_exists( $csvResult ) && filesize( $csvResult ) == 0 ) unlink ( $csvResult );

}
exit(1);

// --- end of mainline ---


function explode_csv ( $target , $flags , $delim , $header) { // turn a csv into a useful array

  $parts = array_map ( 'trim' , explode ("," , $flags ));
  if ( trim($header) == "" ) { 
    $headerline1 = true; 
    $csvheader = 'ERROR Must get from file'; // set a bad value, should be overwritten
  } else { 
    $headerline1 = false;
    $csvheader = array_map ( 'trim' , explode ("," , $header )); // convert to array
    if ( sizeof ( $csvheader ) < 2 ) $csvheader = 'ERROR header to short'; 
  }

  $key=array(); $val=array(); $output=array();

  $parts = array_unique($parts);
  foreach ( $parts as $v ) {
    $v = strtolower($v);
    if ( $v[0] == "k") { $key[ intval( str_replace( "k" , "" , $v )) ] = $v; } // warning, key[X] X starts from 1
    if ( $v[0] == "v") { $val[ intval( str_replace( "v" , "" , $v )) ] = $v; }
    if ( $v[0] == "s") { 
      $tmp = str_replace( "s" , "" , $v );
      $set = array_map ( 'trim' , explode ("-" , $tmp ));
      if ( sizeof ( $set ) == 2 ) {
        for ( $i=$set[0] ; $i <= $set[1] ; $i++ ) {
          $val[$i] = $v;
        }
      }
    }
  }

  if ( empty($key)) { do_error ( "No keys for " . $target); return ($output); }
  if ( empty($val)) { do_error ( "No values for " . $target); return ($output); }

  if ( ! file_exists( $target )) { do_error ( "File not found " . $target); return ($output); }
  do_note ( "Start read to array " . $target ); 
  $fptmp = fopen ( $target , 'r');
  $lineCount = 1; $sample=array(); $tmpKeyList=""; $tmpValList="";
  while (($line = fgetcsv($fptmp, 0, $delim, '"' , "\\" )) !== FALSE) {
    if ( sizeof ( $line ) > 1 ) {
      // good lines
      if ( $lineCount == 1 ) { $refLinesize = count ( $line ); }
      if ( $lineCount == 1 && $headerline1 ) {
        $csvheader = $line; 
      } else {
        if ( count ( $line ) != $refLinesize ) do_error ( "WARN line size at $lineCount different ref=" . $refLinesize . " this=" . count ( $line ) ); 
        // not header line, process the data
        if ( $lineCount < 100000 ) {
          foreach ( $csvheader as $i => $j ) {
            if ( isset ( $sample[$j] )) { 
              if ( strlen ( $sample[$j] ) < 120 && strpos ( $sample[$j] , $line[$i] ) === false  && trim( $line[$i] ) != "") {
                $sample[$j] .= " , " . $line[$i]; 
                }
              }
            else {
              $sample[$j] = $line[$i];
            }
          }
        }
        // always do this for non header
        $keybuild = ""; $i=0; $colarray=array();
        for ( $i=0; $i < sizeof ($line ); $i++ ) { // for each cell
          $point=$i+1; // val array smallest is [1] => v1 , same for key
          if ( isset ( $val[$point] )) { if ( isset ( $csvheader[$i] )) $colarray [ $csvheader[$i] ] = $line[$i]; }
          if ( isset ( $key[$point] )) { if ( isset ( $csvheader[$i] )) $keybuild .= $line[$i] ."^"; }
        }
        if ( $keybuild != "" ) $output[ substr( $keybuild, 0, -1) ] = $colarray; // get rid of last ^
      }
      $lineCount++;
    }
  }
  fclose($fptmp);

  if ( count ( $csvheader ) != $refLinesize ) do_error ( "WARN Size header and data different . ref=" . $refLinesize . " head=" . count ( $csvheader) ); 
  // Show keys and samples
  foreach ( $csvheader as $i => $j) { //  keys are like [8] => k8 [10] => k10 [11] => k11
    $point=$i+1;
    if ( isset ( $key[$point] )) $tmpKeyList .= "(" . $point .") " . $j . " , "; 
    if ( isset ( $val[$point] )) $tmpValList .= "(" . $point .") " . $j . " , ";
  }
  do_note ( "Keys components are : " . $tmpKeyList . "\n" );
  do_note ( "Values collected are: " . $tmpValList . "\n" );

  $k=1;
  foreach ( $sample as $i => $j ) { // everything keys and values
    do_note ( "col:" . $k . " [" . $i . "] e.g: " . $j );
    $k++;
  }

  do_note( "End read to array " . $target . " had " . $lineCount . " lines"); 
  return ( $output );
}

function get_support_barLin ( $name ) { // process support files, bar delimited, multiple elements via comma delimiter

  $out=array();
  // get things like Perry.key.map , xml.client.source
  if ( !file_exists( $name )) return ( $out );
  $out = explode( "\n", file_get_contents( $name ));
  foreach ( $out as $k => $v ) if ( strlen ( $v ) < 2 ) unset ($out[$k]); // get rid of junk
  return ( $out ); // array of lines like 6,7,8,9|Plan|6|PlanNumber,PlanName
}

function fixedcsv_from_array ( $name , $array ) { // special fixed column generic output no matter what source file


  $fh = fopen( $name , 'w' );
  if ( !$fh ) { do_error ( "fixed csv, Can't open" . $name ); return (0); }
  foreach ( $array as $k => $v ) {
	  if (  trim($k) != "" && trim($v) != "" ) fputcsv ( $fh , make_fixed ( $k , $v )); 
  }
  fclose ( $fh );
  return(1);
}

function check_filter ( $key , $filter ) { 

  // function fixedcsv_from_array ( $name , $array ) { // special fixed column generic output no matter what source file
  // LEWISVILLE, HIGHTOP + cert_land_hstd_val,addrs_name
  static $filter_code = "";
  //
  if ( $filter == "" ) { $filter_code = ""; return (true); }  // if no filter then always true and therfore save record
  //
  $res=false;
  if ( strlen ( $filter_code ) == 0 ) {
    // create filters
    do_note ( "Filter is [" . $filter . "]" );
    $lines = array_map ( "trim", explode ( "+" , $filter )); // get sets for
    foreach ( $lines as $k => $v ) {
      $sets = array_map ( "trim", explode ( "," , $v ));
      $filter_comp="";
      foreach ( $sets as $k2 => $v2 ) {
        $filter_comp .= " strpos( \$key , \"" . $v2 . "\" ) !== false || ";
      }
      $filter_comp = " ( " . substr( $filter_comp, 0, -3) . " ) ";
      if ( $filter_code == "" ) { $filter_code = $filter_comp; }
      else { $filter_code .= " && " . $filter_comp; }
    }
    //
    $filter_code = "\$res=" . $filter_code . ";";
    do_note ( "Filter code [" . $filter_code . "]" ); 
  }
  try {
    $i = eval( $filter_code );
  } catch (ParseError $e) {
    // Report error somehow
    do_fatal ( "Could not eval [" . $filter_code . "] error:" . $e ); 
  }
  return ( $res ); 
}


function generate_change_csvs ( $name, $new , $old ) {  // assume the last column is data and files are same fixed width

  global $csvResult; // should be closed here

  if ( !file_exists( $new ) ) { do_error ( "Does not exist " . $new ); return(0); }
  if ( !file_exists( $old ) ) { do_note ( "Does not exist " . $old ); return(0); }
  if ( filesize ( $new ) < 2 ) { do_error ( "No data in " . $new ); return(0); }
  if ( filesize ( $old ) < 2 ) { do_note ( "No data in " . $old ); return(0); }
  if ( identical_exist ( $new, $old )) { do_note ( "Nothing to do " . $new . " " . $old . " are identical"); return (0); }
  //
  $nf = fopen( $new , 'r' );
  if ( !$nf ) { do_error ( "Can't open" . $new ); return (0); }
  $of = fopen( $old , 'r');
  if ( !$of ) { do_error ( "Can't open" . $old ); fclose ( $nf) ; return(0); }

  $nc_file = $name . "." . time() . ".new.csv";
  $sc_file = $name . "." . time() . ".same.csv";
  $dc_file = $name . "." . time() . ".deleted.csv";
  $xc_file = $name . "." . time() . ".changed.csv";

  $nc = fopen( $nc_file , 'w');
  if ( !$nc ) { do_error ( "Can't open new csv"); }
  $sc = fopen( $sc_file , 'w'); // note no time stamp
  if ( !$sc ) { do_error ( "Can't open same csv"); }
  $dc = fopen( $dc_file , 'w');
  if ( !$dc ) { do_error ( "Can't open delete csv"); }
  $xc = fopen( $xc_file , 'w');
  if ( !$xc ) { do_error ( "Can't open change csv"); }

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
          do_build ( "DIFF [" . $k . "] New=" . $v . " Old=" . $old_store[$k] );
          if ( $xc !== false ) fputcsv ( $xc , make_fixed ( $k , $v ));  // the new different
        } else {
          do_build ( "Bypass Diff test [" . $k . "] New=" . $v . " Old=" . $old_store[$k] );
        }
      }
      unset ( $old_store[$k] ); // get rid of it 
    } else {
      // new key not seen
      $origonal++;
      do_build ( "NEW [" . $k . "] Val=" . $v );
      if ( $nc !== false ) fputcsv ( $nc , make_fixed ( $k , $v ));  // not seen before
    }
  }
  foreach ( $old_store as $k => $v ) {
    $deleted++;
    do_build ( "GONE [" . $k . "] Val=" . $v );
    if ( $dc !== false ) fputcsv ( $dc , make_fixed ( $k , $v ));  // still in old but not in new
    }
  //
  global_note ("Compare " . $new . " to " . $old . " New=" . $origonal . " Same=" . $identical . " Deleted=" . $deleted . " Diff=" . $different );

  if ( $nc !== false ) { fclose ( $nc ); }
  if ( $sc !== false ) { fclose ( $sc ); }
  if ( $dc !== false ) { fclose ( $dc ); }
  if ( $xc !== false ) { fclose ( $xc ); }

  $work_todo = "";  // bar list of chnage files
  if ( file_exists( $nc_file )) { if ( filesize( $nc_file ) == 0 ) { unlink ( $nc_file ); } else { $work_todo .= $nc_file . "|"; } }
  if ( file_exists( $sc_file )) { if ( filesize( $sc_file ) == 0 ) { unlink ( $sc_file ); } } // dont send same
  if ( file_exists( $dc_file )) { if ( filesize( $dc_file ) == 0 ) { unlink ( $dc_file ); } else { $work_todo .= $dc_file . "|"; } }
  if ( file_exists( $xc_file )) { if ( filesize( $xc_file ) == 0 ) { unlink ( $xc_file ); } else { $work_todo .= $xc_file . "|"; } }
  if ( identical_exist( $csvResult , $sc_file )) { unlink ( $sc_file ); do_error ( "Identical last/previous should have avoided same=current" ); }// if same = output
  $work_todo = rtrim($work_todo, "|");

  return ( $work_todo );
}

function close_work_files () { // ie latest.csv

  global $csv_out;

  if ( isset ( $csv_out ) && $csv_out !== false ) fclose ( $csv_out ); 

}

function close_log_files () {

  global $errlog, $prolog, $bldlog;

  if ( isset ( $errlog ) && $errlog !== false ) fclose ( $errlog ); 
  if ( isset ( $prolog ) && $prolog !== false ) fclose ( $prolog ); 
  if ( isset ( $bldlog ) && $bldlog !== false ) fclose ( $bldlog );
  if ( isset ( $glblog ) && $glblog !== false ) fclose ( $glblog );

}

function do_error ( $txt ) {

  global $errlog, $sendConsArgv;

  if ( $errlog == false ) $errlog = fopen ( "global.err.log" , "a" );
  if ( $sendConsArgv ) print ( "ERROR: " . $txt . "\n");
  fwrite ( $errlog , "ERROR: " . $txt . "\n" );
}

function do_fatal ( $txt ) {

  global $errlog, $sendConsArgv;

  if ( $errlog == false ) $errlog = fopen ( "global.err.log" , "w" );
  if ( $sendConsArgv ) print ( "FATAL: " . $txt . "\n");
  fwrite ( $errlog , "FATAL: " . $txt . "\n" );
  close_work_files ();
  close_log_files ();
  exit(0);
}

function global_note ( $txt ) {

  global $prolog, $sendConsArgv, $glblog, $name;

  if ( $prolog == false ) $prolog = fopen ( "global.err.log" , "w" );
  if ( $sendConsArgv ) print ( "NOTE: " . $txt . "\n");
  fwrite ( $prolog , $txt . "\n" );
  fwrite ( $glblog , $name . " : " . $txt . "\n" );
}

function do_note ( $txt ) { // important progress steps

  global $prolog, $sendConsArgv;

  if ( $prolog == false ) $prolog = fopen ( "global.err.log" , "w" );
  if ( $sendConsArgv ) print ( "NOTE: " . $txt . "\n");
  fwrite ( $prolog , $txt . "\n" );
}

function do_build ( $txt ) { // noisy progress logs

  global $bldlog, $sendConsArgv, $buildLogArgv;

  if ( $buildLogArgv == false ) return;
  if ( $bldlog == false ) $bldlog = fopen ( "global.err.log" , "w" );
  // if ( $sendConsArgv ) print ( "NOTE: " . $txt . "\n");  // Dont log to console as too much text
  fwrite ( $bldlog , $txt . "\n" );
}

function build_key_trigger (){ // helper for JSON level headings to csv key

  
  global $key_map, $keyTrigger;  // Key_map is 3,4,5,6,7,8,9|Builder|3|BrandName

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

  // pass in the trigger ie "spec" get back the actual values from the source array ie 
  // SpecStreet1,SpecCity,SpecState,SpecZIP >> Smith~New-york~NY~10010
  // base case 2,4,5,6,7,8,9,10,11,12|Corporation|2|CorporateBuilderNumber
  //
  //  currentKeySub has -- BuilderNumber => BRITTON
  //                       SubdivisionNumber => 633

  global $key_map, $currentKeySub;

  $sources = array(); // maybe no replace
  foreach ( $key_map as $k => $v ) {  // 7,8,9|Spec|8|SpecStreet1,SpecCity,SpecState,SpecZIP
    $parts = array_map('trim', explode ( "|" , $v));
    if ( sizeof( $parts ) == 4 && strval($name) == trim ($parts[1] )) {
      $sources = array_map ( 'trim', explode ( "," , $parts[3] )); // which source triggers
      //$levels = array_map ( 'trim', explode ( "," , $parts[0] )); // which levels
    } 
  }

  $combo = "";
  foreach ( $sources as $k1 => $v1 ) { 
    if ( isset ( $currentKeySub[$v1] )) $combo .= $currentKeySub[$v1] . "~"; // get latest value
  }
  
  do_build ( ">>called for $name $level - passing back [$combo]");
  if ( $combo == "" ) return ( $name ); // not found
  return ( substr( $combo, 0, -1) );
}

function get_from_url($url) { // Get data stream from URL
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
    $str = curl_exec($ch);
    if ( is_string( $str )) {
      if ( strlen ( trim ( $str )) == 0 ) do_error ( "Curl response: [" . curl_error($ch) . "] Zero size retun results" );
      else do_note ( "Curl URL response size was " . strlen ( $str ) );
    } else {
      do_error ( "Curl response: [" . curl_error($ch) . "]" ); $str = "";
    }
    curl_close($ch);
    return $str;
}

function array_depth(array $array) {  // How deeps is the JSON converted to matrix
    
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


function record_natural_key ( $level ) { // build up the natural/desired key

  global $key, $newKey, /* $progressiveKeySub,*/ $currentKeySub;

  // keep a list, we may use for table adjust
  //if ( !isset ($progressiveKeySub[$key[$level]])) $progressiveKeySub[$key[$level]] = $newKey[$level] . " | " . $level; 
  //else $progressiveKeySub[$key[$level]] .= " , " . $newKey[$level] . " | " . $level;

  $currentKeySub[$key[$level]] = $newKey[$level]; // latest only

  foreach ( $currentKeySub as $k => $v ) do_build ( "##1 $k => $v ##");
  // HomestoreID => HomestoreID
  // BuilderNumber => BRITTON
  // Status => Status
  // SubdivisionNumber => 633
}

function do_lev ( $level ) { // we are at level in XML array where there are key:value pairs


  global $val, $key, $newKey, $keyTrigger, /* $progressiveKeySub, */ $currentKeySub, $uniqueFoundKey, $csv_out, $filter,
         $skipHintArgv, $excImageArgv;

  static $old_lev = 0;
  if ( $old_lev != $level ) {
    do_build ( "* do_lev changed from $old_lev to $level");
    $old_lev = $level;
  }
  static $count = 0;

  // Do not try to do any key work if no data
  //if ( is_array ( $val[$level] )) return; 

  // Update the key as we go
  $saveKey=""; $saveKeyNew ="";
  //
  if ( isset ( $keyTrigger[$level])) {  // ie found [4] => legaldesc,subblock,sublot
    //
    if ( strpos ( $keyTrigger[$level] , $key[$level] ) !== false ) { // rule trigger
      do_build ( "++ Hit trigger at " . $level . " for [" . $key[$level] . "] Setting val to [" . $val[$level] 
        . "]" . " Driver was [" . $keyTrigger[$level] . "]" );
      $newKey[$level] = $val[$level]; 
      record_natural_key ( $level );  // set $currentKeySub [$key[$level]] = $newKey[$level];
    }
  }

  // Build up the key
  for ( $i=1; $i<= $level; $i++ ) {
    //if ( strtolower($key[$i]) == 'row' ) $key[$i] = $key[$i] . "-L" . $i; // make useful for replace 
    $saveKey .= $key[$i] . "^"; 
    // input trigger ie "spec" get back actual values Smith St~New-york~NY~10010  
    $saveKeyNew .= get_prefered_key ( $key[$i] , $level ). "^";  // uses $currentKeySub  
  }
  $saveKey = substr($saveKey , 0, -1); // get rid of excess delimiter
  $saveKeyNew = substr($saveKeyNew , 0, -1);

  do_build ( "##2 $saveKeyNew ##");

  // write the csv in a fixed column format
  if ( strpos ( $saveKeyNew , "@attributes") === false ) { // Can't use these as they come before key trigger
    if ( check_filter ( $saveKeyNew , $filter )) { 
      fputcsv ( $csv_out , make_fixed ( $saveKeyNew , $val[$level] ));
    }
  }

  // Hints processing
  if ( !$skipHintArgv && trim ( $val[$level] ) != "" ) { 

    if ( ( strpos ( $val[$level] , ".png") !== false || strpos ( $val[$level] , ".jpg") !== false ) && $excImageArgv == true) {
      // found image asset
    } else {
      //
      if (strlen ( $val[$level] ) > 40 ) { $tmp = substr( $val[$level], 0, 40) . "..more.."; }
      else { $tmp = $val[$level]; }
      //
      do_build ( "[" . $level . "] " . $saveKey . " | " . $saveKeyNew . " -> " . $tmp );

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
  }
  
  $count++;
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

  static $old_lev = 0;
  if ( $old_lev != $level ) {
    do_build ( "+ go_deeper changed from $old_lev to $level");
    $old_lev = $level;
    //do_lev ( $level );  // re-process keys on way up and down
  }

  if ( !isset ($newKey[$level] )) { 
    if ( strtolower($key[$level]) == 'row' ) $key[$level] = $key[$level] . "-L" . $level; // make useful for replace 
    do_build ( "== No NewKey " . $level . " Set to [" . $key[$level] . "]");
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
                          do_error ( "Array structure is too deep" );
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