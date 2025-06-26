<?php
    chdir(dirname(__FILE__));
    set_time_limit(0);
    
    ini_set("memory_limit", "128M");
    ini_set("post_max_size", "50M");
    ini_set("upload_max_filesize", "50M");
    
    require_once "../../core/lib/Database.php";
    
    require_once "../../core/lib/generatePass.php";
    require_once "../../core/lib/exploitPatch.php";
    
    $new_con = new Database ();
    $db = $new_con->open_connection();
  
    $userName = ExploitPatch::remove($_POST["userName"]);
    $password = !empty($_POST["password"]) ? $_POST["password"] : "";
    $saveData = ExploitPatch::remove($_POST["saveData"]);

    if(empty($_POST["accountID"])) 
    {
	    $query = $db->prepare("SELECT accountID FROM accounts WHERE userName = :userName");
	    $query->execute([':userName' => $userName]);
	    $accountID = $query->fetchColumn();
    } 
    else 
    {
	    $accountID = ExploitPatch::remove($_POST["accountID"]);
    }

    if(!is_numeric($accountID)) exit(-1);

    $pass = 0;
    
    if(!empty($_POST["password"])) $pass = GeneratePass::isValid($accountID, $_POST["password"]);
    elseif(!empty($_POST["gjp2"])) $pass = GeneratePass::isGJP2Valid($accountID, $_POST["gjp2"]);
    
    if ($pass == 1) 
    {
	    $saveDataArr = explode(";", $saveData); //splitting ccgamemanager and cclocallevels
	    $saveData = str_replace("-", "+", $saveDataArr[0]); //decoding
	    $saveData = str_replace("_", "/", $saveData);
	    $saveData = base64_decode($saveData);
	    $saveData = gzdecode($saveData);
	    
	    $orbs = explode("</s><k>14</k><s>", $saveData)[1];
	    $orbs = explode("</s>", $orbs)[0];
	    $lvls = explode("<k>GS_value</k>", $saveData)[1];
	    $lvls = explode("</s><k>4</k><s>", $lvls)[1];
	    $lvls = explode("</s>", $lvls)[0];
	    $protected_key_encoded = "";

	    $saveData = str_replace("<k>GJA_002</k><s>".$password."</s>", "<k>GJA_002</k><s>password</s>", $saveData); //replacing pass
	    $saveData = gzencode($saveData); 
	    $saveData = base64_encode($saveData);
	    $saveData = str_replace("+", "-", $saveData);
	    $saveData = str_replace("/", "_", $saveData);
	    $saveData = $saveData . ";" . $saveDataArr[1]; 

	    file_put_contents("../data/accounts/$accountID", $saveData);
	    file_put_contents("../data/accounts/keys/$accountID","");
	    
	    $query = $db->prepare("SELECT extID FROM users WHERE userName = :userName LIMIT 1");
	    $query->execute([':userName' => $userName]);
	    $result = $query->fetchAll();
	    $result = $result[0];
	    
	    $extID = $result["extID"];
	    
	    $query = $db->prepare("UPDATE `users` SET `orbs` = :orbs, `completedLvls` = :lvls WHERE extID = :extID");
	    $query->execute([':orbs' => $orbs, ':extID' => $extID, ':lvls' => $lvls]);
	
	    echo 1;
    }
    else
    {
	    echo -1;
    }
?>