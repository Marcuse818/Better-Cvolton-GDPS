<?php
	require_once __DIR__ . "/../../../core/Main.php";

	
	require_once __DIR__ . "/../../../core/lib/Lib.php";
	require_once __DIR__ . "/../../../core/lib/Database.php";
	require_once __DIR__ . "/../../../core/lib/generatePass.php";
	require_once __DIR__ . "/../../../core/lib/exploitPatch.php";

	$main = new Main();
	$new_con = new Database();
	$lib = new Lib();
	$db = $new_con->open_connection();

	if(!empty($_POST["userName"]) && !empty($_POST["password"]))
	{
		$userName = ExploitPatch::remove($_POST["userName"]);
		$password = ExploitPatch::remove($_POST["password"]);
		$pass = GeneratePass::isValidUsrname($userName, $password);
		if ($pass == 1) 
		{
			$query = $db->prepare("SELECT accountID FROM accounts WHERE userName = :userName");	
			$query->execute([':userName' => $userName]);
			$accountID = $query->fetchColumn();

			if($query->rowCount() == 0)
			{
				echo "Invalid account/password. <a href='/'>Try again.</a>";
			}
			else if($main->getRolePermission($accountID, "toolSuggestlist"))
			{
				$accountID = $query->fetchColumn();
				$query = $db->prepare("SELECT accountID, levelID, difficulty, stars, featured, state, auto, demon, timestamp FROM sendLevel ORDER BY timestamp DESC");
				$query->execute();
				$result = $query->fetchAll();
			
				echo '<table class="table">
    					<thead>
	 					<tr><th>Time</th><th>Send by</th><th>Level ID</th><th>Difficulty</th><th>Stars</th><th>Featured</th></tr>
       					</thead>
	    				<tbody>';
				
				foreach($result as &$send) {
					// $difficulty = $main->get_difficulty($send['stars'], "", "stars");
					
					echo "<tr>
     						<td>" . date("d/m/Y G:i", $send["timestamp"]) . "</td>
	   					<td>" . $lib->getAccountName($send["accountID"]). "(Account ID: " . $accountID["accountID"] . ")</td>
	 					<td>" . htmlspecialchars($send["levelID"], ENT_QUOTES) . "</td>
       						<td>" . htmlspecialchars($send["difficulty"], ENT_QUOTES) . "</td>
	     					<td>" . htmlspecialchars($send["stars"], ENT_QUOTES) . "</td>
	   					<td>" . htmlspecialchars($send["featured"], ENT_QUOTES) . "</td>
	   				</tr>";
				}
				
				echo "</tbody></table>";
			}
			else
			{
				echo "You don't have permissions to view content on this page. <a href='/'>Try again.</a>\n";
			}
		}
		else
		{
			echo "Invalid account/password. <a href='/'>Try again.</a>";
		}
	}
	else
	{
		echo '<form method="post">
			<b>Username</b> <input class="input" type="text" name="userName"> <br>
			Password: <input class="input" type="password" name="password"><br>
			<br><input class="button" type="submit" value="Show suggested levels"></form>';
	}
?>
