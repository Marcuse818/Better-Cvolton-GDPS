<?php
    chdir(dirname(__FILE__));
    require_once "../../core/Main.php";
    require_once "../../core/Communication.php";
    
    require_once "../../core/lib/GJPCheck.php";
    require_once "../../core/lib/exploitPatch.php";

    $Main         = new Main();
    $Message      = new Message();

    if (isset($_POST["secret"]))
    {
        // ids
        $accountID          = GJPCheck::getAccountIDOrDie();
        $userID             = $Main->get_user_id($accountID);
        $toAccountID        = ExploitPatch::number($_POST["toAccountID"]);
        
        // message 
        $subject             = ExploitPatch::remove($_POST["subject"]);
        $body                = ExploitPatch::remove($_POST["body"]);
        
        $secret              = ExploitPatch::remove($_POST["secret"]);
        $gameVersion         = ExploitPatch::remove($_POST["gameVersion"]);
        $binaryVersion       = ExploitPatch::remove($_POST["binaryVersion"]);

        $uploadMessage = $Message->upload($accountID, $userID, 0, $toAccountID, '', '', $subject, $body, $secret);

        exit($uploadMessage);
    }
    
    exit("-1");
?>