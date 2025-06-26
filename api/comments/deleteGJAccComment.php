<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Main.php";
	require_once "../../core/Communication.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$main 					= new Main();
	$AccountComment 		= new AccountComment();

	if (isset($_POST["commentID"]))
	{
		$userID				= $main->get_user_id(GJPCheck::getAccountIDOrDie());
		$commentID			= ExploitPatch::remove($_POST["commentID"]);
		$permission 		= $main->getRolePermission($userID, "actionDeleteComment");

		$deleteAccountComment = $AccountComment->delete(0, $userID, $permission, $commentID);

		exit($deleteAccountComment);
	} 
	else
	{
		exit("-1");
	}