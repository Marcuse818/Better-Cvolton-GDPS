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
		
		if ($pass == 1) 
		{
			$query = $db->prepare("SELECT accountID FROM accounts WHERE userName=:userName");	
			$query->execute([':userName' => $userName]);
			
			if($query->rowCount() == 0)
			{
				echo "Invalid password or nonexistant account. <a href='/'>Try again</a>";
			}
			else
			{
				$accountID = $query->fetchColumn();
				$query = $db->prepare("SELECT levelID, levelName FROM levels WHERE extID=:extID AND unlisted=1");	
				$query->execute([':extID' => $accountID]);
				$result = $query->fetchAll();
				
				echo '<table class="table"><thead><tr><th>ID</th><th>Name</th></tr></thead><tbody>';
				
				foreach($result as &$level) echo "<tr><td>".$level["levelID"]."</td><td>".$level["levelName"]."</td></tr>";
					
				echo "</tbody></table>";
			}
		}
		else
		{
			echo "Invalid password or nonexistant account. <a href='/'>Try again</a>";
		}
	}
	else
	{
		echo '<form method="post">
			<b>Username</b> <input class="input" type="text" name="userName"> <br>
			<b>Password</b> <input class="input" type="password" name="password"><br>
			<br><input class="button" type="submit" value="Show Unlisted Levels"></form>';
	}
?>