<?php
	require_once __DIR__ . "/../../../core/lib/Database.php";

	$new_con = new Database();
	$db = $new_con->open_connection();

	$query = $db->prepare("SELECT roleID, roleName FROM roles WHERE priority > 0 ORDER BY priority DESC");
	$query->execute();
	$result = $query->fetchAll();
	
	foreach ($result as $role) {
		echo "<h2>" . $role['roleName'] . "</h2>";
		
		$query2 = $db->prepare("SELECT users.userName, users.lastPlayed FROM roleassign INNER JOIN users ON roleassign.accountID = users.extID WHERE roleassign.roleID = :roleID");
		$query2->execute([':roleID' => $role["roleID"]]);
		$account = $query2->fetchAll();
		
		echo '<table class="table"><thead><tr><th>User</th><th>Last Online</th></tr></thead><tbody>';
		
		foreach ($account as $user) {
			$time = date("d/m/Y G:i:s", $user["lastPlayed"]);
			$username = htmlspecialchars($user["userName"], ENT_QUOTES);
			
			echo "<tr><td>" . $username . "</td><td>$time</td></tr>";
		}
		
		echo "</tbody></table>";
	}
?>