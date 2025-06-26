<?php
	require_once __DIR__ . "/../../core/Main.php";
	
	require_once __DIR__ . "/../../core/lib/Database.php";
	require_once __DIR__ . "/../../core/lib/generatePass.php";
	require_once __DIR__ . "/../../core/lib/exploitPatch.php";

	$main = new Main();
	$new_con = new Database();
	$db = $new_con->open_connection();

	if(!empty($_POST["userName"]) && !empty($_POST["password"]) && !empty($_POST["packName"]) && !empty($_POST["levels"]) && !empty($_POST["stars"]) && !empty($_POST["coins"]) && !empty($_POST["color"]))
	{
		$userName = ExploitPatch::remove($_POST["userName"]);
		$password = ExploitPatch::remove($_POST["password"]);
		$packName = ExploitPatch::remove($_POST["packName"]);
		$levels = ExploitPatch::remove($_POST["levels"]);
		$stars = ExploitPatch::remove($_POST["stars"]);
		$coins = ExploitPatch::remove($_POST["coins"]);
		$color = preg_replace('/[^0-9A-Fa-f]/', '', $_POST['color']);
		$pass = GeneratePass::isValidUsrname($userName, $password);

		if ($pass == 1) 
		{
			$query = $db->prepare("SELECT accountID FROM accounts WHERE userName=:userName");	
			$query->execute([':userName' => $userName]);
			$accountID = $query->fetchColumn();

			if($main->getRolePermission($accountID, "toolPackcreate") == false)
			{
				echo "This account doesn't have the permissions to access this tool. <a href='/'>Try again</a>";
			}
			else
			{
				if(!is_numeric($stars) || !is_numeric($coins) || $stars > 10 || $coins > 2) exit("Invalid stars/coins value");
				if(strlen($color) != 6) exit("Unknown color value");

				$rgb = hexdec(substr($color,0,2)).
					",".hexdec(substr($color,2,2)).
					",".hexdec(substr($color,4,2));
				$lvlsarray = explode(",", $levels);

				foreach($lvlsarray AS &$level) {
					if(!is_numeric($level)) exit("$level isn't a number");
			
					$query = $db->prepare("SELECT levelName FROM levels WHERE levelID=:levelID");	
					$query->execute([':levelID' => $level]);
					
					if($query->rowCount() == 0) exit("Level #$level doesn't exist.");

					$levelName = $query->fetchColumn();
					$levelstring .= $levelName . ", ";
				}
				
				$levelstring = substr($levelstring,0,-2);
				$difficulty = 0;

				$pack_difficulty = $main->get_difficulty($stars, "", "stars");
				$difficulty = $pack_difficulty["difficulty"] / 10;
					
				$query = $db->prepare("INSERT INTO mappacks (name, levels, stars, coins, difficulty, rgbcolors) VALUES (:name, :levels, :stars, :coins, :difficulty, :rgbcolors)");
				$query->execute([':name' => $packName, ':levels' => $levels, ':stars' => $stars, ':coins' => $coins, ':difficulty' => $difficulty, ':rgbcolors' => $rgb]);
				
				$query = $db->prepare("INSERT INTO modactions (type, value, timestamp, account, value2, value3, value4, value7) VALUES ('11', :value, :timestamp, :account, :levels, :stars, :coins, :rgb)");
				$query->execute([':value' => $packName, ':timestamp' => time(), ':account' => $accountID, ':levels' => $levels, ':stars' => $stars, ':coins' => $coins, ':rgb' => $rgb]);
			
				echo "AccountID: $accountID <br>
					Pack Name: $packName <br>
					Levels: $levelstring ($levels)<br>
					Difficulty: ".$pack_difficulty['diffname']." (".$difficulty.")"."<br>
					Stars: $stars <br>
					Coins: $coins <br>
					RGB Color: $rgb";
			}
		}
		else
		{
			echo "Invalid password or nonexistant account. <a href='/'>Try again</a>";
		}
	} 
	else
	{
		echo '<form method="post"><b>Username</b> <input class="input" type="text" name="userName">
			<br><b>Password</b> <input class="input" type="password" name="password">
			<br><b>Pack Name</b> <input class="input" type="text" name="packName">
			<br>
			<div class="has-addons">
				<b>Level IDs</b> <input class="input" type="text" name="levels">
				<p class="help">separate by commas</p>
			</div>
			<div class="has-addons">
				<br><b>Stars</b> <input class="input" type="text" name="stars">
				<p class="help">max 10</p>
			</div>
			<div class="has-addons">
				<br><b>Coins</b> <input class="input" type="text" name="coins">
				<p class="help">max 2</p>
			</div>
			<br><b>Color</b> <input class="input" type="color" name="color" value="#ffffff">
			<br>
			<br><input class="button" type="submit" value="Create"></form>';
	}
?>
