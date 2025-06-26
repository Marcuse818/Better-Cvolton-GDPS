<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Misc.php";

	require_once "../../core/lib/exploitPatch.php";

	$Misc 		= new Misc();

	if (!empty($_POST["songID"])) 
	{
		$songID = ExploitPatch::remove($_POST["songID"]);

		$songInfo = $Misc->getSong($songID);

		echo $songInfo;
	}
	else
	{
		echo -1;
	}