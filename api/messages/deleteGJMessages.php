<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Communication.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$Message = new Message();

	if (isset($_POST['messageID']) || isset($_POST['messages'])) 
	{
		$messageID 		= ExploitPatch::remove($_POST["messageID"]);
		$accountID 		= GJPCheck::getAccountIDOrDie();
		$messages       = ExploitPatch::numbercolon($_POST["messages"]);

		$deleteMessage = $Message->delete($accountID, 0, 0, 0, $messageID, $messages);

		exit($deleteMessage);
	}
	
	exit("-1");
?>