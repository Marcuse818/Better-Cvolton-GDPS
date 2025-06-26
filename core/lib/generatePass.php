<?php
	require_once __DIR__."/../Main.php";
	require_once __DIR__."/Database.php";

	class GeneratePass
	{	
		public static function GJP2fromPassword($pass) {
			return sha1($pass . "mI29fmAnxgTs");
		}

		public static function GJP2hash($pass) {
			return password_hash(self::GJP2fromPassword($pass), PASSWORD_DEFAULT);
		}

		public static function assignGJP2($accid, $pass) {
			$new_con = new Database();
			$db = $new_con->open_connection();

			$query = $db->prepare("UPDATE accounts SET gjp2 = :gjp2 WHERE accountID = :id");
			$query->execute(["gjp2" => self::GJP2hash($pass), ":id" => $accid]);
		}

		public static function attemptsFromIP() {
			$main = new Main();
			$new_con = new Database();
			$db = $new_con->open_connection();

			$ip = $main->get_ip();
			$newtime = time() - (60*60);

			$query6 = $db->prepare("SELECT count(*) FROM actions WHERE type = '6' AND timestamp > :time AND value2 = :ip");
			$query6->execute([':time' => $newtime, ':ip' => $ip]);
			
			return $query6->fetchColumn();
		}

		public static function tooManyAttemptsFromIP() {
			return self::attemptsFromIP() > 7;
		}

		public static function logInvalidAttemptFromIP($accid) {
			$main = new Main();
			$DB = new Database();
			$db = $DB->open_connection();

			$ip = $main->get_ip();
			$query6 = $db->prepare("INSERT INTO actions (type, value, timestamp, value2) VALUES ('6', :accid, :time, :ip)");
			$query6->execute([':accid' => $accid, ':time' => time(), ':ip' => $ip]);
		}

		public static function assignModIPs($accountID, $ip) {
			$main = new Main();
			$new_con = new Database();
			$db = $new_con->open_connection();

			$modipCategory = $main->getRolePermission($accountID, "modipCategory");

			if($modipCategory > 0)
			{ 
				$query4 = $db->prepare("SELECT count(*) FROM modips WHERE accountID = :id");
				$query4->execute([':id' => $accountID]);

				if ($query4->fetchColumn() > 0) 
				{
					$query6 = $db->prepare("UPDATE modips SET IP=:hostname, modipCategory=:modipCategory WHERE accountID=:id");
				}
				else
				{
					$query6 = $db->prepare("INSERT INTO modips (IP, accountID, isMod, modipCategory) VALUES (:hostname,:id,'1',:modipCategory)");
				}

				$query6->execute([':hostname' => $ip, ':id' => $accountID, ':modipCategory' => $modipCategory]);
			}
		}

		public static function isGJP2Valid($accid, $gjp2) {
			$main = new Main();
			$new_con = new Database();
			$db = $new_con->open_connection();

			if(self::tooManyAttemptsFromIP()) return -1;

			$userInfo = $db->prepare("SELECT gjp2, isActive FROM accounts WHERE accountID = :accid");
			$userInfo->execute([':accid' => $accid]);
			if($userInfo->rowCount() == 0) return 0;

			$userInfo = $userInfo->fetch();
			if(!($userInfo['gjp2'])) return -2;

			if(password_verify($gjp2, $userInfo['gjp2'])) 
			{
				self::assignModIPs($accid, $main->get_ip());
				return $userInfo['isActive'] ? 1 : -2;
			} 
			else 
			{
				self::logInvalidAttemptFromIP($accid);
				return 0;
			}
		
		}

		public static function isGJP2ValidUsrname($userName, $gjp2) {
			$new_con = new Database();
			$db = $new_con->open_connection();

			$query = $db->prepare("SELECT accountID FROM accounts WHERE userName LIKE :userName");
			$query->execute([':userName' => $userName]);
			if($query->rowCount() == 0) return 0;
			
			$result = $query->fetch();
			$accID = $result["accountID"];

			return self::isGJP2Valid($accID, $gjp2);
		
		}

		public static function isValid($accid, $pass) {
			$main = new Main();
			$new_con = new Database();
			$db = $new_con->open_connection();

			if(self::tooManyAttemptsFromIP()) return -1;

			$query = $db->prepare("SELECT accountID, salt, password, isActive, gjp2 FROM accounts WHERE accountID = :accid");
			$query->execute([':accid' => $accid]);
			if($query->rowCount() == 0) return 0;
		
			$result = $query->fetch();
			if(password_verify($pass, $result["password"]))
			{
				if(!$result["gjp2"]) self::assignGJP2($accid, $pass);
				self::assignModIPs($accid, $main->get_ip());
				return $result['isActive'] ? 1 : -2;
			} 
			else 
			{
				self::logInvalidAttemptFromIP($accid);
				return 0;
			}
		}

		public static function isValidUsrname($userName, $pass) {
			$new_con = new Database();
			$db = $new_con->open_connection();
			
			$query = $db->prepare("SELECT accountID FROM accounts WHERE userName LIKE :userName");
			$query->execute([':userName' => $userName]);

			if($query->rowCount() == 0) return 0;

			$result = $query->fetch();
			$accID = $result["accountID"];

			return self::isValid($accID, $pass);
		}
	}
?>