<?php
	chdir(dirname(__FILE__));
	
	require_once "../../core/Profile.php";

	require_once "../../core/lib/exploitPatch.php";

	$Account 	= new Account();

	if (isset($_POST["str"])) 
	{	
		$string 	= ExploitPatch::remove($_POST["str"]);
		$page 		= ExploitPatch::remove($_POST["page"]);

		$getUsers = $Account->getUsers($string, $page);

		echo $getUsers;
	}
	else
	{
		echo -1;
	}