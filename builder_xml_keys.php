<?php

// pass in file name and optional tag to get key info on the XML, passes back variables with the & marker

function get_xml_key_position ( &$corp, &$builder, &$community , &$plan, &$keyParts, // outputs
								$filename , $tag="" ) { // inputs

// run the bash script ./findtag.sh to find tags, edit this to find different tag
// Keyparts should be one greater than the latest position key part
// may need to add logic for deeper tags, passing blank tag will find stuff at the same level as BasePrice, ie SqrFt, Beds etc	
if ( $tag == "" ) $tag = "BasePrice"; // The defualt is to match Baseprice


//./Highland-PecanSquare.latest.csv:CORPHIGHLAND,Highland,"Pecan Square: 40ft. lots ","Plan Greyton~Plan Greyton",0,BasePrice,,,,,,,,,,BasePrice,433990
if ( $filename == "Highland-PecanSquare.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=3; $keyParts =4; return(1);
}

//./Highland-WolfRanch.latest.csv:CORPHIGHLAND,Highland,"Wolf Ranch","Plan Carlton~Plan Carlton",0,BasePrice,,,,,,,,,,BasePrice,447990
if ( $filename == "Highland-WolfRanch.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=3; $keyParts =4; return(1);
}	

//./Highland-UnionPark.latest.csv:CORPHIGHLAND,Highland,"Union Park: Artisan Series - 50ft. lots ",0,"Plan Dorchester~Plan Dorchester",0,BasePrice,,,,,,,,,BasePrice,518990
if ( $filename == "Highland-UnionPark.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}	

//./David-Harvest.latest.csv:"David Weekley Homes","David Weekley Homes","Harvest Gardens",0,4628~Kepley,0,BasePrice,,,,,,,,,BasePrice,522990
if ( $filename == "David-Harvest.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}	

//./David-Bridgeland.latest.csv:"David Weekley Homes","David Weekley Homes","Parkland Row 42' Homesites",0,4183~Belville,0,BasePrice,,,,,,,,,BasePrice,356990
if ( $filename == "David-Bridgeland.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}	

//./TollBrothers.latest.csv:CORPTOLL,"Toll Brothers",0,Subdivision,Plan,Spec,0,SpecPrice,,,,,,,,SpecPrice,672495
//./TollBrothers.latest.csv:CORPTOLL,"Toll Brothers",1,"Pecan Square",0,247688~Westbury,0,BasePrice,,,,,,,,BasePrice,640995
if ( $filename == "TollBrothers.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=3; $plan=5; $keyParts =6; return(1);
}	

//./MandI.latest.csv:MHI,"Coventry Homes","Palmera Ridge 60'",0,1864~Liberty,0,,,,,,,,,,BasePrice,463990
if ( $filename == "MandI.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}	

//./David-WolfRanch.latest.csv:"David Weekley Homes","David Weekley Homes","Wolf Ranch",6242~Halden,0,BasePrice,,,,,,,,,,BasePrice,597990
if ( $filename == "David-WolfRanch.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=3; $keyParts =4; return(1);
}	

//./AshtonWoods.latest.csv:CORP42,"Ashton Woods",0,"Reverie on Cumberland",0,1425-REVTH~Bellamy,0,BasePrice,,,,,,,,BasePrice,488200
// TODO lots of key dups, try deeper index ie keyparts=7 not 6
if ( $filename == "AshtonWoods.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=3; $plan=5; $keyParts =7; return(1);
}	

// /MandI-Harvest.latest.csv:MIHomes,"M/I Homes-Dallas / Fort Worth",Harvest,0,Plan,BasePrice,,,,,,,,,,BasePrice,427990
if ( $filename == "MandI-Harvest.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}	
//./Darling.latest.csv:TaylorMorrisonUSCorp,"Taylor Morrison - Phoenix",0,"Alamar Encore Collection",0,862090~Hudson,0,BasePrice,,,,,,,,BasePrice,424990
if ( $filename == "Darling.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=3; $plan=5; $keyParts =6; return(1);
}	

//./TaylorMorrison.latest.csv:TaylorMorrisonUSCorp,"Taylor Morrison",0,"Alamar Encore Collection",0,862090~Hudson,0,BasePrice,,,,,,,,BasePrice,424990
if ( $filename == "TaylorMorrison.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=3; $plan=5; $keyParts =6; return(1);
}	

//./CBJeni.latest.csv:NHF-3515,"CB JENI Homes","Celina Hills",0,Sweetwater-12511~Sweetwater,0,BasePrice,,,,,,,,,BasePrice,386990
if ( $filename == "CBJeni.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}

//./Coventry.latest.csv:MHI,"Coventry Homes",0,"Santa Rita Ranch Homestead 50' Homesites",0,1691~Izoro,0,BasePrice,,,,,,,,BasePrice,479990
if ( $filename == "Coventry.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=3; $plan=5; $keyParts =6; return(1);
}

//./David-PecanSquare.latest.csv:"David Weekley Homes","David Weekley Homes","Pecan Square - Gardens",5455~Paseo,0,BasePrice,,,,,,,,,,BasePrice,457990
if ( $filename == "David-PecanSquare.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=3; $keyParts =4; return(1);
}

//./Highland-Pomona.latest.csv:CORPHIGHLAND,Highland,"Pomona: 50ft. lots ",0,"Plan Camden~Plan Camden",0,BasePrice,,,,,,,,,BasePrice,449990
if ( $filename == "Highland-Pomona.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}

//./Highland-Harvest.latest.csv:CORPHIGHLAND,Highland,"Harvest: Townside ",0,"Plan Devon~Plan Devon",0,BasePrice,,,,,,,,,BasePrice,475300
if ( $filename == "Highland-Harvest.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}

//./Perry.latest.csv:PERRYCORP,"BRITTON HOMES",0,"The Tribute 60'",0,P519A,0,BasePrice,,,,,,,,BasePrice,880900
//                   PERRYCORP,"PERRY HOMES",  1,"ShadowGlen 55'",23,P2519W,0,BasePrice,,,,,,,,BasePrice,649900
//                   PERRYCORP,"PERRY HOMES",  1,"ShadowGlen 65'",92,P2999W,BasePrice,,,,,,,,,BasePrice,674900
if ( $filename == "Perry.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=3; $plan=5; $keyParts =6; return(1);
}

//./Ravenna.latest.csv:RAVENNAHOMES,"Ravenna Homes",1990,0,StartingPrice,,,,,,,,,,,StartingPrice,"$278,900"
// TODO startingprice? is this a spec, community missing???
if ( $filename == "Ravenna.latest.csv" && $tag == "StartingPrice" ) {
	$corp = 0; $builder=1; $community=3; $plan=2; $keyParts =4; return(1); // looks wrong! no BasePrice and price has $ plus commas
}

//./Highland-Sandbrock.latest.csv:CORPHIGHLAND,Highland,"Sandbrock Ranch: 45ft. lots ",0,"Plan Alpina~Plan Alpina",0,BasePrice,,,,,,,,,BasePrice,386990
if ( $filename == "Perry.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=3; $plan=5; $keyParts =6; return(1);
}

//./MandI-Bridgeland.latest.csv:MIHomes,"M/I Homes","Harper's Preserve",0,V2T_JZttn0Wxv_RgTBxK4w~Balboa,0,BasePrice,,,,,,,,,BasePrice,397490
// TODO plans has junk in it
if ( $filename == "MandI-Bridgeland.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}

//./David-SandBrock.latest.csv:"David Weekley Homes","David Weekley Homes","Sandbrock Ranch",5454~Belton,0,BasePrice,,,,,,,,,,BasePrice,448990
if ( $filename == "David-SandBrock.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=3; $keyParts =4; return(1);
}

//./Highland-WoodlandsHills.latest.csv:CORPHIGHLAND,Highland,"The Woodlands Hills: Artisan Series ",0,"Plan Carlton~Plan Carlton",0,BasePrice,,,,,,,,,BasePrice,306990
if ( $filename == "Highland-WoodlandsHills.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}

//./AmericanLegend.latest.csv:AMLEGENDHOMES,"American Legend Homes","Light Farms",0,"62b4a4ce4db0487a6a9c71bd~3520 Crescent Lane",0,BasePrice,,,,,,,,,BasePrice,645123
// TODO Looks like an address not a community
if ( $filename == "AmericanLegend.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}

//./David-WoodlandsHills.latest.csv:CorpDW,"David Weekley Homes","The Woodlands Hills 45' ",0,B001~Cloverstone,0,BasePrice,,,,,,,,,BasePrice,291990
if ( $filename == "David-WoodlandsHills.latest.csv" && $tag == "BasePrice" ) {
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}

//./Chesmar-Bridgeland.latest.csv:2,"Chesmar Homes Houston",NorthGrove,0,"4582~Ann Arbor",0,BasePrice,,,,,,,,,BasePrice,338990
// TODO check why no corp
if ( $filename == "Chesmar-Bridgeland.latest.csv" && $tag == "BasePrice" ) { 
	$corp = 0; $builder=0; $community=1; $plan=3; $keyParts =4; return(1);
}

//./Gehan-WoodlandsHills.latest.csv:PriceLow,,,,,,,,,,,,,,,PriceLow,299990
// TODO nasty flat XML file
if ( $filename == "Gehan-WoodlandsHills.latest.csv" && $tag == "BasePrice" ) { 
	$corp = 0; $builder=1; $community=2; $plan=3; $keyParts =4; return(1);
}

//./Highland-Bridgeland.latest.csv:CORPHIGHLAND,Highland,"Bridgeland: The Patios ",0,"Plan Berkley~Plan Berkley",0,BasePrice,,,,,,,,,BasePrice,349990
if ( $filename == "Highland-Bridgeland.latest.csv" && $tag == "BasePrice" ) { 
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}

//./TriPointe-UnionPark.latest.csv:"Tri Pointe Homes","Tri Pointe Homes","Discovery Collection at Union Park",0,Emery,0,BasePrice,,,,,,,,,BasePrice,497990
if ( $filename == "TriPointe-UnionPark.latest.csv" && $tag == "BasePrice" ) { 
	$corp = 0; $builder=1; $community=2; $plan=4; $keyParts =5; return(1);
}

// nothing found
$corp = 0; $builder=0; $community=0; $plan=0; $keyParts =0; return(1);
return(0);
}
