<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/ListPack.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";
	
	$List = new Lists();
	
	$accountID = GJPCheck::getAccountIDOrDie();
	$type = (!empty($_POST["type"])) ? ExploitPatch::number($_POST["type"]) : 0;
	$page = (isset($_POST["page"]) && is_numeric($_POST["page"])) ? ExploitPatch::number($_POST["page"]) . "0" : 0;

	$List->followed = ExploitPatch::numbercolon($_POST["followed"]);
	$List->difficulty = (!empty($_POST["diff"])) ? ExploitPatch::numbercolon($_POST["diff"]) : "-";
	$List->demonFilter = (!empty($_POST["demonFilter"])) ? ExploitPatch::number($_POST["demonFilter"]) : 0;
	$List->star = ExploitPatch::number($_POST["star"]);
	$List->featured = ExploitPatch::remove($_POST["featured"]);
	$List->string = ExploitPatch::remove($_POST["str"]);
	
	$getList = $List->get_data($accountID, $page, $type);
	
	exit($getList);
?>