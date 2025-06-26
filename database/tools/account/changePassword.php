<?php
    require_once __DIR__ . "/../../../config/security.php";
    
    require_once __DIR__ . "/../../../core/lib/Database.php";
    require_once __DIR__ . "/../../../core/lib/generatePass.php";
    require_once __DIR__ . "/../../../core/lib/exploitPatch.php";
    
    $new_con = new Database();
    $db = $new_con->open_connection();
    
    if (!empty($_POST["userName"]) && !empty($_POST["oldpassword"]) && !empty($_POST["newpassword"])) 
    {
        $userName = ExploitPatch::remove($_POST["userName"]);
        $oldpass = $_POST["oldpassword"];
        $newpass = $_POST["newpassword"];
        $salt = "";
    
        if($userName != "" && $newpass != "" && $oldpass != "")
        {
            $pass = GeneratePass::isValidUsrname($userName, $oldpass);
        
            if ($pass == 1) 
            {
	            $passhash = password_hash($newpass, PASSWORD_DEFAULT);
	            $query = $db->prepare("UPDATE accounts SET password = :password, salt = :salt WHERE userName = :userName");	
	            $query->execute([':password' => $passhash, ':userName' => $userName, ':salt' => $salt]);
	        
	            GeneratePass::assignGJP2($accid, $newpass);
	        
	            $query = $db->prepare("SELECT accountID FROM accounts WHERE userName = :userName");	
	            $query->execute([':userName' => $userName]);
	            $accountID = $query->fetchColumn();
	        
	            $saveData = file_get_contents("../../data/accounts/$accountID");

                echo "Password changed. <a href='/'>Go back to tools</a>";
            }
            else
            {
	            echo "Invalid old password or nonexistent account. <a href='/'>Try again</a>";
            }
        }
    }
    else
    {
	    echo '<form method="post">
            <strong>Username</strong> <input class="input" type="text" name="userName"><br>
            <strong>Old password</strong> <input class="input" type="password" name="oldpassword"><br>
            <strong>New password</strong> <input class="input" type="password" name="newpassword"><br>
            <br><input class="button" type="submit" value="Change"><br>
        </form>';
    }
?>