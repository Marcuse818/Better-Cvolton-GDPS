<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/LevelPack.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";
	
	$List = new Lists();
	
	$accointID = GJPCheck::getAccountIDOrDie();
    $listID = ExploitPatch::number($_POST["listID"]);

    $List->listName = !empty(ExploitPatch::remove($_POST["listName"])) ? ExploitPatch::remove($_POST["listName"]) : "Unnamed list";
    $List->listDescription = ExploitPatch::remove($_POST["listDesc"]);
    $List->list_levels = ExploitPatch::remove($_POST["listLevels"]);
    $List->difficulty = ExploitPatch::number($_POST["difficulty"]);
    $List->list_version = ExploitPatch::number($_POST["listVersion"]) == 0 ? 1 : ExploitPatch::number($_POST["listVersion"]);
    $List->original = ExploitPatch::number($_POST["original"]);
    $List->unlisted = ExploitPatch::number($_POST["unlisted"]);
    $List->secret = ExploitPatch::remove($_POST["secret"]);
    
    $uploadList = $List->upload($accountID, $listID);
    
    exit($uploadList);
?>