<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Scores.php";

	require_once "../../core/lib/exploitPatch.php";

	$Creators 	= new Creators();

	if (isset($_POST["type"])) 
	{
		$accountID 		= ExploitPatch::remove($_POST["accountID"]);
		$type			= ExploitPatch::remove($_POST["type"]);

		$getCreators = $Creators->getData($accountID, 0, $type);

		exit($getCreators);
	}

	exit("-1");
?>