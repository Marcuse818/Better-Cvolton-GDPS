<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Communication.php";
	
	require_once "../../core/lib/exploitPatch.php";

	$LevelComments = new LevelComments();

	if (isset($_POST['binaryVersion']) || isset($_POST['gameVersion']) || isset($_POST['mode']))
	{
		$page 				= isset($_POST['page']) ? ExploitPatch::remove($_POST["page"]) : 0;
		$levelID 			= isset($_POST['levelID']) ? $_POST['levelID'] : 0;
		$userID				= isset($_POST['userID']) ? $_POST['userID'] : 0;

		$LevelComments->mode	= isset($_POST["mode"]) ? ExploitPatch::remove($_POST["mode"]) : 0;
		$LevelComments->count	= (isset($_POST["count"]) && is_numeric($_POST["count"])) ? ExploitPatch::remove($_POST["count"]) : 10;

		$binaryVersion 		= isset($_POST['binaryVersion']) ? ExploitPatch::remove($_POST["binaryVersion"]) : 0;
		$gameVersion 		= isset($_POST['gameVersion']) ? ExploitPatch::remove($_POST["gameVersion"]) : 0;
		
		$loadComments = $LevelComments->getData(0, $userID, $page, 0, $levelID, $gameVersion, $binaryVersion);

		exit($loadComments);
	}
	
	exit("-1");
?>