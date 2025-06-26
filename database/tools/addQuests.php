<?php
	require_once __DIR__ . "/../../core/Main.php";

	require_once __DIR__ . "/../../core/lib/Database.php";
	require_once __DIR__ . "/../../core/lib/generatePass.php";
	require_once __DIR__ . "/../../core/lib/exploitPatch.php";

	$main = new Main();
	$new_con = new Database();
	$db = $new_con->open_connection();

	if(!empty($_POST["userName"]) && !empty($_POST["password"]) && !empty($_POST["type"]) && !empty($_POST["amount"]) && !empty($_POST["reward"]) && !empty($_POST["names"]))
	{
		$userName = ExploitPatch::remove($_POST["userName"]);
		$password = ExploitPatch::remove($_POST["password"]);
		$type = ExploitPatch::number($_POST["type"]);
		$amount = ExploitPatch::number($_POST["amount"]);
   		$reward = ExploitPatch::number($_POST["reward"]);
    	$name = ExploitPatch::remove($_POST["names"]);
		$pass = GeneratePass::isValidUsrname($userName, $password);
	
		if ($pass == 1) 
		{
			$query = $db->prepare("SELECT accountID FROM accounts WHERE userName=:userName");	
			$query->execute([':userName' => $userName]);
			$accountID = $query->fetchColumn();
		
			if($main->getRolePermission($accountID, "toolQuestsCreate") == false)
			{
				echo "This account doesn't have the permissions to access this tool. <a href='/'>Try again</a>";
			}
			else
			{
				if(!is_numeric($type) || !is_numeric($amount) || !is_numeric($reward) || $type > 3) exit("Type/Amount/Reward invalid");
			
				$query = $db->prepare("INSERT INTO quests (type, amount, reward, name) VALUES (:type,:amount,:reward,:name)");
				$query->execute([':type' => $type, ':amount' => $amount, ':reward' => $reward, ':name' => $name]);
				$query = $db->prepare("INSERT INTO modactions (type, value, timestamp, account, value2, value3, value4) VALUES ('25',:value,:timestamp,:account,:amount,:reward,:name)");
				$query->execute([':value' => $type, ':timestamp' => time(), ':account' => $accountID, ':amount' => $amount, ':reward' => $reward, ':name' => $name]);
			
				if($db->lastInsertId() < 3) exit("Successfully added Quest! It's recommended that you should add a few more."); 
				else exit("Successfully added Quest!");
			}
		}
		else
		{
        	echo "Invalid password or nonexistant account. <a href='addQuest.php'>Try again</a>";
    	}
	}
	else
	{
		echo '<form method="post"><b>Username</b> <input class="input" type="text" name="userName">
			<br><b>Password</b> <input class="input" type="password" name="password">
			<br><b>Quest Name</b> <input class="input" type="text" name="names">
			<br><b>Quest Type</b><br> 
			<div class="select">
			<select name="type">
				<option value="1">Orbs</option>
				<option value="2">Coins</option>
				<option value="3">Star</option>
			</select>
			</div>
			<br>
			<div class="has-addons">
				<b>Amount</b> <input class="input" type="number" name="amount"> 
				<p class="help">How many orbs/coins/stars you need to collect</p>
			</div>
			<br>
			<div class="has-addons">
				<b>Reward</b> <input class="input" type="number" name="reward"> 
				<p class="help">How many Diamonds you get as a reward</p>
			</div>
			<br><input class="button" type="submit" value="Create"></form>';
	}
?>
