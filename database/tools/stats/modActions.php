<?php
	require_once __DIR__ . "/../../../core/lib/Database.php";
	require_once __DIR__ . "/../../../core/lib/Lib.php";

	$new_con = new Database();
	$lib = new Lib();

	$db = $new_con->open_connection();
	$accounts = implode(",", $lib->get_accounts_with_permission("toolModactions"));
	
	if($accounts == "") echo "Error: No accounts with the 'toolModactions' permission have been found";
	else {
?>

<table class="table">
	<thead>
	<tr>
		<th>Moderator</th>
		<th>Count</th>
		<th>Levels rated</th>
		<th>Last time online</th>
	</tr>
	</thead>
	<tbody>
<?php

	if(isset($_GET["page"]) && is_numeric($_GET["page"]) && $_GET["page"] > 0) 
	{
		$page = ($_GET["page"] - 1) * 10;
		$actualPage = $_GET["page"];
	}
	else 
	{
		$page = 0;
		$actualPage = 1;
	}

	$search = (isset($_GET["search"]) && strlen($_GET["search"]) > 0 && is_string($_GET["search"])) ? $_GET["search"] : " ";

	$query = $db->prepare("SELECT accountID, userName FROM accounts WHERE accountID IN ($accounts) ORDER BY userName ASC");
	$query->execute();
	$result = $query->fetchAll();

	foreach($result as &$mod) {
		$query = $db->prepare("SELECT lastPlayed FROM users WHERE extID = :id");
		$query->execute([':id' => $mod["accountID"]]);

		$time = date("d/m/Y G:i:s", $query->fetchColumn());

		$query = $db->prepare("SELECT count(*) FROM modactions WHERE account = :id");
		$query->execute([':id' => $mod["accountID"]]);
		$actionscount = $query->fetchColumn();
		$query = $db->prepare("SELECT count(*) FROM modactions WHERE account = :id AND type = '1'");
		$query->execute([':id' => $mod["accountID"]]);
		$lvlcount = $query->fetchColumn();
	
		echo "
		<tr>
			<td>".$mod["userName"]."</td>
			<td>".$actionscount."</td>
			<td>".$lvlcount."</td>
			<td>".$time."</td>
		</tr>
		";
}

?>
	</tbody>
</table>

<h3 class="title is-3">Actions Log</h3>
<form action="<?= $_SERVER['SCRIPT_NAME'] ?>">
	<p><b>Search</b> 
		<input class="input" type="text" name="search" value="<?php if (isset($_GET["search"]) && strlen($_GET["search"]) > 0) echo $search;?>"/> 
		<br>
		<br>
		<input class="button" type="submit" value="Search" /> 
	</p> 
	<button class="button" name="page" value="<?php $i = $actualPage - 1; if($i <= 0) $i = 0; echo $i;?>">Previous page</button> 
	<button class="button" name="page" value="<?php $i = $actualPage + 1; echo $i; ?>">Next page</button>
</form>

<?php
	$query = $db->prepare("SELECT * FROM modactions ORDER BY ID DESC LIMIT 10 OFFSET $page");
	$query->execute();
	$result = $query->fetchAll();

	$query2 = $db->prepare("SELECT * FROM accounts WHERE userName = :username");
	$query2->execute([':username' => $search]);
	$result2 = $query2->fetchAll();

	foreach($result2 as &$mod) {
		if(isset($_GET["search"]) && strlen($_GET["search"]) > 0 && is_string($_GET["search"])) 
		{
			$query = $db->prepare("SELECT * FROM modactions WHERE account = :id ORDER BY ID DESC LIMIT 10 OFFSET $page");
			$query->execute([':id' => $mod["accountID"]]);
			$result = $query->fetchAll();
		}
	}

	if(isset($_GET["search"]) and $_GET["search"] > 0 and is_numeric($_GET["search"])) 
	{
		$query = $db->prepare("SELECT * FROM modactions WHERE value3 = :id ORDER BY ID DESC LIMIT 10 OFFSET $page");
		$query->execute([':id'=>$_GET["search"]]);
		$result = $query->fetchAll();
	}

	if (count($result) == 0) 
	{
		echo "Actions not found";
	}

?>
<table class="table">
	<thead>
	<tr>
		<th>Moderator</th>
		<th>Action</th>
		<th>Value</th>
		<th>Value2</th>
		<th>LevelID</th>
		<th>Time</th>
	</tr>
	</thead>
	<tbody>
<?php

	foreach($result as &$action) {
		$account = $action["account"];
		$query = $db->prepare("SELECT userName FROM accounts WHERE accountID = :id");
		$query->execute([':id'=>$account]);
		$account = $query->fetchColumn();
		$value = $action["value"];
		$value2 = $action["value2"];

		switch($action["type"]){
			case 1:
				$actionname = "Rated a level";
				break;

			case 2:
				$actionname = "Featured change";
				break;

			case 3:
				$actionname = "Coins verification state";
				break;

			case 4:
				$actionname = "Epic change";
				break;

			case 5:
				$actionname = "Set as daily feature";
				if(is_numeric($value2)) $value2 = date("d/m/Y G:i:s", $value2);
				break;

			case 6:
				$actionname = "Deleted a level";
				break;

			case 7:
				$actionname = "Creator change";
				break;

			case 8:
				$actionname = "Renamed a level";
				break;

			case 9:
				$actionname = "Changed level password";
				break;

			case 10:
				$actionname = "Changed demon difficulty";
				break;

			case 11:
				$actionname = "Shared CP";
				break;

			case 12:
				$actionname = "Changed level publicity";
				break;

			case 13:
				$actionname = "Changed level description";
				break;

			case 15:
				$actionname = "Un/banned a user";
				break;

			default:
				$actionname = $action["type"];
				break;
		}

		if($action["type"] == 2 || $action["type"] == 3 || $action["type"] == 4 || $action["type"] == 15)
		{
			$value = ($action["value"] == 1) ? "True" : "False";
		}
		if($action["type"] == 5 OR $action["type"] == 6) $value = "";
	
		$time = date("d/m/Y G:i:s", $action["timestamp"]);

		if($action["type"] == 5 && $action["value2"] > time())
		{
			echo "<tr>
				<td>".$account."</td>
				<td>".$actionname."</td>
				<td>".$value."</td>
				<td>".$value2."</td>
				<td>future</td>
				<td>".$time."</td>
			</tr>
			";
	} 
	else 
	{
		echo "<tr>
			<td>".$account."</td>
			<td>".$actionname."</td>
			<td>".$value."</td
			><td>".$value2."</td>
			<td>".$action["value3"]."</td
			><td>".$time."</td>
		</tr>";
	}
}
?>
	</tbody>
</table>

<?php } ?>