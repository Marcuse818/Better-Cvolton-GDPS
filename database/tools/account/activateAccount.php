<?php
    require_once __DIR__ . "/../../../core/lib/Database.php";
    require_once __DIR__ . "/../../../core/lib/generatePass.php";
    require_once __DIR__ . "/../../../core/lib/exploitPatch.php";
    
    $new_con = new Database();
    $db = $new_con->open_connection();
    
    if(!empty($_POST["userName"]) && !empty($_POST["password"]))
    {
	    $userName = ExploitPatch::remove($_POST["userName"]);
	    $password = ExploitPatch::remove($_POST["password"]);
	    
	    $pass = GeneratePass::isValidUsrname($userName, $password);
	    
	    if ($pass == -2)
	    {
		    $query = $db->prepare("UPDATE accounts SET isActive = 1 WHERE userName LIKE :userName");
		    $query->execute(['userName' => $userName]);
		    echo "Account has been succesfully activated.";
	    }
	    elseif ($pass == 1) 
	    {
		    echo "Account is already activated.";
	    }
	    else
	    {
		    echo "Invalid password or nonexistant account. <a href='/'>Try again</a>";
	    }
    }
    else
    {
	    echo '<form method="post">
		    <strong>Username</strong> <input class="input" type="text" name="userName"><br>
		    <strong>Password</strong> <input class="input" type="password" name="password"><br>';
	    echo '<br><input class="button" type="submit" value="Activate"></form>';
    }