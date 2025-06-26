<?php
    chdir(dirname(__FILE__));
    
    require_once "../../core/lib/Database.php";
    require_once "../../core/lib/generatePass.php";
    require_once "../../core/lib/exploitPatch.php";
    
    $new_con = new Database();
    $db = $new_con->open_connection();
    
    $password = !empty($_POST["password"]) ? $_POST["password"] : "";

    if(empty($_POST["accountID"])) 
    {
	    $userName = ExploitPatch::remove($_POST["userName"]);
	    $query = $db->prepare("SELECT accountID FROM accounts WHERE userName = :userName");
	    $query->execute([':userName' => $userName]);
	    $accountID = $query->fetchColumn();
    } 
    else 
    {
	    $accountID = ExploitPatch::remove($_POST["accountID"]);
    }

    $pass = 0;
    
    if(!empty($_POST["password"])) $pass = GeneratePass::isValid($accountID, $_POST["password"]);
    elseif(!empty($_POST["gjp2"])) $pass = GeneratePass::isGJP2Valid($accountID, $_POST["gjp2"]);
    
    if ($pass == 1) 
    {
	    if(!is_numeric($accountID) || !file_exists("../data/accounts/$accountID"))
	    {
		    exit(-1);
	    }
	    else
	    {
		    $saveData = file_get_contents("../data/accounts/$accountID");
	    }
	    
	    echo $saveData.";21;30;a;a";
    }
    else
    {
	    echo -2;
    }
?>