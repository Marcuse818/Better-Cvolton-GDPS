<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Main.php";
	require_once "../../core/Level.php";

	require_once "../../core/lib/exploitPatch.php";

	$Main 		= new Main();
	$Level 		= new Level();

	if($_POST["levelID"])
	{
		$levelID 	= ExploitPatch::remove($_POST["levelID"]);
		$hostname 	= $Main->get_ip();
		
		$reportLevel = $Level->report($levelID, $hostname);
		
		exit($reportLevel);
	} 
	exit("-1");
?>