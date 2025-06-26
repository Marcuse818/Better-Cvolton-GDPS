<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Rewards.php";

	require_once "../../core/lib/exploitPatch.php";
	
	$Chests 	= new Chests();

	$accountID 			= ExploitPatch::remove($_POST["accountID"]);
	$udid 				= ExploitPatch::remove($_POST["udid"]);
	$check 				= ExploitPatch::remove($_POST["chk"]);
	$rewardType 		= ExploitPatch::remove($_POST["rewardType"]);

	$getRewards = $Chests->getData($accountID, $udid, $check, $rewardType);

	echo $getRewards;