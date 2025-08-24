<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Chest.php";

	require_once "../../core/lib/exploitPatch.php";
	
	$Chest 	= new Chest();

	$accountID 			= ExploitPatch::remove($_POST["accountID"]);
	$udid 				= ExploitPatch::remove($_POST["udid"]);
	$check 				= ExploitPatch::remove($_POST["chk"]);
	$rewardType 		= ExploitPatch::remove($_POST["rewardType"]);

	$getRewards = $Chest->get_data($accountID, $udid, $check, $rewardType);

	exit($getRewards);
?>