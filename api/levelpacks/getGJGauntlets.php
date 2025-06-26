<?php
	chdir(dirname(__FILE__));

	include "../../core/LevelPack.php";

	$Gauntlet = new Gauntlets();

	$loadGauntlet = $Gauntlet->getData();

	exit($loadGauntlet);
?>