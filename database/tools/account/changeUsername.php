<?php
    require_once __DIR__ . "/../../../core/lib/Database.php";
    require_once __DIR__ . "/../../../core/lib/generatePass.php";
    require_once __DIR__ . "/../../../core/lib/exploitPatch.php";

    $new_con = new Database();
    $db = $new_con->open_connection();
    
	if (!empty($_POST["userName"]) && !empty($_POST["newusr"]) && !empty($_POST["password"]))
	{
    	$userName = ExploitPatch::remove($_POST["userName"]);
    	$newusr = ExploitPatch::remove($_POST["newusr"]);
    	$password = ExploitPatch::remove($_POST["password"]);
    
    	if($userName != "" && $newusr != "" && $password != "")
    	{
	    	$pass = GeneratePass::isValidUsrname($userName, $password);
	    	if ($pass == 1) 
	    	{
		    	if(strlen($newusr) > 20) exit("Username too long - 20 characters max. <a href='/'>Try again</a>");
		    
		    	$query = $db->prepare("UPDATE accounts SET username=:newusr WHERE userName=:userName");	
		    	$query->execute([':newusr' => $newusr, ':userName' => $userName]);
		    
		    	if($query->rowCount()==0) echo "Invalid password or nonexistant account. <a href='/'>Try again</a>";
		    	else echo "Username changed. <a href='/'>Go back to tools</a>";
	    	}
	    	else echo "Invalid password or nonexistant account. <a href='/'>Try again</a>";
    	}
	}
    else
    {
	    echo '<form method="post">
			<strong>Old username</strong> <input class="input" type="text" name="userName"><br>
			<strong>New username</strong> <input class="input" type="text" name="newusr"><br>
			<strong>Password</strong> <input class="input" type="password" name="password"><br>
			<br><input class="button" type="submit" value="Change">
		</form>';
    }
?>