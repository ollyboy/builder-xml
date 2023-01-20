<?php

words_match ( "hello", $argv[1], $argv[2] , "forward" );
words_match ( "hello", $argv[2], $argv[1] , "reverse" );
checkWords ( $argv[1], $argv[2] );
checkWords ( $argv[2], $argv[1] );


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
      }
    }
    if ( !$hit ) {
      $rtn = false;
      print ( "Missed [$tv]\n");
    }
  }
  if ( $mode != "" ) {
    if ( $rtn ) { print ( "--good--\n"); } else { print ( "--bad--\n"); }
  }
  return ( $rtn );
}

function checkWords($string1, $string2) {
    // Remove all non-alphanumeric characters
    $string1 = preg_replace('/[^A-Za-z0-9\s]/', '', $string1);
    $string2 = preg_replace('/[^A-Za-z0-9\s]/', '', $string2);

    // Convert strings to uppercase
    $string1 = strtoupper($string1);
    $string2 = strtoupper($string2);

    // Remove single words
    $string1 = preg_replace('/\b\w+\b(?<!\w)/', '', $string1);
    $string2 = preg_replace('/\b\w+\b(?<!\w)/', '', $string2);

    // Split strings into arrays of words
    $words1 = explode(" ", $string1);
    $words2 = explode(" ", $string2);

    // Check if words from first string can be found in second string
    $result = true;
    foreach ($words1 as $word) {
        if (!in_array($word, $words2)) {
            $result = false;
            break;
        }
    }

    if ( $result ) { print ( "--good--\n"); } else { print ( "--bad--\n"); }
    return $result;
}
