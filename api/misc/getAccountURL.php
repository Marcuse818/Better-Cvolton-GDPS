<?php
    chdir(dirname(__FILE__));
    
    require_once "../../core/Misc.php";

    $Misc = new Misc();

    echo $Misc->getUrl();