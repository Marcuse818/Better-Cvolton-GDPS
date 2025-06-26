<?php
	chdir(dirname(__FILE__));
	require_once "../../core/Communication.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$Message 		= new Message();

	if (isset($_POST["messageID"])) 
	{
		$accountID = GJPCheck::getAccountIDOrDie();
		$messageID = ExploitPatch::remove($_POST["messageID"]);
		$isSender = isset($_POST["isSender"]) ? 1 : 0;

		$downloadMessage = $Message->download($accountID, $messageID, $isSender);

		echo $downloadMessage;
	}
	else
	{
		echo -1;
	}