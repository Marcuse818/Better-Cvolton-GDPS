<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Rewards.php";

	require_once "../../core/lib/XORCipher.php";
	require_once "../../core/lib/generateHash.php";
	require_once "../../core/lib/exploitPatch.php";

	$Challenges 	= new Challenges();

	if (isset($_POST["udid"]) || !is_numeric($_POST["udid"])) 
	{
		$accountID 	= ExploitPatch::remove($_POST["accountID"]);
		$udid 		= ExploitPatch::remove($_POST["udid"]);
		$check 		= ExploitPatch::remove($_POST["chk"]);

		$getChallenges = $Challenges->getData($accountID, $udid, $check);

		echo $getChallenges;
	}
	else 
	{
		echo -1;
	}
