<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Main.php";
	require_once "../../core/Communication.php";

	require_once "../../core/lib/exploitPatch.php";

	$main 			= new Main();
	$AccountComment = new AccountComment();

	if (isset($_POST["accountID"]))
	{
		$accountID 		= ExploitPatch::remove($_POST["accountID"]);
		$userID 		= $main->get_user_id($accountID);
		$page 			= ExploitPatch::remove($_POST["page"]);

		$loadAccountComments = $AccountComment->getData($accountID, $userID, $page);

		exit($loadAccountComments);
	}
	exit("-1");
?>