<h3 class="title is-5">Levels</h3>
<table class="table">
	<thead>
	<tr>
		<th>Difficulty</th>
		<th>Total</th>
		<th>Unrated</th>
		<th>Rated</th>
		<th>Featured</th>
		<th>Epic</th>
	</tr>
	</thead>
	<tbody>
<?php
	require_once __DIR__ . "/../../../core/lib/Database.php";
	require_once __DIR__ . "/../../../core/lib/Lib.php";

	$lib = new Lib();
	$new_con = new Database();
	$db = $new_con->open_connection();

	$start_time = microtime(true);

	function genLvlRow($params, $params2, $params3, $params4) {
	    $new_con = new Database();
	    $db = $new_con->open_connection();
		$query = $db->prepare("SELECT count(*) FROM levels ".$params4." ".$params2);
		$query->execute();
		$row = "<tr><td>$params3</td><td>".$query->fetchColumn()."</td>";
		$query = $db->prepare("SELECT count(*) FROM levels WHERE starStars = 0 ".$params." ".$params2);
		$query->execute();
		$row .= "<td>".$query->fetchColumn()."</td>";
		$query = $db->prepare("SELECT count(*) FROM levels WHERE starStars <> 0 ".$params." ".$params2);
		$query->execute();
		$row .= "<td>".$query->fetchColumn()."</td>";
		$query = $db->prepare("SELECT count(*) FROM levels WHERE starFeatured <> 0 ".$params." ".$params2);
		$query->execute();
		$row .= "<td>".$query->fetchColumn()."</td>";
		$query = $db->prepare("SELECT count(*) FROM levels WHERE starEpic <> 0 ".$params." ".$params2);
		$query->execute();
		$row .= "<td>".$query->fetchColumn()."</td></tr>";

		return $row;
	}

	function generateQuery($groupBy, $requirements){
		$queryString = "
			SELECT total.${groupBy}, total.amount AS total, unrated.amount AS unrated, rated.amount AS rated, featured.amount AS featured, epic.amount AS epic
			FROM(
				(SELECT ${groupBy}, count(*) AS amount FROM levels WHERE ${requirements} GROUP BY(${groupBy})) total
				JOIN
				(SELECT ${groupBy}, count(*) AS amount FROM levels WHERE ${requirements} AND starStars = 0 GROUP BY(${groupBy})) unrated
				ON total.${groupBy} = unrated.${groupBy}
				JOIN
				(SELECT ${groupBy}, count(*) AS amount FROM levels WHERE ${requirements} AND starStars <> 0 GROUP BY(${groupBy})) rated
				ON total.${groupBy} = rated.${groupBy}
				JOIN
				(SELECT ${groupBy}, count(*) AS amount FROM levels WHERE ${requirements} AND starFeatured <> 0 GROUP BY(${groupBy})) featured
				ON total.${groupBy} = featured.${groupBy}
				JOIN
				(SELECT ${groupBy}, count(*) AS amount FROM levels WHERE ${requirements} AND starEpic <> 0 GROUP BY(${groupBy})) epic
				ON total.${groupBy} = epic.${groupBy}
			) GROUP BY(total.${groupBy})
		";

		return $queryString;
	}

	function fetchQuery($db, $groupBy, $requirements) {
		$query = $db->prepare(generateQuery($groupBy, $requirements));
		$query->execute();
		
		return $query->fetchAll();
	}


	echo genLvlRow("", "", "Total", "");
	
	foreach(fetchQuery($db, 'starAuto', 'starAuto = 1') as &$row) {
		$diffName = $lib->get_difficulty(50, 1, 0);
		
		echo "
		<tr>
			<td>${diffName}</td>
			<td>${row['total']}</td>
			<td>${row['unrated']}</td>
			<td>${row['rated']}</td>
			<td>${row['featured']}</td>
			<td>${row['epic']}</td>
		</tr>
		";
	}

	foreach(fetchQuery($db, 'starDifficulty', 'starAuto = 0 AND starDemon = 0') as &$row) {
		$diffName = $lib->get_difficulty($row['starDifficulty'], 0, 0);
		
		echo "
		<tr>
			<td>${diffName}</td>
			<td>${row['total']}</td>
			<td>${row['unrated']}</td>
			<td>${row['rated']}</td>
			<td>${row['featured']}</td>
			<td>${row['epic']}</td>
		</tr>
		";
	}

	foreach(fetchQuery($db, 'starDemon', 'starDemon = 1') as &$row) {
		$diffName = $lib->get_difficulty(50, 0, 1);
		
		echo "
		<tr>
			<td>${diffName}</td>
			<td>${row['total']}</td>
			<td>${row['unrated']}</td>
			<td>${row['rated']}</td>
			<td>${row['featured']}</td>
			<td>${row['epic']}</td>
		</tr>
		";
	}
?>
	</tbody>
</table>
<h3 class="title is-5">Demons</h3>
<table class="table">
	<thead>
	<tr>
		<th>Difficulty</th>
		<th>Total</th>
		<th>Unrated</th>
		<th>Rated</th>
		<th>Featured</th>
		<th>Epic</th>
	</tr>
	</thead>
	<tbody>
<?php
	echo genLvlRow("AND", "starDemon = 1", "Total", "WHERE");
	$query = $db->prepare(generateQuery('starDemonDiff', 'starDemon = 1'));
	$query->execute();
	
	foreach($query->fetchAll() as &$row) 
	{
		$diffName = $lib->demon_filter($row['starDemonDiff']);
		
		echo "
		<tr>
			<td>${diffName['name']}</td>
			<td>${row['total']}</td>
			<td>${row['unrated']}</td>
			<td>${row['rated']}</td>
			<td>${row['featured']}</td>
			<td>${row['epic']}</td>
		</tr>
		";
	}
?>
	</tbody>
</table>

<h3 class="title is-5">Accounts</h3>
<table class="table">
	<thead>
	<tr>
		<th>Type</th>
		<th>Count</th>
	</tr>
	</thead>
	<tbody>
<?php
	$query = $db->prepare("SELECT count(*) FROM users");
	$query->execute();
	$thing = $query->fetchColumn();
	
	echo "
	<tr>
		<td>Total</td>
		<td>$thing</td>
	</tr>
	";
	
	$query = $db->prepare("SELECT count(*) FROM accounts");
	$query->execute();
	$thing = $query->fetchColumn();
	
	echo "
	<tr>
		<td>Registered</td>
		<td>$thing</td>
	</tr>
	";
	
	$sevendaysago = time() - 604800;
	$query = $db->prepare("SELECT count(*) FROM users WHERE lastPlayed > :lastPlayed");
	$query->execute([':lastPlayed' => $sevendaysago]);
	$thing = $query->fetchColumn();
	
	echo "
	<tr>
		<td>Active</td>
		<td>$thing</td>
	</tr>
	";
?>
	</tbody>
</table>

<?php
	// echo (microtime(true) - $start_time);
?>