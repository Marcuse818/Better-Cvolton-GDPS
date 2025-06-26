<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Scores.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";
	require_once "../../core/lib/XORCipher.php";

	$Platformer = new Platformer();
	
	$accountID = GJPCheck::getAccountIDOrDie();
	$levelID = ExploitPatch::remove($_POST["levelID"]);
	$time = ExploitPatch::number($_POST["time"]);
	$points = ExploitPatch::number($_POST["points"]);
	$mode = ExploitPatch::number($_POST["mode"]) == 1 ? "points" : "time";
	$type = (!isset($_POST["type"])) ? 1 : $_POST["type"];

	$getPlatformerScores = $Platformer->getData($accountID, $levelID, $type, $mode, $time, $points);

	echo $getPlatformerScores;