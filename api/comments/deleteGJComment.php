<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Main.php";
	require_once "../../core/LevelComments.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";
	
	$main 				= new Main();
	$LevelComments 		= new LevelComments();

	if (isset($_POST["commentID"]))
	{
		$accountID 		= GJPCheck::getAccountIDOrDie();
		$userID 		= $main->get_user_id($accountID);
		$commentID 		= ExploitPatch::remove($_POST["commentID"]);
		$permission		= $main->getRolePermission($accountID, "actionDeleteComment");

		$deleteComment = $LevelComments->delete($accountID, $userID, $commentID,$permission);
		
		exit($deleteComment);
	}
	
	exit("-1");	
?>