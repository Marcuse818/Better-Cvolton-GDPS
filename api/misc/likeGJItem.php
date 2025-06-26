<?php
	chdir(dirname(__FILE__));
	require_once "../../core/Main.php";
	require_once "../../core/Misc.php";

	require_once "../../core/lib/exploitPatch.php";
	
	$Main 		= new Main();
	$Misc 		= new Misc();

	if (isset($_POST["itemID"]))
	{
		$itemID 	= ExploitPatch::remove($_POST['itemID']);
		$type 		= isset($_POST['type']) ? $_POST['type'] : 1;
		$like 		= isset($_POST['like']) ? $_POST['like'] : 1;
		$hostname 	= $Main->get_ip();

		$likeItem = $Misc->like($itemID, $type, $like, $hostname);

		echo $likeItem;
	} 
	else
	{
		echo -1;
	}