<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Friend.php";

	require_once "../../core/lib/exploitPatch.php";
	require_once "../../core/lib/GJPCheck.php";

	$Friend 	= new Friend();
    
    $gjp = ($_POST["gameVersion"] > 21) ? $_POST["gjp2"] : $_POST["gjp"];
    
	if(isset($_POST["accountID"]) || (isset($_POST["page"]) || is_numeric($_POST["page"])) || isset($gjp)) 
	{
		$accountID 		= GJPCheck::getAccountIDOrDie();
		$page 			= ExploitPatch::number($_POST["page"]);
		$getSent 		= (isset($_POST["getSent"])) ? ExploitPatch::remove($_POST["getSent"]) : 0;

		$getFriendRequest = $Friend->getData($accountID, $page, $getSent);

		exit($getFriendRequest);
	}

	exit("-1");
?>