<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Scores.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";
	require_once "../../core/lib/XORCipher.php";

	$Level 		= new Score();

	$accountID 	= GJPCheck::getAccountIDOrDie();
	$levelID 	= ExploitPatch::remove($_POST["levelID"]);
	$type 		= (isset($_POST["type"])) ? $_POST["type"] : 1;
	
	$Level->percent = ExploitPatch::remove($_POST["percent"]);
	$Level->attempts = !empty($_POST["s1"]) ? $_POST["s1"] - 8354 : 0;
	$Level->clicks = !empty($_POST["s2"]) ? $_POST["s2"] - 3991 : 0;
	$Level->time = !empty($_POST["s3"]) ? $_POST["s3"] - 4085 : 0;
	$Level->progresses = !empty($_POST["s6"]) ? 
		XORCipher::cipher(base64_decode(str_replace("_", "/", str_replace("-", "+", $_POST["s6"]))), 41274) : 0;
	$Level->dailyID = !empty($_POST["s10"]) ? $_POST["s10"] : 0;

	$getLevelScores = $Level->getData($accountID, $levelID, $type);

	exit($getLevelScores);
?>