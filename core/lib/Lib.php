<?php
	require_once __DIR__."/Database.php";

	class Lib {
		private $connection;

		public function __construct() {
			$new_con = new Database();

			$this->connection = $new_con->open_connection();
		}
		
		public function get_difficulty($diff, $auto, $demon) {
			if($auto != 0) return "Auto";
			if($demon != 0) return "Demon";
			
			switch($diff) {
				case 0:
					return "N/A";
					
				case 10:
					return "Easy";
					
				case 20:
					return "Normal";
					
				case 30:
					return "Hard";
					
				case 40:
					return "Harder";
					
				case 50:
					return "Insane";
					
				default:
					return "Unknown";
		}
	}

	public function demon_filter($demon_rating) {
		switch($demon_rating) 
		{
			case 1:
				$rating["demon"] = 3;
				$rating["name"] = "Easy";
				break;

			case 2:
				$rating["demon"] = 4;
				$rating["name"] = "Medium";
				break;

			case 3:
				$rating["demon"] = 0;
				$rating["name"]  = "Hard";
				break;

			case 4:
				$rating["demon"] = 5;
				$this->demon_name = "Insane";

			case 5:
				$rating["demon"] = 6;
				$rating["name"] = "Extreme";
				break;
		}

		return $rating;
	}
	
	function make_time($delta) {
		$interval = time() - $delta;

		if ($interval < 60) return round($interval)." seconds";
		if ($interval < 3600) return round($interval / 60)." minutes";
		if ($interval < 86400) return round($interval / 3600)." hours";
		if ($interval < 604800) return round($interval / 86400)." days";
		if ($interval < 2678400) return round($interval / 604800)." weeks";
		if ($interval < 31536000) return round($interval / 2678400)." months";
		if ($interval > 31536000) return round($interval / 31536000)." years";

	}
	
	public function getAccountName($accountID) {
		if(!is_numeric($accountID)) return false;

		$query = $this->connection->prepare("SELECT userName FROM accounts WHERE accountID = :id");
		$query->execute([':id' => $accountID]);

		if ($query->rowCount() > 0) 
		{
			$userName = $query->fetchColumn();
		} 
		else 
		{
			$userName = false;
		}

		return $userName;
	}

	public function randomString($length = 6) {
		$randomString = openssl_random_pseudo_bytes($length);

		if($randomString == false)
		{
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen($characters);
			$randomString = '';

			for ($i = 0; $i < $length; $i++) $randomString .= $characters[rand(0, $charactersLength - 1)];

			return $randomString;
		}

		$randomString = bin2hex($randomString);

		return $randomString;
	}

	public function get_accounts_with_permission($permission) {
		$query = $this->connection->prepare("SELECT roleID FROM roles WHERE $permission = 1 ORDER BY priority DESC");
		$query->execute();
		$result = $query->fetchAll();
		$accountlist = array();

		foreach($result as &$role) {
			$query = $this->connection->prepare("SELECT accountID FROM roleassign WHERE roleID = :roleID");
			$query->execute([':roleID' => $role["roleID"]]);
			$accounts = $query->fetchAll();

			foreach($accounts as &$user) $accountlist[] = $user["accountID"];
		}

		return $accountlist;
	}
	
	public function song_reupload($url) {
		require_once __DIR__ . "/../../core/lib/exploitPatch.php";
		$song = str_replace("www.dropbox.com", "dl.dropboxusercontent.com", $url);

		if (filter_var($song, FILTER_VALIDATE_URL) == TRUE && substr($song, 0, 4) == "http") 
		{
			$song = str_replace(["?dl=0", "?dl=1"], "", $song);
			$song = trim($song);

			$query = $this->connection->prepare("SELECT id FROM songs WHERE download = :download");
			$query->execute([':download' => $song]);	
			$id = $query->fetchColumn();

			if($id != false) return $id;

			$name = ExploitPatch::remove(urldecode(str_replace([".mp3", ".webm", ".mp4", ".wav"], "", basename($song))));
			
			if (str_contains($name, "?rlkey=")) 
			{
                $name = explode("?", $name);
                $name = $name[0];
                $name = str_replace("_", " ", $name);
                $name = ucwords($name);
            }
			
			$author = "Reupload";
			$info = $this->get_file_info($song);
			$size = $info['size'];

			if(substr($info['type'], 0, 6) != "audio/") return "-4";

			$size = round($size / 1024 / 1024, 2);
			$hash = "";
			
			$id = $this->connection->prepare("SELECT ID FROM songs WHERE <= 10000001 ORDER BY ID DESC");
			$id->execute();
			$id = $id->fetchColumn();
			$id += 1;
			
			$query = $this->connection->prepare("INSERT INTO songs (ID, name, authorID, authorName, size, download, hash) VALUES (:ID, :name, '9', :author, :size, :download, :hash)");
			$query->execute([':ID' => $id, ':name' => $name, ':download' => $song, ':author' => $author, ':size' => $size, ':hash' => $hash]);
		    
			return $id;
		}
		
		return "-2";
	}
    
	public function get_file_info($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		$data = curl_exec($ch);
		$size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		$mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		curl_close($ch);
		
		return ['size' => $size, 'type' => $mime];
	}
}
