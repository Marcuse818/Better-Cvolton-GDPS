<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Level.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$Level 			= new Level();

	if (isset($_POST['levelDesc']) && isset($_POST['levelID'])) 
	{
		$levelID 				= ExploitPatch::remove($_POST["levelID"]);
		$levelDescription 		= ExploitPatch::remove($_POST["levelDesc"]);

		if (isset($_POST['udid']) && !empty($_POST['udid'])) 
		{
			$accountID = ExploitPatch::remove($_POST["udid"]);
			if (is_numeric($accountID)) exit("-1");
		} 
		else 
		{
			$accountID = GJPCheck::getAccountIDOrDie();
		}

		$updateDescription = $Level->updateDesc($accountID, $levelID, $levelDescription);

		exit($updateDescription);
	}
	
	exit("-1");
?>