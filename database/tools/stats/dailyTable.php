<table class="table">
	<thead>
		<tr>
			<th>#</th>
			<th>ID</th>
			<th>Name</th>
			<th>Creator</th>
			<th>Time</th>
		</tr>
	</thead>
<?php
	require_once __DIR__ . "/../../../core/lib/Database.php";

	$new_con = new Database();
	$db = $new_con->open_connection();

	$x = 1;
	$query = $db->prepare("SELECT dailyfeatures.feaID, dailyfeatures.levelID, dailyfeatures.timestamp, levels.levelName, users.userName FROM dailyfeatures INNER JOIN levels ON dailyfeatures.levelID = levels.levelID INNER JOIN users ON levels.userID = users.userID  WHERE timestamp < :time ORDER BY feaID DESC");
	$query->execute([':time' => time()]);
	$result = $query->fetchAll();

	foreach($result as &$daily) {
		$feaID = $daily["feaID"];
		$levelID = $daily["levelID"];
		$time = date("d/m/Y H:i", $daily["timestamp"]);
		$levelName = $daily["levelName"];
		$creator = $daily["userName"];
		
		echo "
		<tbody>	
			<tr>
				<td>$feaID</td>
				<td>$levelID</td>";
		echo "<td>$levelName</td>";
		echo "<td>$creator</td>";
		echo "<td>$time</td>
			</tr>
		</tbody>";
	}
?>
</table>