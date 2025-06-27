<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Main.php";
	require_once "../../core/LevelComments.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";
	require_once "../../core/lib/Commands.php";

	$main 				= new Main();
	$LevelComments 		= new LevelComments();
	$commands 			= new Commands();

	if (isset($_POST['userName']) || isset($_POST['comment'])) 
	{
		$accountID			= $main->get_post_id();
		$userName			= !empty($_POST['userName']) ? ExploitPatch::remove($_POST['userName']) : "";
		$userID				= $main->get_user_id($accountID, $userName);

		$comment 			= ExploitPatch::remove($_POST['comment']);
		$comment			= ($gameVersion < 20) ? base64_encode($comment) : $comment;
		$levelID			= ($_POST["levelID"] < 0 ? "-" : "") . ExploitPatch::number($_POST["levelID"]);
		
		$LevelComments->percent 		= !empty($_POST["percent"]) ? ExploitPatch::remove($_POST["percent"]) : 0;
		$LevelComments->game_version	= isset($_POST['gameVersion']) ? ExploitPatch::remove($_POST["gameVersion"]) : 0;

		$commentDecode		= base64_decode($comment);
		
		if($commands->doCommands($accountID, $commentDecode, $levelID)) 
		{
			exit($gameVersion > 20 ? "temp_0_Command executed successfully!" : "-1");
		}

		$uploadComment = $LevelComments->upload($accountID, $userID, $userName, $comment, $levelID);

		exit($uploadComment);
	}

	exit("-1");
?>