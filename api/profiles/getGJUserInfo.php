<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Profile.php";
	
	require_once "../../core/lib/exploitPatch.php";

	$Account 	= new Account();
	
	if (isset($_POST["targetAccountID"]))
	{
		$accountID 			= !empty($_POST["accountID"]) ? GJPCheck::getAccountIDOrDie() : 0;
		$targetAccountID 	= ExploitPatch::number($_POST["targetAccountID"]);
		
		$getAccountInfo = $Account->getData($accountID, $targetAccountID);

		echo $getAccountInfo;
	}
	else
	{
		echo -1;
	}
