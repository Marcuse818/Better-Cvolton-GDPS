<?php
	chdir(dirname(__FILE__));

	include "../../core/GauntletPack.php";

	$Gauntlet = new GauntletPack();

	$loadGauntlet = $Gauntlet->get_data();

	exit($loadGauntlet);
?>