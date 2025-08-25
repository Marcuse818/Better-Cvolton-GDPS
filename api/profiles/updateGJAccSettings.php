<?php
    chdir(dirname(__FILE__));

    require_once "../../core/Profile.php";

    require_once "../../core/lib/GJPCheck.php";
    require_once "../../core/lib/exploitPatch.php";

    $Account    = new Account();
    
    $accountID            = GJPCheck::getAccountIDOrDie();
    $privateMessage       = ExploitPatch::remove($_POST["mS"]);
    $privateFriend        = ExploitPatch::remove($_POST["frS"]);
    $privateHistory      = ExploitPatch::remove($_POST["cS"]);
    $youtube              = ExploitPatch::remove($_POST["yt"]);
    $twitter              = ExploitPatch::remove($_POST["twitter"]);
    $twitch               = ExploitPatch::remove($_POST["twitch"]);

    $updateAccountSettings = $Account->update($accountID, $privateMessage, $privateFriend, $privateHistory, $youtube, $twitch, $twitter);

    exit($updateAccountSettings);
?>