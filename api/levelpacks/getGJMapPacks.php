<?php
	chdir(dirname(__FILE__));

	require_once "../../core/LevelPack.php";
	require_once "../../core/lib/exploitPatch.php";

	$MapPack = new MapPacks();

	$page = ExploitPatch::remove($_POST["page"]);

	$loadMapPacks = $MapPack->getData(0, $page);

	exit($loadMapPacks);
?>