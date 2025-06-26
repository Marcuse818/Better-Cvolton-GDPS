<?php
	chdir(dirname(__FILE__));
	require_once "../../core/Communication.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$message 		= new Message();

	$accountID 		= GJPCheck::getAccountIDOrDie();
	$page 			= ExploitPatch::remove($_POST["page"]);
	$getSent 		= isset($_POST["getSent"]) ? 1 : 0;

	$getMessages = $message->getData($accountID, 0, $page, $getSent);

	echo $getMessages;
?>