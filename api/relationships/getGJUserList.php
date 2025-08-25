<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Friend.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";

	$Friend 	= new Friend();

	if (isset($_POST["type"]) || is_numeric($_POST["type"]))
	{
		$accountID 		= GJPCheck::getAccountIDOrDie();
		$type 			= ExploitPatch::remove($_POST["type"]);

		$getUserList = $Friend->getDataList($accountID, $type);

		exit($getUserList);
	}

	exit("-1");
?>