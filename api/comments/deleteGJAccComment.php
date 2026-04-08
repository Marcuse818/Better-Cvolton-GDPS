<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Main.php";
	require_once "../../core/AccountComments.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$main 					= new Main();
	$AccountComment 		= new AccountComments();

	if (isset($_POST["commentID"]))
	{
		$userID				= $main->get_user_id(GJPCheck::getAccountIDOrDie());
		$commentID			= ExploitPatch::remove($_POST["commentID"]);
		$permission 		= $main->getRolePermission($userID, "actionDeleteComment");

		$deleteAccountComment = $AccountComment->delete_Comment($userID, $commentID, $permission);

		exit($deleteAccountComment);
	} 
	
	exit("-1");
?>