<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Profile.php";
	require_once "../../core/lib/GJPCheck.php";

	$Request 	= new Request();
    
    $accountID 	= GJPCheck::getAccountIDOrDie();
    
	$requestAccess = $Request->request($accountID);

	echo $requestAccess;