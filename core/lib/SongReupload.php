<?php
	require_once __DIR__."/Database.php";

	class SongReupload {
		private $connection;

		public $reupload_song, $uploadDate;

		public function __construct() {
			$new_con = new Database();
			$this->connection = $new_con->open_connection();
		}

		public function reupload($result) {
			$this->reupload_song = explode('~|~', $result);
			$this->uploadDate = time();

			$query = $this->connection->prepare("INSERT INTO songs (ID, name, authorID, authorName, size, download, reuploadTime) VALUES (:id, :name, :authorID, :authorName, :size, :download, :reuploadTime)");
			$query->execute([':id' => $this->reupload_song[1], ':name' => $this->reupload_song[3], ':authorID' => $this->reupload_song[5], ':authorName' => $this->reupload_song[7], ':size' => $this->reupload_song[9], ':download' => $this->reupload_song[13], ':reuploadTime' => $this->uploadDate]);
			
			return $this->connection->lastInsertId();
		}
}
?>