<?php

function clean_builder_model ( $model ) {

$b_model = str_replace ( "FT.", "" , strtoupper ( $model ));
$b_model = str_replace ( " LOTS", "" , $b_model );
$b_model = str_replace ( "TOLL BROTHERS AT" , "" , $b_model );
$b_model = str_replace ( " GARDENS" , "" , $b_model );
$b_model = str_replace ( "EXECUTIVE COLLECTION" , "" , $b_model );
$b_model = str_replace ( "HOMESITES" , "" , $b_model );
$b_model = str_replace ( " (WALLER ISD)" , "" , $b_model );
$b_model = str_replace ( "AT MERIDIAN" , "" , $b_model );
$b_model = str_replace ( "GEHAN HOMES" , "" , $b_model );

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

return ( $b_model );
}
