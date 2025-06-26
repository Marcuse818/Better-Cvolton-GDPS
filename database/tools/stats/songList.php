<form action="/" method="post">
	<b>Search</b> 
	<input class="input" type="text" name="name" placeholder="Enter field">
	<br><b>Search Type</b> 
	<br>
	<div class="select">
		<select name="type">
			<option value="1">Song Name</option>
			<option value="2">Song Author</option>
		</select>
	</div>
	<br>
	<br>
	<input class="button" type="submit" value="Search">
</form>
<table class="table">
	<thead>
	<tr>
		<th>ID</th>
		<th>Song Name</th>
		<th>Song Author</th>
		<th>Size</th>
	</tr>
	</thead>
	<tbody>
	<?php
		require_once __DIR__ . "/../../../core/lib/Database.php";
		require_once __DIR__ . "/../../../core/lib/exploitPatch.php";
	
		$new_con = new Database();
		$db = $new_con->open_connection();
		
		$type = (isset($_POST[""])) ? ExploitPatch::number($_POST['type']) : 2;

		switch($type) {
			case 1:
				$searchType = "name";
				break;

			case 2:
				$searchType = "authorName";
				break;

			default:
				$searchType = "name";
				break;
		}

		$name = (isset($_POST["name"])) ? ExploitPatch::remove($_POST['name']) : 'reupload';

		$query = $db->prepare("SELECT ID,name,authorName,size FROM songs WHERE " . $searchType . " LIKE CONCAT('%', :name, '%') ORDER BY ID DESC LIMIT 5000");
		$query->execute([':name' => $name]);
		$result = $query->fetchAll();

		foreach($result as &$song) {
			echo "<tr><td>" . $song["ID"] . "</td><td>" . htmlspecialchars($song["name"], ENT_QUOTES) . "</td><td>" . $song['authorName'] . "</td><td>" . $song['size'] . "mb</td></tr>";
		}
	?>
	</tbody>
</table>
