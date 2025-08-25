<?php 
	chdir(dirname(__FILE__));
	require_once "../../core/Main.php";
    require_once "../../core/Level.php";
	
	require_once "../../core/lib/Lib.php";
	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

    $Main 		= new Main();
	$Lib 		= new Lib();
	$Level 		= new Level();

	if (!empty($_POST['levelID']) && $_POST['levelID'] != "") 
	{
		$accountID 			    = GJPCheck::getAccountIDOrDie();
		$levelID			    = ExploitPatch::remove($_POST['levelID']);
		$inc 				    = !empty($_POST['inc']) && $_POST['inc'];
		$extras 			    = !empty($_POST['extras']) && $_POST['extras'];
		$hostname			    = $Main->get_ip();
		
		$Level->binaryVersion	    = (!empty($_POST['binaryVersion'])) ? ExploitPatch::remove($_POST['levelID']) : 0;
		$Level->gameVersion 	= (!empty($_POST['gameVersion'])) ? ExploitPatch::remove($_POST['gameVersion']) : 1;
		
		$downloadLevel = $Level->download($accountID, $levelID, $inc, $extras, $hostname);
		
		exit($downloadLevel);
	} 
	
	exit("-1");
?>