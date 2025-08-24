<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Main.php";
	require_once "../../core/Level.php";

	require_once '../../core/lib/GJPCheck.php';
	require_once '../../core/lib/exploitPatch.php';
	require_once '../../core/lib/Lib.php';

	$Main 		= new Main();
	$Level 		= new Level();
	$Lib		= new Lib();

	$userName 				= ExploitPatch::charclean($_POST['userName']);
	$levelID 				= ExploitPatch::remove($_POST['levelID']);

	$levelName 				= ExploitPatch::charclean($_POST['levelName']);
	$Level->gameVersion		= ExploitPatch::remove($_POST['gameVersion']);
	$Level->binaryVersion	= !empty($_POST["binaryVersion"]) ? ExploitPatch::remove($_POST["binaryVersion"]) : 0;
	$audioTrack 			= ExploitPatch::remove($_POST['audioTrack']);
	$levelLength 			= ExploitPatch::remove($_POST['levelLength']);
	$secret 				= ExploitPatch::remove($_POST['secret']);
	$levelString 			= ExploitPatch::remove($_POST['levelString']);
	$gjp 					= ExploitPatch::remove(isset($_POST['gjp2']) ? $_POST['gjp2'] : $_POST['gjp']);
	$levelVersion 			= ExploitPatch::remove($_POST['levelVersion']);
    $ts 					= !empty($_POST["ts"]) ? ExploitPatch::number($_POST["ts"]) : 0;
	$songs 					= !empty($_POST["songIDs"]) ? ExploitPatch::numbercolon($_POST["songIDs"]) : '';
	$sfxs 					= !empty($_POST["sfxIDs"]) ? ExploitPatch::numbercolon($_POST["sfxIDs"]) : '';
	
	$accountID 				= $Main->get_post_id();
	$hostname 				= $Main->get_ip();
	$userID 				= $Main->get_user_id($accountID, $userName);

	$auto 					= !empty($_POST['auto']) ? ExploitPatch::remove($_POST['auto']) : 0;
	$original 				= !empty($_POST['original']) ? ExploitPatch::remove($_POST['original']) : 0;
	$twoPlayer 				= !empty($_POST['twoPlayer']) ? ExploitPatch::remove($_POST['twoPlayer']) : 0;
	$songID 				= !empty($_POST['songID']) ? ExploitPatch::remove($_POST['songID']) : 0;
	$objects 				= !empty($_POST['objects']) ? ExploitPatch::remove($_POST['objects']) : 0;
	$coins 					= !empty($_POST['coins']) ? ExploitPatch::remove($_POST['coins']) : 0;
	$requestedStars 		= !empty($_POST['requestedStars']) ? ExploitPatch::remove($_POST['requestedStars']) : 0;
	$extraString 			= !empty($_POST['extraString']) ? ExploitPatch::remove($_POST['extraString']) : '29_29_29_40_29_29_29_29_29_29_29_29_29_29_29_29';
	$levelInfo 				= !empty($_POST['levelInfo']) ? ExploitPatch::remove($_POST['levelInfo']) : '';
	$unlisted 				= !empty($_POST['unlisted1']) ? ExploitPatch::remove($_POST['unlisted1']) : (!empty($_POST['unlisted']) ? ExploitPatch::remove($_POST['unlisted']) : 0);
	$unlisted2 				= !empty($_POST['unlisted2']) ? ExploitPatch::remove($_POST['unlisted2']) : $unlisted;
	$ldm 					= !empty($_POST['ldm']) ? ExploitPatch::remove($_POST['ldm']) : 0;
	$wt 					= !empty($_POST['wt']) ? ExploitPatch::remove($_POST['wt']) : 0;
	$wt2 					= !empty($_POST['wt2']) ? ExploitPatch::remove($_POST['wt2']) : 0;
	$settingsString 		= !empty($_POST['settingsString']) ? ExploitPatch::remove($_POST['settingsString']) : '';

	$levelDescription 		= ExploitPatch::remove($_POST['levelDesc']);

	$rawDesc				= $levelDescription;
	$levelDescription		= str_replace("+", "-", $rawDesc);
	$levelDescription 		= str_replace("/", "_", $levelDescription);
    $password 				= ExploitPatch::remove($_POST["password"]);
		
	$uploadLevel = $Level->upload(
		$accountID, 
		$levelID, 
		$userName,
		$hostname,
		$userID,
		$levelName,
		$audioTrack,
		$levelLength,
		$secret,
		$levelString,
		$gjp,
		$levelVersion,
		$ts,
		$songs,
		$sfxs,
		$auto,
		$original,
		$twoPlayer,
		$songID,
		$objects,
		$coins,
		$requestedStars,
		$extraString,
		$levelInfo,
		$unlisted,
		$unlisted2,
		$ldm,
		$wt,
		$wt2,
		$settingsString,
		$levelDescription,
		$password
	);

	echo $uploadLevel;
?>
