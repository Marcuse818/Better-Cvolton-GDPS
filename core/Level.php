<?php
    require_once __DIR__."/Main.php";

    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/Lib.php";
    require_once __DIR__."/lib/XORCipher.php";
    require_once __DIR__."/lib/exploitPatch.php";
    require_once __DIR__."/lib/generateHash.php";

    interface Level {
        public function existLevel(string $levelName, int $userID): int;
        public function delete(int $userID, int $levelID): string;
        public function download(int $accountID, int $levelID, $inc, $extras, $hostname): string;
        public function getDaily(int $type): string;
        public function rateStar(int $accountID, int $levelID, int $starStars): string;
        public function rateDemon(int $accountID, int $levelID, int $rating): string;
        public function rateSuggest(int $accountID, int $levelID, int $starStars, int $feature, array $difficulty): string;
        public function report(int $levelID, $hostname): string;
        public function updateDesc($accountID, int $levelID, string $levelDescription): string;
        public function upload(
            int $accountID, 
            int $levelID, 
            $userName,
            $hostname,
            $userID,
            $levelName,
            $audioTrack,
            $levelLength,
            $secret,
            $levelString,
            $gjp,
            $levelVersion,
            $ts,
            $songs,
            $sfxs,
            $auto,
            $original,
            $twoPlayer,
            $songID,
            $object,
            $coins,
            $requestedStars,
            $extraString,
            $levelInfo,
            $unlisted,
            $unlisted2,
            $ldm,
            $wt,
            $wt2,
            $settingsString,
            $levelDescription,
            $password
        ): int;
    }
    
    class Levels implements Level {
        protected $connection;
        protected $Main, $Database, $Lib;

        private $uploadDate, $updateDate;
        
        public $gameVersion, $binaryVersion;
        
        public function __construct() {
            $this->Lib = new Lib();
            $this->Main = new Main();
            $this->Database = new Database();

            $this->connection = $this->Database->open_connection();
            $this->uploadDate = time();
            $this->updateDate = time();
        }

        public function existLevel(string $levelName, int $userID): int
        {
            $level = $this->connection->prepare("SELECT levelID FROM levels WHERE levelName = :levelName AND userID = :userID");
            $level->execute([":levelName" => $levelName, ":userID" => $userID]);
            $level = $level->rowCount();
            
            return ($level == 1) ? 1 : 0;
        }

        public function delete(int $userID, int $levelID): string { 
            $levelDelete = $this->connection->prepare('DELETE from levels WHERE levelID = :levelID AND userID = :userID AND starStars = 0 LIMIT 1');
            $levelDelete->execute([':levelID' => $levelID, ':userID' => $userID]);

            $insert = $this->connection->prepare('INSERT INTO actions (type, value, timestamp, value2) VALUES (:type, :itemID, :time, :ip)');
            $insert->execute([':type' => 8, ':itemID' => $levelID, ':time' => time(), ':ip' => $userID]);

            if (file_exists(__DIR__."/../database/data/levels/$levelID") && $levelDelete->rowCount()) 
            {
                rename(__DIR__."/../database/data/levels/$levelID", __DIR__."/../database/data/levels/deleted/$levelID");
                return "1";
            }

            return "-1";
        }

        public function download(int $accountID, int $levelID, $inc, $extras, $hostname): string {
            $feaID = 0;
            $levelString = "";
            $daily = 0;
            $response = "";
            
            if (!is_numeric($levelID)) return "-1";

            switch ($levelID) {
                case -1:
                    $dailyLevel = $this->connection->prepare("SELECT feaID, levelID FROM dailyfeatures WHERE timestamp < :time AND type = 0 ORDER BY timestamp DESC LIMIT 1");
                    $dailyLevel->execute([':time' => time()]);
                    $res = $dailyLevel->fetch();
                    $levelID = $res['levelID'];
                    $feaID = $res['feaID'];
                    $daily = 1;
                    break;
                case -2:
                    $dailyLevel = $this->connection->prepare('SELECT feaID, levelID FROM dailyfeatures WHERE timestamp < :time AND type = 1 ORDER BY timestamp DESC LIMIT 1');
                    $dailyLevel->execute([':time' => time()]);
                    $res = $dailyLevel->fetch();
                    $levelID = $res['levelID'];
                    $feaID = $res['feaID'];
                    $feaID = $feaID + 100001;
                    $daily = 1;
                    break;
                case -3:
                    $dailyLevel = $this->connection->prepare("SELECT feaID, levelID FROM dailyfeatures WHERE timestamp < :time AND type = 2 ORDER BY timestamp DESC LIMIT 1");
                    $dailyLevel->execute([':time' => time()]);
                    $res = $dailyLevel->fetch();
                    $levelID = $res['levelID'];
                    $feaID = $res['feaID'];
                    $feaID = $feaID + 200001;
                    $daily = 1;
                    break;

                default:
                    $daily = 0;
            }
            
             $levelData = ($daily == 1) ?
                $this->connection->prepare("SELECT levels.*, users.userName, users.extID FROM levels LEFT JOIN users ON levels.userID = users.userID WHERE levelID = :levelID") :
                $this->connection->prepare("SELECT * FROM levels WHERE levelID = :levelID");
            $levelData->execute([':levelID' => $levelID]);
            
            if ($levelData->rowCount() != 0) 
            {
                $levelData = $levelData->fetch();
                $addDownload = $this->connection->prepare("SELECT count(*) FROM actions_downloads WHERE levelID=:levelID AND ip=INET6_ATON(:ip)");
                $addDownload->execute([':levelID' => $levelID, ':ip' => $hostname]);
                
                if ($inc && $addDownload->fetchColumn() < 2)                
                {
                    $addDownload = $this->connection->prepare("UPDATE levels SET downloads = downloads + 1 WHERE levelID = :levelID");
                    $addDownload->execute([':levelID' => $levelID]);
                    $addDownload = $this->connection->prepare("INSERT INTO actions_downloads (levelID, ip) VALUES (:levelID, INET6_ATON(:ip))");
                    $addDownload->execute([':levelID' => $levelID, ':ip' => $hostname]);
                }
                
                $this->uploadDate = $this->Lib->make_time($levelData["uploadDate"]);
                $this->updateDate = $this->Lib->make_time($levelData["updateDate"]);

                $levelDescription = $levelData["levelDesc"];
                $password = 1;
		        $xor = $password;
		    
                if ($this->gameVersion > 19)
                {
                    $xor = base64_encode(XORCipher::cipher($password, 26364));
                }
                else
                {
                    $levelDescription = base64_decode($levelDescription);
                }

                if (file_exists(__DIR__."/../database/data/levels/$levelID")) $levelString = file_get_contents(__DIR__."/../database/data/levels/$levelID");

                if ($this->gameVersion > 18)
                {
                    if (substr($levelString, 0, 3) == "kS1")
                    {
                        $levelString = base64_encode(gzcompress($levelString));
                        $levelString = str_replace("/", "_", $levelString);
                        $levelString = str_replace("+", "-", $levelString);
                    }
                }
                
                $response = "1:".$levelData["levelID"].":2:".$levelData["levelName"].":3:".$levelDescription.":4:".$levelString.":5:".$levelData["levelVersion"];
			    $response .= ":6:".$levelData["userID"].":8:10:9:".$levelData["starDifficulty"].":10:".$levelData["downloads"].":11:1:12:".$levelData["audioTrack"];
			    $response .= ":13:".$levelData["gameVersion"].":14:".$levelData["likes"].":17:".$levelData["starDemon"].":43:".$levelData["starDemonDiff"];
			    $response .= ":25:".$levelData["starAuto"].":18:".$levelData["starStars"].":19:".$levelData["starFeatured"].":42:".$levelData["starEpic"];
			    $response .= ":45:".$levelData["objects"].":15:".$levelData["levelLength"].":30:".$levelData["original"].":31:".$levelData["twoPlayer"];
			    $response .= ":28:".$this->uploadDate.":29:".$this->updateDate.":35:".$levelData["songID"].":36:".$levelData["extraString"];
			    $response .= ":37:".$levelData["coins"].":38:".$levelData["starCoins"].":39:".$levelData["requestedStars"].":46:".$levelData["wt"].":47:".$levelData["wt2"];
			    $response .= ":48:".$levelData["settingsString"].":40:".$levelData["isLDM"].":27:$xor:52:".$levelData["songIDs"].":53:".$levelData["sfxIDs"].":57:".$levelData["ts"];
			
			    if ($daily == 1) $response .= ":41:".$feaID;
			    if ($extras) $response .= ":26:".$levelData["levelInfo"];
			
		        $response .= "#".GenerateHash::genSolo($levelString)."#";
			
			    $somestring = $levelData["userID"].",".$levelData["starStars"].",".$levelData["starDemon"].",".$levelData["levelID"].",".$levelData["starCoins"].",".$levelData["starFeatured"].",".$password.",".$feaID;
			    $response .= GenerateHash::genSolo2($somestring);
                
			    if ($daily == 1) $response .= "#".$this->Main->get_user_string($levelData["userID"]);
			    if ($this->binaryVersion == 30) $response .= "#" . $somestring;
            }
            
            return $response;
        }

        public function getDaily(int $type): string {
            $level_daily = $this->connection->prepare("SELECT feaID FROM dailyfeatures WHERE timestamp < :current AND type = :type ORDER BY timestamp DESC LIMIT 1");
            $level_daily->execute([':current' => time(), ':type' => $type]);

            if ($level_daily->rowCount() == 0) return "-1";
            
            $dailyLevelID = $level_daily->fetchColumn();
            $midnight = ($type == 1) ? strtotime('next monday') : strtotime('tomorrow 00:00:00');

	        if($type == 1) $dailyID += 100001;
            if ($type == 2) $dailyID += 200001;
            
	        $timeleft = $midnight - time();
            return $dailyLevelID."|".$timeleft;
        }

        public function rateStar(int $accountID, int $levelID, int $starStars): string {
           if (!is_numeric($accountID)) return "-1";
            
            $difficulty = $this->Main->get_difficulty($starStars, "", "stars");
                    
            $actionRate = $this->connection->prepare("INSERT INTO action_rate (accountID, levelID, difficulty) VALUES ($accountID, $levelID, :difficulty)");
            $actionRate->execute([":difficulty" => $difficulty["difficulty"]]);
                    
            $rate = $this->connection->prepare("
                SELECT difficulty, levelID, COUNT(*) AS CNT
                FROM action_rate
                WHERE levelID = $levelID
                GROUP BY difficulty
                HAVING COUNT(*) > 0
            ");
            $rate->execute();
            $rate = $rate->fetch();
                
            $isRated = $this->connection->prepare("SELECT isRated from action_rate WHERE levelID = $levelID");
            $isRated->execute();
            $isRated = $isRated->fetch();
            
            $starRate = $this->connection->prepare("SELECT starStars from levels WHERE levelID = $levelID");
            $starRate->execute();
            $starRate = $starRate->fetch();
            
            if ($isRated["isRated"] != 0 && $starRate != 0)  return "-1";
            
            if ($rate["CNT"] > 5)
            {
                $diff = $this->connection->prepare("SELECT AVG(difficulty) AS difficultyAvg FROM action_rate WHERE levelID = $levelID");
                $diff->execute();
                $diff = $diff->fetch();
                $diff = round($diff["difficultyAvg"]);
                
                $auto = 0;
                $demon = 0;
                
                if ($difficulty["auto"] == 1 && $diff == 10) $auto = 1;
                if ($difficulty["demon"] == 1 && $diff == 50) $demon = 1;
                
                $this->Main->rate_level($accountID, $levelID, 0, $diff, $auto, $demon);
                $upd = $this->connection->prepare("UPDATE action_rate SET isRated = 1 WHERE levelID = $levelID");
                $upd->execute();
                
                return "1";
            }
            
            return "-1";
        }

        public function rateDemon(int $accountID, int $levelID, int $rating): string 
        {
            if ($this->Main->getRolePermission($accountID, "actionRateDemon"))
            {
                $data = $this->Lib->demon_filter($rating);

                $demon_rate = $this->connection->prepare("UPDATE levels SET starDemonDiff = :demon WHERE levelID = :levelID");
                $demon_rate->execute([":demon" => $data["demon"], ":levelID" => $levelID]);

                $demon_rate = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('10', :value, :levelID, :timestamp, :id)");
                $demon_rate->execute([":value" => $data["name"], ":timestamp" => $this->uploadDate, ":id" => $accountID, ":levelID" => $levelID]);
                
                return $levelID;
            }

            return "-1";
        }

        public function rateSuggest(int $accountID, int $levelID, int $starStars, int $feature, array $difficulty): string {
            if ($this->Main->getRolePermission($accountID, "actionRateStars"))
            {
                $this->Main->rate_level($accountID, $levelID, $starStars, $difficulty["difficulty"], $difficulty["auto"], $difficulty["demon"]);
                $this->Main->feature_level($accountID, $levelID, $feature);
                $this->Main->verify_coins($accountID, $levelID, 1);

                return "1";
            }

            if ($this->Main->getRolePermission($accountID, "actionSuggestRating"))
            {
                $this->Main->suggest_level($accountID, $levelID, $difficulty["difficulty"], $starStars, $feature, $difficulty["auto"], $difficulty["demon"]);

                return "1";
            }

            return "-2";
        }

        public function report(int $levelID, $hostname): string {
            $levelReport = $this->connection->prepare("SELECT count(*) FROM reports WHERE levelID = :levelID AND hostname = :hostname");
            $levelReport->execute([":levelID" => $levelID, ":hostname" => $hostname]);

            if ($levelReport->fetchColumn() == 0)
            {
                $levelReport = $this->connection->prepare("INSERT INTO reports (levelID, hostname) VALUES (:levelID, :hostname)");
                $levelReport->execute([":levelID" => $levelID, ":hostname" => $hostname]);

                return $this->connection->lastInsertId();
            }

            return "-1";
        }

        public function updateDesc($accountID, int $levelID, string $levelDescription): string {
            $rawDescription = str_replace('-', '+', $levelDescription);
            $rawDescription = str_replace('_', '/', $rawDescription);
            $rawDescription = base64_decode($rawDescription);

            if (strpos($rawDescription, "<c") != false)
            {
                $tags = substr_count($rawDescription, "<c");

                if ($tags > substr_count($rawDescription, "</c>"))
                {
                    $tags = $tags - substr_count($rawDescription, '</c>');
				    for ($i = 0; $i < $tags; $i++) $rawDescription .= '</c>';

                    $levelDescription = str_replace('+', '-', base64_encode($rawDescription));
                    $levelDescription = str_replace('/', '_', $levelDescription);
                }
            }

            $updateDescription = $this->connection->prepare("UPDATE levels SET levelDesc = :levelDesc WHERE levelID = :levelID AND extID = :extID");
            $updateDescription->execute(['levelDesc' => $levelDescription, ':levelID' => $levelID, ':extID' => $accountID]);

            return "1";
        }

        public function upload(
            int $accountID, 
            int $levelID, 
            $userName,
            $hostname,
            $userID,
            $levelName,
            $audioTrack,
            $levelLength,
            $secret,
            $levelString,
            $gjp,
            $levelVersion,
            $ts,
            $songs,
            $sfxs,
            $auto,
            $original,
            $twoPlayer,
            $songID,
            $object,
            $coins,
            $requestedStars,
            $extraString,
            $levelInfo,
            $unlisted,
            $unlisted2,
            $ldm,
            $wt,
            $wt2,
            $settingsString,
            $levelDescription,
            $password
        ): int {
            $check_ip = $this->connection->prepare("SELECT count(*) FROM levels WHERE uploadDate > :time AND (userID = :userID OR hostname = :ip)");
            $check_ip->execute([
                ':time' => $this->uploadDate - 30,
                ':userID' => $userID,
                ':ip' => $hostname
            ]);
            
            if ($check_ip->fetchColumn()) return -1;

            if ($levelString != "" && $levelName != "")
            {
                if ($this->existLevel($levelName, $userID) == 1)
                {
                    $level_update = $this->connection->prepare("UPDATE levels SET levelName = :levelName, gameVersion = :gameVersion, binaryVersion=:binaryVersion, userName=:userName, levelDesc=:levelDesc, levelVersion=:levelVersion, levelLength=:levelLength, audioTrack=:audioTrack, auto=:auto, password=:password, original=:original, twoPlayer=:twoPlayer, songID=:songID, objects=:objects, coins=:coins, requestedStars=:requestedStars, extraString=:extraString, levelString=:levelString, levelInfo=:levelInfo, secret=:secret, updateDate=:updateDate, unlisted=:unlisted, hostname=:hostname, isLDM=:ldm, wt=:wt, wt2=:wt2, unlisted2=:unlisted2, settingsString=:settingsString, songIDs = :songs, sfxIDs = :sfxs, ts = :ts WHERE levelName = :levelName AND extID = :id");
                    $level_update->execute([
                        ":levelName" => $levelName, 
                        ":gameVersion" => $this->gameVersion, 
                        ":binaryVersion" => $this->binaryVersion,
                        ":userName" => $userName, 
                        ":levelDesc" => $levelDescription, 
                        ":levelVersion" => $levelVersion, 
                        ":levelLength" => $levelLength, 
                        ":audioTrack" => $audioTrack, 
                        ":auto" => $auto, 
                        ":password" => $password, 
                        ":original" => $original, 
                        ":twoPlayer" => $twoPlayer, 
                        ":songID" => $songID, 
                        ":objects" => $object, 
                        ":coins" => $coins, 
                        ":requestedStars" => $requestedStars, 
                        ":extraString" => $extraString, 
                        ":levelString" => $levelString, 
                        ":levelInfo" => $levelInfo, 
                        ":secret" => $secret, 
                        ":id" => $accountID,
                        ":updateDate" => $this->updateDate,
                        ":unlisted" => $unlisted, 
                        ":hostname" => $hostname, 
                        ":ldm" => $ldm, 
                        ":wt" => $wt, 
                        ":wt2" => $wt2, 
                        ":unlisted2" => $unlisted2, 
                        ":settingsString" => $settingsString, 
                        ":songs" => $songs, 
                        ":sfxs" => $sfxs, 
                        ":ts" => $ts
                    ]);
                    
                    file_put_contents(__DIR__."/../database/data/levels/$levelID", $levelString);

                    return $levelID;
                }
                
                $level_upload = $this->connection->prepare("INSERT INTO levels (levelName, gameVersion, binaryVersion, userName, levelDesc, levelVersion, levelLength, audioTrack, auto, password, original, twoPlayer, songID, objects, coins, requestedStars, extraString, levelString, levelInfo, secret, uploadDate, userID, extID, updateDate, unlisted, hostname, isLDM, wt, wt2, unlisted2, settingsString, songIDs, sfxIDs, ts) 
                    VALUES (:levelName, :gameVersion, :binaryVersion, :userName, :levelDesc, :levelVersion, :levelLength, :audioTrack, :auto, :password, :original, :twoPlayer, :songID, :objects, :coins, :requestedStars, :extraString, :levelString, :levelInfo, :secret, :uploadDate, :userID, :id, :updateDate, :unlisted, :hostname, :ldm, :wt, :wt2, :unlisted2, :settingsString, :songs, :sfxs, :ts)");
                $level_upload->execute([
                    ":levelName" => $levelName, 
                    ":gameVersion" => $this->gameVersion, 
                    ":binaryVersion" => $this->binaryVersion, 
                    ":userName" => $userName, 
                    ":levelDesc" => $levelDescription, 
                    ":levelVersion" => $levelVersion, 
                    ":levelLength" => $levelLength, 
                    ":audioTrack" => $audioTrack, 
                    ":auto" => $auto, 
                    ":password" => $password, 
                    ":original" => $original, 
                    ":twoPlayer" => $twoPlayer, 
                    ":songID" => $songID, 
                    ":objects" => $object, 
                    ":coins" => $coins, 
                    ":requestedStars" => $requestedStars, 
                    ":extraString" => $extraString, 
                    ":levelString" => $levelString, 
                    ":levelInfo" => $levelInfo, 
                    ":secret" => $secret, 
                    ':uploadDate' => $this->uploadDate, 
                    ':userID' => $userID, 
                    ':id' => $accountID, 
                    ':updateDate' => $this->updateDate, 
                    ":unlisted" => $unlisted, 
                    ":hostname" => $hostname, 
                    ':ldm' => $ldm, 
                    ":wt" => $wt, 
                    ":wt2" => $wt2, 
                    ':unlisted2' => $unlisted2, 
                    ":settingsString" => $settingsString, 
                    ":songs" => $songs, 
                    ":sfxs" => $sfxs, 
                    ":ts" => $ts
                ]);
                
                $lastInsertID = $this->connection->lastInsertId();
                file_put_contents(__DIR__."/../database/data/levels/$lastInsertID", $levelString);

                return $lastInsertID;
            }

            return -1;
        }
    }
