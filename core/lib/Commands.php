<?php
	require_once __DIR__."/../Main.php";
	require_once __DIR__."/exploitPatch.php";
	require_once __DIR__."/Database.php";
	require_once __DIR__."/Lib.php";

	class Commands {
		private $connection, $main, $lib;

		public function __construct() {
			$new_con = new Database();
			$this->main = new Main();
			$this->lib = new Lib();

			$this->connection = $new_con->open_connection();
		}

		public function doCommands($accountID, $comment, $levelID) {
			if(!is_numeric($accountID)) return false;
			if ($levelID < 0) return $this->doListCommands($accountID, $comment, $levelID);

			$commentarray = explode(' ', $comment);
			$uploadDate = time();

			$query2 = $this->connection->prepare("SELECT extID FROM levels WHERE levelID = :id");
			$query2->execute([':id' => $levelID]);
			
			if(substr($comment, 0, 7) == '!unrate' && $this->main->getRolePermission($accountID, "commandUnrate"))
			{	
				$query = $this->connection->prepare("UPDATE levels SET starStars=:starStars, starDifficulty=:starDifficulty, starDemon=:starDemon, starAuto=:starAuto, starFeatured = :starFeatured, starEpic = :starEpic, rateDate=:timestamp WHERE levelID=:levelID");
				$query->execute([':starStars' => 0, ':starDifficulty' => 0, ':starDemon' => 0, ':starAuto' => 0, ':starFeatured' => 0, ':starEpic' => 0, ':timestamp' => 0, ':levelID' => $levelID]);
				$query = $this->connection->prepare("INSERT INTO modactions (type, value, value2, value3, timestamp, account) VALUES ('1', :value, :value2, :levelID, :timestamp, :id)");
				$query->execute([':value' => $commentarray[1], ':timestamp' => $uploadDate, ':id' => $accountID, ':value2' => 0, ':levelID' => $levelID]);

				return true;
			}

			if (substr($comment, 0, 7) == '!uncoin' && $this->main->getRolePermission($accountID, "commandUncoin")) 
			{
				$query = $this->connection->prepare("UPDATE levels SET starCoins = 0 WHERE levelID = $levelID");
				$query->execute();

				return true;
			}

			if(substr($comment, 0, 6) == '!daily' && $this->main->getRolePermission($accountID, "commandDaily"))
			{
				$query = $this->connection->prepare("SELECT count(*) FROM dailyfeatures WHERE levelID = :level AND type = 0");
				$query->execute([':level' => $levelID]);

				if($query->fetchColumn() != 0) return false;

				$query = $this->connection->prepare("SELECT timestamp FROM dailyfeatures WHERE timestamp >= :tomorrow AND type = 0 ORDER BY timestamp DESC LIMIT 1");
				$query->execute([':tomorrow' => strtotime("tomorrow 00:00:00")]);
				$timestamp = ($query->rowCount() == 0) ? strtotime("tomorrow 00:00:00") : $query->fetchColumn() + 86400;

				$query = $this->connection->prepare("INSERT INTO dailyfeatures (levelID, timestamp, type) VALUES (:levelID, :uploadDate, 0)");
				$query->execute([':levelID' => $levelID, ':uploadDate' => $timestamp]);
				$query = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account, value2, value4) VALUES ('5', :value, :levelID, :timestamp, :id, :dailytime, 0)");
				$query->execute([':value' => "1", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID, ':dailytime' => $timestamp]);
			
				return true;
			}

			if(substr($comment, 0, 7) == '!weekly' && $this->main->getRolePermission($accountID, "commandWeekly"))
			{
				$query = $this->connection->prepare("SELECT count(*) FROM dailyfeatures WHERE levelID = :level AND type = 1");
				$query->execute([':level' => $levelID]);

				if($query->fetchColumn() != 0) return false;

				$query = $this->connection->prepare("SELECT timestamp FROM dailyfeatures WHERE timestamp >= :tomorrow AND type = 1 ORDER BY timestamp DESC LIMIT 1");
				$query->execute([':tomorrow' => strtotime("next monday")]);
				$timestamp = ($query->rowCount() == 0) ? strtotime("next monday") : $query->fetchColumn() + 604800;

				$query = $this->connection->prepare("INSERT INTO dailyfeatures (levelID, timestamp, type) VALUES (:levelID, :uploadDate, 1)");
				$query->execute([':levelID' => $levelID, ':uploadDate' => $timestamp]);
				$query = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account, value2, value4) VALUES ('5', :value, :levelID, :timestamp, :id, :dailytime, 1)");
				$query->execute([':value' => "1", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID, ':dailytime' => $timestamp]);
			
				return true;
			}

			if(substr($comment, 0, 7) == '!delete' && $this->main->getRolePermission($accountID, "commandDelete"))
			{
				if(!is_numeric($levelID)) return false;
			
				$query = $this->connection->prepare("DELETE from levels WHERE levelID=:levelID LIMIT 1");
				$query->execute([':levelID' => $levelID]);
				$query = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('6', :value, :levelID, :timestamp, :id)");
				$query->execute([':value' => "1", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
				if(file_exists(__DIR__."/../../database/data/levels/$levelID")) rename(__DIR__."/../../database/data/levels/$levelID", __DIR__."/../../database/data/levels/deleted/$levelID");

				return true;
			}

			if(substr($comment, 0, 7) == '!setacc' && $this->main->getRolePermission($accountID, "commandSetacc")){
				$query = $this->connection->prepare("SELECT accountID FROM accounts WHERE userName = :userName OR accountID = :userName LIMIT 1");
				$query->execute([':userName' => $commentarray[1]]);
				if($query->rowCount() == 0) return false;

				$targetAcc = $query->fetchColumn();

				$query = $this->connection->prepare("SELECT userID FROM users WHERE extID = :extID LIMIT 1");
				$query->execute([':extID' => $targetAcc]);
				$userID = $query->fetchColumn();

				$query = $this->connection->prepare("UPDATE levels SET extID=:extID, userID=:userID, userName=:userName WHERE levelID=:levelID");
				$query->execute([':extID' => $targetAcc, ':userID' => $userID, ':userName' => $commentarray[1], ':levelID' => $levelID]);
				$query = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('7', :value, :levelID, :timestamp, :id)");
				$query->execute([':value' => $commentarray[1], ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
			
				return true;
			}

			return false;
		}

		public function doListCommands($accountID, $command, $listID) {
			if(substr($command,0,1) != '!') return false;
			$listID = $listID * -1;

			$carray = explode(' ', $command);

			switch($carray[0]) 
			{
				case '!r':
				case '!rate':
					$getList = $this->connection->prepare('SELECT * FROM lists WHERE listID = :listID');
					$getList->execute([':listID' => $listID]);
					$getList = $getList->fetch();

					$reward = ExploitPatch::number($carray[1]);
					$diff = ExploitPatch::charclean($carray[2]);
					$featured = is_numeric($carray[3]) ? ExploitPatch::number($carray[3]) : ExploitPatch::number($carray[4]);
					$count = is_numeric($carray[3]) ? ExploitPatch::number($carray[4]) : ExploitPatch::number($carray[5]);

					if(empty($count)) 
					{
						$levelsCount = $getList['listlevels'];
						$count = count(explode(',', $levelsCount));
					}

					if(!is_numeric($diff)) 
					{
						$diff = strtolower($diff);

						if(isset($carray[3]) AND strtolower($carray[3]) == "demon") 
						{
							$diffList = ['easy' => 1, 'medium' => 2, 'hard' => 3, 'insane' => 4, 'extreme' => 5];
							$diff = 5+$diffList[$diff];
						} 
						else 
						{
							$diffList = ['na' => -1, 'auto' => 0, 'easy' => 1, 'normal' => 2, 'hard' => 3, 'harder' => 4, 'demon' => 5];
							$diff = $diffList[$diff];
						}
					}
					
					if(!isset($diff)) $diff = $getList['starDifficulty'];

					if($this->main->getRolePermission($accountID, "commandRate")) 
					{
						$query = $this->connection->prepare("UPDATE lists SET starStars = :reward, starDifficulty = :diff, starFeatured = :feat, countForReward = :count WHERE listID = :listID");
						$query->execute([':listID' => $listID, ':reward' => $reward, ':diff' => $diff, ':feat' => $featured, ':count' => $count]);
						$query = $this->connection->prepare("INSERT INTO modactions (type, value, value2, value3, timestamp, account) VALUES ('30', :value, :value2, :listID, :timestamp, :id)");
						$query->execute([':value' => $reward, ':value2' => $diff, ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
					} 
					elseif($this->main->getRolePermission($accountID, "actionSuggestRating")) 
					{
						$query = $this->connection->prepare("INSERT INTO suggest (suggestBy, suggestLevelId, suggestDifficulty, suggestStars, suggestFeatured, timestamp) VALUES (:accID, :listID, :diff, :reward, :feat, :time)");
						$query->execute([':listID' => $listID*-1, ':reward' => $reward, ':diff' => $diff, ':accID' => $accountID, ':feat' => $featured, ':time' => time()]);
						$query = $this->connection->prepare("INSERT INTO modactions (type, value, value2, value3, timestamp, account) VALUES ('31', :value, :value2, :listID, :timestamp, :id)");
						$query->execute([':value' => $reward, ':value2' => $diff, ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
					} 
					else 
					{
						return false;
					}
					break;

				case '!f':
				case '!feature':
					if(!$this->main->getRolePermission($accountID, "commandFeature")) return false;
					if(!isset($carray[1])) $carray[1] = 1;

					$query = $this->connection->prepare("UPDATE lists SET starFeatured = :feat WHERE listID=:listID");
					$query->execute([':listID' => $listID, ':feat' => $carray[1]]);
					$query = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('32', :value, :listID, :timestamp, :id)");
					$query->execute([':value' => $carray[1], ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
					break;

				case '!d':
				case '!delete':
					if(!$this->main->getRolePermission($accountID, "commandDelete")) return false;

					$query = $this->connection->prepare("DELETE FROM lists WHERE listID = :listID");
					$query->execute([':listID' => $listID]);
					$query = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('34', 0, :listID, :timestamp, :id)");
					$query->execute([':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
					break;

				case '!acc':
				case '!setacc':
					if(!$this->main->getRolePermission($accountID, "commandSetacc")) return false;
					
					$acc = (is_numeric($carray[1])) ? ExploitPatch::number($carray[1]) : $this->main->get_id_from_name(ExploitPatch::charclean($carray[1]));

					if(empty($acc)) return false;
					
					$query = $this->connection->prepare("UPDATE lists SET accountID = :accID WHERE listID=:listID");
					$query->execute([':listID' => $listID, ':accID' => $acc]);
					$query = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('35', :value, :listID, :timestamp, :id)");
					$query->execute([':value' => $acc, ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
					break;
			}
			return true;
		}
}
?>
