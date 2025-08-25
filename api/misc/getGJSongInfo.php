<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Misc.php";

	require_once "../../core/lib/exploitPatch.php";

	$Misc 		= new Misc();

	if (!empty($_POST["songID"])) 
	{
		$songID = ExploitPatch::remove($_POST["songID"]);

		$songInfo = $Misc->getSong($songID);

		exit($songInfo);
	}

	exit("-1");
?>