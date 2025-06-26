<table class="tabel">
	<thead>
		<tr><th>LevelID</th><th>Level Name</th><th>Reported</th></tr>
	</thead>
	<tbody>
<?php
	require_once __DIR__ . "/../../../core/lib/Database.php";

	$new_con = new Database();
	$db = $new_con->open_connection();

	$array = array();
	$query = $db->prepare("SELECT levels.levelID, levels.levelName, count(*) AS reportsCount FROM reports INNER JOIN levels ON reports.levelID = levels.levelID GROUP BY levels.levelID ORDER BY reportsCount DESC");
	$query->execute();
	$result = $query->fetchAll();

	foreach($result as &$report) {
		$levelName = htmlspecialchars($report['levelName'], ENT_QUOTES);
		
		echo "<tr><td>${report['levelID']}</td><td>$levelName</td><td>${report['reportsCount']} times</td></tr>";
	}
?>
	</tbody>
</table>