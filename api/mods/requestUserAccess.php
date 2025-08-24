<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Request.php";
	require_once "../../core/lib/GJPCheck.php";

	$Request 	= new Request();
    
    $accountID 	= GJPCheck::getAccountIDOrDie();
    
	$requestAccess = $Request->request($accountID);

	exit($requestAccess);
?>