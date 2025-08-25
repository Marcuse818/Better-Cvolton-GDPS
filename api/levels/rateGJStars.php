<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Main.php";
	require_once "../../core/Level.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$main 		= new Main();
	$Level 		= new Level();
    
    $gjp2check = isset($_POST['gjp2']) ? $_POST['gjp2'] : $_POST['gjp'];
    
	if (isset($gjp2check) || isset($_POST["stars"]) || isset($_POST["levelID"]))
	{
		$accountID  = GJPCheck::getAccountIDOrDie();
		$levelID 	= ExploitPatch::remove($_POST["levelID"]);
		$starStars 	= ExploitPatch::remove($_POST["stars"]);
		
		$rateLevel = $Level->rateStar($accountID, $levelID, $starStars);

		exit($rateLevel);	
	}
	
	exit("-1");
?>