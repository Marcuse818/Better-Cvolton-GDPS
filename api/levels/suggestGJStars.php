<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Main.php";
	require_once "../../core/Level.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$Main 		= new Main();
	$Level 		= new Levels();
    
    $gjp2check = isset($_POST['gjp2']) ? $_POST['gjp2'] : $_POST['gjp'];
    
	if (isset($gjp2check) || isset($_POST["stars"]) || isset($_POST["feature"]) || isset($_POST["levelID"])) 
	{
		$accountID 		= GJPCheck::getAccountIDOrDie();
		$levelID 		= ExploitPatch::remove($_POST["levelID"]);
		$starStars 		= ExploitPatch::remove($_POST["stars"]);
		$feature 		= ExploitPatch::remove($_POST["feature"]);
		$difficulty 	= $Main->get_difficulty($starStars, "", "stars");

		$suggestStars = $Level->rateSuggest($accountID, $levelID, $starStars, $feature, $difficulty);

		echo $suggestStars;
	}
	else
	{
		echo -1;
	}