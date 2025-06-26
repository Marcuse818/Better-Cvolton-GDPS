<h3 class="title is-5">Top leaderboard 24 house</h3>
<table class="table">
	<thead>
	<tr><th>#</th><th>UserID</th><th>UserName</th><th>Stars</th></tr>
	</thead>
	<tbody>
<?php
	require_once __DIR__ . "/../../../core/lib/Database.php";

	$new_con = new Database();
	$db = $new_con->open_connection();

	$starsgain = array();
	$time = time() - 86400;
	$x = 0;
	
	$query = $db->prepare("SELECT users.userID, SUM(actions.value) AS stars, users.userName FROM actions INNER JOIN users ON actions.account = users.userID WHERE type = '9' AND timestamp > :time AND users.isBanned = 0 GROUP BY(users.userID)");
	$query->execute([':time' => $time]);
	$result = $query->fetchAll();
	
	foreach($result as &$gain) {
		$x++;
	
		echo "<tr><td>$x</td><td>${gain['userID']}</td><td>${gain['userName']}</td><td>${gain['stars']}</td></tr>";
	}
?>
</tbody>
</table>