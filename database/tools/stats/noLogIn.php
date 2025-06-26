<table class="table">
	<thead>
	<tr><th>#</th><th>ID</th><th>Name</th><th>Registration date</th></tr>
	</thead>
	<tbody>
<?php
	set_time_limit(0);
	ob_flush();
	flush();

	require_once __DIR__ . "/../../../core/lib/Database.php";

	$new_con = new Database();
	$db = $new_con->open_connection();

	$x = 1;
	$query = $db->prepare("SELECT accountID, userName, registerDate FROM accounts");
	$query->execute();
	$result = $query->fetchAll();

	foreach($result as &$account) {
		$query = $db->prepare("SELECT count(*) FROM users WHERE extID = :accountID");
		$query->execute([':accountID' => $account["accountID"]]);
		
		if($query->fetchColumn() == 0)
		{
			$register = date("d/m/Y G:i:s", $account["registerDate"]);
			
			echo "<tr><td>$x</td><td>".$account["accountID"] . "</td><td>" . $account["userName"] . "</td><td>$register</td>";
			ob_flush();
			flush();
			
			$time = time() - 2592000;
			
			if($account["registerDate"] < $time) echo "<td>1</td>";
			
			echo "</tr>";
			$x++;
		}
	}
?>
	</tbody>
</table>