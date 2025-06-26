<h3 class="title is-5">Map pack</h3>
<table class="table">
	<thead>
		<tr><th>#</th><th>ID</th><th>Map Pack</th><th>Stars</th><th>Coins</th><th>Levels</th></tr>
	</thead>
	<tbody>
<?php
	require_once __DIR__ . "/../../../core/lib/Database.php";

	$new_con = new Database();
	$db = $new_con->open_connection();
	
	$x = 1;
	$query = $db->prepare("SELECT * FROM mappacks ORDER BY ID ASC");
	$query->execute();
	$result = $query->fetchAll();
	
	foreach($result as &$pack) {
		$lvlarray = explode(",", $pack["levels"]);
		
		echo "<tr><td>$x</td><td>".$pack["ID"]."</td><td>".htmlspecialchars($pack["name"],ENT_QUOTES)."</td><td>".$pack["stars"]."</td><td>".$pack["coins"]."</td><td>";
		
		$x++;
		
		foreach($lvlarray as &$lvl) {
			echo $lvl . " - ";

			$query = $db->prepare("SELECT levelName FROM levels WHERE levelID = :levelID");
			$query->execute([':levelID' => $lvl]);
			$levelName = $query->fetchColumn();
			
			echo $levelName . ", ";
		}
		echo "</td></tr>";
	}
/*
	GAUNTLETS
*/
?>
	</tbody>
</table>
<h3 class="title is-5">Gauntlets</h3>
<table class="table">
	<thead>
	<tr><th>#</th><th>Name</th><th>Level 1</th><th>Level 2</th><th>Level 3</th><th>Level 4</th><th>Level 5</th></tr>
	</thead>
	<tbody>
<?php
	require_once __DIR__ . "/../../../core/lib/Database.php";

	$new_con = new Database();
	$db = $new_con->open_connection();

	$query = $db->prepare("SELECT * FROM gauntlets ORDER BY ID ASC");
	$query->execute();
	$result = $query->fetchAll();

	foreach($result as &$gauntlet) {
		$gauntletname = "Unknown";
		switch($gauntlet["ID"]) {
			case 1:
				$gauntletname = "Fire";
				break;

			case 2:
				$gauntletname = "Ice";
				break;
				
			case 3:
				$gauntletname = "Poison";
				break;

			case 4:
				$gauntletname = "Shadow";
				break;

			case 5:
				$gauntletname = "Lava";
				break;

			case 6:
				$gauntletname = "Bonus";
				break;

			case 7:
				$gauntletname = "Chaos";
				break;

			case 8:
				$gauntletname = "Demon";
				break;

			case 9:
				$gauntletname = "Time";
				break;

			case 10:
				$gauntletname = "Crystal";
				break;
				
			case 11:
				$gauntletname = "Magic";
				break;

			case 12:
				$gauntletname = "Spike";
				break;

			case 13:
				$gauntletname = "Monster";
				break;

			case 14:
				$gauntletname = "Doom";
				break;

			case 15:
				$gauntletname = "Death";
				break;
		}

		echo "<tr><td>".$gauntlet["ID"]."</td><td>".$gauntletname."</td>";

		for ($x = 1; $x < 6; $x++) {
			echo "<td>";

			$lvl = $gauntlet["level".$x];

			echo $lvl . " - ";

			$query = $db->prepare("SELECT levelName FROM levels WHERE levelID = :levelID");
			$query->execute([':levelID' => $lvl]);
			$levelName = $query->fetchColumn();

			echo "$levelName</td>";
		}
		
		echo "</tr>";
	}
/*
	GAUNTLETS
*/
?>
	</tbody>	
</table>