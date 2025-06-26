<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Friend.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$Friend 	= new Friend();

	if(isset($_POST["targetAccountID"])){
		$accountID 				= GJPCheck::getAccountIDOrDie();
		$targetAccountID 		= ExploitPatch::remove($_POST["targetAccountID"]);
		$isSender 				= ExploitPatch::remove($_POST["isSender"]);

		$deleteFriendRequest = $Friend->delete($accountID, $targetAccountID, $isSender);

		echo $delete_friend_request;
	}
	else
	{
		echo -1;
	}
?>