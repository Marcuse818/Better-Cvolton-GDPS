<?php
	require_once __DIR__ . "/../../core/lib/Lib.php";
	
	require_once __DIR__ . "/../../core/lib/Database.php";
	require_once __DIR__ . "/../../core/lib/generatePass.php";
	require_once __DIR__ . "/../../core/lib/exploitPatch.php";

	$lib = new Lib();
	$new_con = new Database();
	$db = $new_con->open_connection();

	if(!empty($_POST['songlink'])) 
	{
		$result = $lib->song_reupload($_POST['songlink']);
		
		switch ($result) {
			case "-4":
				echo "This URL doesn't point to a valid audio file.";
				break;
			
			case "-2":
				echo "The download link isn't a valid URL.";
				break;
			
			default:
				echo "Song reuploaded: <b>${result}</b><hr>";
				break;
		}
	}
	else
	{
		echo '<br>
			<form method="post">
				<div class="has-addons">
					<b>Link</b> <input class="input" type="text" name="songlink"><br>
					<p class="help"><b>Direct links</b> or <b>Dropbox links</b> only accepted, <b>NO YOUTUBE LINKS</b></p>
				</div>';
		echo '<br><input class="button" type="submit" value="Add Song"></form>';
	}
?>