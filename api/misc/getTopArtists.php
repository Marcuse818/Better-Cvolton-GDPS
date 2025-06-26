<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Misc.php";

	require_once "../../core/lib/exploitPatch.php";
	require_once "../../config/topArtists.php";

	$Misc 	= new Misc();

	if (isset($_POST["page"])) 
	{
		$page 			= (is_numeric($_POST["page"])) ? ExploitPatch::number($_POST["page"]) . 0 : 0;
		$url 			= "http://www.boomlings.com/database/getGJTopArtists.php";
		$request 		= "page=$page&secret=Wmfd2893gb7";

		$getArtist = $Misc->getArtists($page, $url, $request);
		
		echo $getArtist;
	} 
	else
	{
		echo -1;
	}
