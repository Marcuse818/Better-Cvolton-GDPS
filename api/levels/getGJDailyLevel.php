<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Level.php";

	$Level = new Level();

	$type = !empty($_POST['weekly']) ? $_POST['weekly'] : 0;

	$dailyLevel = $Level->getDaily($type);

	echo $dailyLevel;