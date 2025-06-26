<?php
	require_once __DIR__ . "/../../core/Main.php";
	
	require_once __DIR__ . "/../../core/lib/Database.php";
	require_once __DIR__ . "/../../core/lib/generatePass.php";
	require_once __DIR__ . "/../../core/lib/exploitPatch.php";

	$main = new Main();
	$new_con = new Database();
	$db = $new_con->open_connection();
	
	if(!empty($_POST["userName"]) AND !empty($_POST["password"]) AND !empty($_POST["userID"]))
	{
		$userName = ExploitPatch::remove($_POST["userName"]);
		$password = ExploitPatch::remove($_POST["password"]);
		$userID = ExploitPatch::remove($_POST["userID"]);
		$pass = GeneratePass::isValidUsrname($userName, $password);
		
		if ($pass == 1) 
		{
			$query = $db->prepare("SELECT accountID FROM accounts WHERE userName=:userName");	
			$query->execute([':userName' => $userName]);
			$accountID = $query->fetchColumn();

			if($main->getRolePermission($accountID, "toolLeaderboardsban"))
			{
				if(!is_numeric($userID)) exit("Invalid userID");
			
				$query = $db->prepare("UPDATE users SET isBanned = 0 WHERE userID = :id");
				$query->execute([':id' => $userID]);

				$query = $db->prepare("INSERT INTO modactions  (type, value, value2, timestamp, account) VALUES ('15',:userID, '0',  :timestamp,:account)");
				$query->execute([':userID' => $userID, ':timestamp' => time(), ':account' => $accountID]);
			
				if($query->rowCount() != 0)
				{
					echo "Unbanned succesfully.";
				}
				else
				{
					echo "Unban failed.";
				}
			}
			else
			{
				exit("You do not have the permission to do this action. <a href='/'>Try again</a>");
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
			<b>Your Username</b> <input class="input" type="text" name="userName">
			<br><b>Your Password</b> <input class="input" type="password" name="password">
			<br><b>Target UserID</b> <input class="input" type="text" name="userID">
			<br>
			<br><input class="button" type="submit" value="Unban"></form>';
	}
?>