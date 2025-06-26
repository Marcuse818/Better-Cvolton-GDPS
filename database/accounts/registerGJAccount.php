<?php
    require_once "../../config/security.php";
    
    require_once "../../core/lib/Database.php";
    require_once "../../core/lib/exploitPatch.php";
    require_once "../../core/lib/generatePass.php";
	require_once "../../core/lib/Lib.php";
    
    $new_con = new Database();
    $db = $new_con->open_connection();
	$lib = new Lib();
    
    if(!isset(SecurityConfig::$preactivateAccounts)) SecurityConfig::$preactivateAccounts = true;

    if($_POST["userName"] != "")
    {
	    $userName = ExploitPatch::remove($_POST["userName"]);
	    $password = ExploitPatch::remove($_POST["password"]);
	    $email = ExploitPatch::remove($_POST["email"]);
	    $secret = "";
	    
	    if (strlen($userName) > 20) exit(-4);
		// if ($lib->is_ascii_string($userName)) exit(-1);
	    
	    $query2 = $db->prepare("SELECT count(*) FROM accounts WHERE userName LIKE :userName");
	    $query2->execute([':userName' => $userName]);
	    $regusrs = $query2->fetchColumn();
	    
	    if ($regusrs > 0) 
	    {
		    echo -2;
	    }
	    else
	    {
		    $hashpass = password_hash($password, PASSWORD_DEFAULT);
		    $gjp2 = GeneratePass::GJP2hash($password);
		    
		    $query = $db->prepare("INSERT INTO accounts (userName, password, email, registerDate, isActive, gjp2) VALUES (:userName, :password, :email, :time, :isActive, :gjp)");
		    $query->execute([':userName' => $userName, ':password' => $hashpass, ':email' => $email, ':time' => time(), ':isActive' => SecurityConfig::$preactivateAccounts ? 1 : 0, ':gjp' => $gjp2]);
		    
		    echo "1";
	    }
    }
?>
