<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Friend.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";


	$Friend 	= new Friend();

	if(isset($_POST["requestID"]))
	{
		$accountID 	= GJPCheck::getAccountIDOrDie();
		$requestID 	= ExploitPatch::remove($_POST["requestID"]);

		$acceptFriendRequest = $Friend->accept($accountID, $requestID);

		exit($acceptFriendRequest);
	} 

	exit("-1");
?>