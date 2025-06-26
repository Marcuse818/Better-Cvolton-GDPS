<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Main.php";
	require_once "../../core/Scores.php";
	require_once "../../core/Level.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";
	
	$Main 		= new Main();
	$Level 		= new Score();

	if(isset($_POST["userName"]) || isset($_POST["secret"]) || isset($_POST["stars"]) || isset($_POST["demons"]) || isset($_POST["icon"]) || isset($_POST["color1"]) || isset($_POST["color2"]))
	{
		$accountID 				= $Main->get_post_id();
		$Level->userName 				= ExploitPatch::charclean($_POST["userName"]);
		$userID					= $Main->get_user_id($accountID, $Level->userName);
		$hostname 				= $Main->get_ip();

		$Level->secret				= ExploitPatch::remove($_POST["secret"]);
		$Level->stars 				= ExploitPatch::remove($_POST["stars"]);
		$Level->demons 				= ExploitPatch::remove($_POST["demons"]);
		$Level->icon 				= ExploitPatch::remove($_POST["icon"]);
		$Level->color1 				= ExploitPatch::remove($_POST["color1"]);
		$Level->color2 				= ExploitPatch::remove($_POST["color2"]);

		$Level->gameVersion 		= !empty($_POST["gameVersion"]) ? ExploitPatch::remove($_POST["gameVersion"]) : 1;
		$Level->binaryVersion 		= !empty($_POST["binaryVersion"]) ? ExploitPatch::remove($_POST["binaryVersion"]) : 1;
		$Level->coins 				= !empty($_POST["coins"]) ? ExploitPatch::remove($_POST["coins"]) : 0;
		$Level->iconType 			= !empty($_POST["iconType"]) ? ExploitPatch::remove($_POST["iconType"]) : 0;
		$Level->userCoins 			= !empty($_POST["userCoins"]) ? ExploitPatch::remove($_POST["userCoins"]) : 0;
		$Level->special 			= !empty($_POST["special"]) ? ExploitPatch::remove($_POST["special"]) : 0;
		$Level->accIcon 			= !empty($_POST["accIcon"]) ? ExploitPatch::remove($_POST["accIcon"]) : 0;
		$Level->accShip 			= !empty($_POST["accShip"]) ? ExploitPatch::remove($_POST["accShip"]) : 0;
		$Level->accBall 			= !empty($_POST["accBall"]) ? ExploitPatch::remove($_POST["accBall"]) : 0;
		$Level->accBird 			= !empty($_POST["accBird"]) ? ExploitPatch::remove($_POST["accBird"]) : 0;
		$Level->accDart 			= !empty($_POST["accDart"]) ? ExploitPatch::remove($_POST["accDart"]) : 0;
		$Level->accRobot 			= !empty($_POST["accRobot"]) ? ExploitPatch::remove($_POST["accRobot"]) : 0;
		$Level->accGlow 			= !empty($_POST["accGlow"]) ? ExploitPatch::remove($_POST["accGlow"]) : 0;
		$Level->accSpider 			= !empty($_POST["accSpider"]) ? ExploitPatch::remove($_POST["accSpider"]) : 0;
		$Level->accExplosion 		= !empty($_POST["accExplosion"]) ? ExploitPatch::remove($_POST["accExplosion"]) : 0;
		$Level->diamonds 			= !empty($_POST["diamonds"]) ? ExploitPatch::remove($_POST["diamonds"]) : 0;
		$Level->moons 				= !empty($_POST["moons"]) ? ExploitPatch::remove($_POST["moons"]) : 0;
		$Level->color3 				= !empty($_POST["color3"]) ? ExploitPatch::remove($_POST["color3"]) : 0;
		$Level->accSwing 			= !empty($_POST["accSwing"]) ? ExploitPatch::remove($_POST["accSwing"]) : 0;
		$Level->accJetpack 			= !empty($_POST["accJetpack"]) ? ExploitPatch::remove($_POST["accJetpack"]) : 0;
		
		$updateUserScore = $Level->update($accountID, $userID, $hostname);

		echo $updateUserScore;
	}
	else
	{
		echo -1;
	}