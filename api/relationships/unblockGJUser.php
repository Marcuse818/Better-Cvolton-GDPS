<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Friend.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$Friend		= new Friend();	

	if(isset($_POST['targetAccountID'])) 
	{
		$accountID 				= GJPCheck::getAccountIDOrDie();
		$targetAccountID 		= ExploitPatch::remove($_POST["targetAccountID"]);

		$unblockUser = $Friend->unblock($accountID, $targetAccountID);

		echo $unblockUser;
	}
	else
	{
		echo -1;
	}