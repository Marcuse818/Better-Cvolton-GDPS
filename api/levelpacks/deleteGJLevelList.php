<?php
	chdir(dirname(__FILE__));

	require_once "../../core/LevelPack.php";
	require_once "../../core/lib/GJPCheck.php";

	$List = new Lists();

	$accountID = GJPCheck::getAccountIDOrDie();
	$listID = ExploitPatch::number($_POST["listID"]);
	
	$deleteList = $List->deleteList($accountId, $listID);

	exit($deleteList);
?>