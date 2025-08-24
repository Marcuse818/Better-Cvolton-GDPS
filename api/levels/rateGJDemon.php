<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Main.php";
	require_once "../../core/Level.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";


	$Main 		= new Main();
	$Level 		= new Level();
    
    $gjp2check = isset($_POST['gjp2']) ? $_POST['gjp2'] : $_POST['gjp'];
    
	if(isset($gjp2check) || isset($_POST["rating"]) || !isset($_POST["levelID"]) || !isset($_POST["accountID"]))
    {
		$accountID	    = GJPCheck::getAccountIDOrDie();
		$levelID 			= ExploitPatch::remove($_POST["levelID"]);
		$rating 			= ExploitPatch::remove($_POST["rating"]);
	
		$rateDemon = $Level->rateDemon($accountID, $levelID, $rating);
		
		echo $rateDemon;
	}
	else
	{
		echo -1;
	}
?>
