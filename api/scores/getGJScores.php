<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Scores.php";

	require_once "../../core/lib/exploitPatch.php";
	require_once "../../core/lib/GJPCheck.php";

	$Leaderboard = new Leaderboard();
	
	if (!empty($_POST["accountID"])) {
		$accountID = GJPCheck::getAccountIDOrDie();
	}
	else
	{
		$accountID = ExploitPatch::remove($_POST["udid"]);
		if (is_numeric($accountID)) exit (-1);
	}

	$type = ExploitPatch::remove($_POST["type"]);
	$count = ExploitPatch::remove($_POST["count"]);
	$Leaderboard->gameVersion = ExploitPatch::remove($_POST["gameVersion"]);

	$getScores = $Leaderboard->getData($accountID, 0, $type, 'none', 0, 0, $count);

	exit($getScores);
?>