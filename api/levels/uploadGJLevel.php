<?php
	chdir(dirname(__FILE__));

	require_once "../../core/Main.php";
	require_once "../../core/Level.php";

	require_once '../../core/lib/GJPCheck.php';
	require_once '../../core/lib/exploitPatch.php';
	require_once '../../core/lib/Lib.php';
	
	require_once '../../core/data/LevelUploadDTO.php';

	$Main 		= new Main();
	$Level 		= new Level();
	$Lib		= new Lib();

	$data = LevelUploadDTO::request($_POST, $_SERVER, $Main);

	$Level->gameVersion = $data->gameVersion;
	$Level->binaryVersion = $data->binaryVersion;

	$uploadLevel = $Level->upload($data);

	exit((string) $uploadLevel);
?>
