<?php
	chdir(dirname(__FILE__));
	require_once "../../core/LevelSearch.php";

	require_once "../../core/lib/GJPCheck.php";
	require_once "../../core/lib/exploitPatch.php";
	
	$LevelSearch		= new LevelSearch();

	$accountID = GJPCheck::getAccountIDOrDie();
	$page = ExploitPatch::number($_POST["page"])."0";
	$type = ExploitPatch::number($_POST["type"]);

	$gameVersion = (empty($_POST["gameVersion"])) ? 1 : ExploitPatch::remove($_POST["gameVersion"]);
	$binaryVersion = ExploitPatch::number($_POST["binaryVersion"]);
	
	$difficulty = null;
	$demonFilter = null;
	$starFeatured = null;
	$original = null;
	$coins = null;
	$starEpic = null;
	$uncompleted = null;
	$onlyCompleted = null;
	$completedLevels = null;
	$song = null;
	$customSong = null;
	$twoPlayer = null;
	$star = null;
	$noStar = null;
	$gauntlet = null;
	$len = null;
	$legendary = null;
	$mythic = null;
	$followed = null;
	$string = "";
	
	if (!empty($_POST["diff"])) $difficulty = ExploitPatch::numbercolon($_POST["diff"]);
	if (!empty($_POST["demonFilter"])) $demonFilter = ExploitPatch::number($_POST["demonFilter"]);
    if (!empty($_POST["featured"])) $starFeatured = $_POST['featured'];
	if (!empty($_POST["original"])) $original = $_POST['original'];
	if (!empty($_POST["coins"])) $coins = $_POST['coins'];
	if (!empty($_POST["epic"])) $starEpic = $_POST['epic'];
	if (!empty($_POST["uncompleted"])) $uncompleted = ExploitPatch::numbercolon($_POST['uncompleted']);
	if (!empty($_POST["onlyCompleted"])) $onlyCompleted = ExploitPatch::numbercolon($_POST["onlyCompleted"]);
	if (!empty($_POST["completedLevels"])) $completedLevels = ExploitPatch::numbercolon($_POST["completedLevels"]);
	if (!empty($_POST["song"])) $song = ExploitPatch::number($_POST["song"]);
	if (!empty($_POST["customSong"])) $customSong = ExploitPatch::number($_POST["customSong"]);
	if (!empty($_POST["twoPlayer"])) $twoPlayer = $_POST["twoPlayer"];
	if (!empty($_POST["star"])) $star = $_POST["star"];
	if (!empty($_POST["noStar"])) $noStar = $_POST["noStar"];
	if (!empty($_POST["gauntlet"])) $gauntlet = $_POST["gauntlet"];
	if (!empty($_POST["len"])) $len = ExploitPatch::numbercolon($_POST["len"]);
	if (!empty($_POST["legendary"])) $legendary = ExploitPatch::remove($_POST["legendary"]);
	if (!empty($_POST["mythic"])) $mythic = ExploitPatch::remove($_POST["mythic"]);
	if (!empty($_POST["followed"])) $followed = ExploitPatch::numbercolon($_POST["followed"]);
	if (!empty($_POST["str"])) $string = ExploitPatch::remove($_POST["str"]);
    
	$levelSearch = $LevelSearch->search(
		$accountID, 
		$page, 
		$type,
		$gameVersion,
		$binaryVersion,
		$difficulty,
		$demonFilter,
		$starFeatured,
		$original,
		$coins,
		$starEpic,
		$uncompleted,
		$onlyCompleted,
		$completedLevels,
		$song,
		$customSong,
		$twoPlayer,
		$star,
		$noStar,
		$gauntlet,
		$len,
		$legendary,
		$mythic,
		$followed,
		$string
	);
    
	echo $levelSearch;