<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Main.php";
	require_once "../../core/AccountComments.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";
	
	$main 				= new Main();
	$AccountComment 	= new AccountComments();
	
	if (isset($_POST["userName"]) || isset( $_POST["comment"])) 
	{
		$accountID		= GJPCheck::getAccountIDOrDie();
		$userName 		= ExploitPatch::remove($_POST["userName"]);

		$userID			= $main->get_user_id($accountID, $userName);
		$comment		= ExploitPatch::remove($_POST["comment"]);

		$uploadComment = $AccountComment->upload_comment($userID, $userName, $comment);

		exit($uploadComment);
	}
	
	exit("-1");
?>