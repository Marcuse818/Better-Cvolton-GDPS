<?php
chdir(dirname(__FILE__));
	require_once "../../core/Friend.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$Friend 	= new Friend();

	if(isset($_POST["toAccountID"]))
	{
		$accountID 				= GJPCheck::getAccountIDOrDie();
		$targetAccountID 		= ExploitPatch::number($_POST["toAccountID"]);
		$comment 				= ExploitPatch::remove($_POST["comment"]);

		$uploadFriendRequest = $Friend->upload($accountID, $targetAccountID, $comment);

		exit($uploadFriendRequest);
	}

	exit("-1");
?>