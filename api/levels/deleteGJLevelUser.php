<?php
	chdir(dirname(__FILE__));


	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	require_once "../../core/Level.php";
	
	$Main 		= new Main();
	$Level 		= new Level();
	
	if(!empty($_POST['levelID']))
	{
		$accountID 			= GJPCheck::getAccountIDOrDie();
		$userID 			= $Main->get_user_id($accountID);
		$levelID 			= ExploitPatch::remove($_POST["levelID"]);

		$deleteLevel = $Level->delete($userID, $levelID);
		
		echo $deleteLevel;
	} 
	else
	{
		echo -1;
	}