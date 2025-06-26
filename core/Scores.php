<?php  
    require_once __DIR__."/Main.php";

    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/Lib.php";

    interface Scores {
        public function getData(
            $accountID, 
            int $levelID, 
            $type = null, 
            $mode = "points", 
            int $time = 0, 
            int $points = 0,
            int $count = 0
        ): string;
        public function update(int $accountID, int $userID, string $hostname): string;
    }

    class Creators implements Scores {
        protected $connection;
        protected $Main, $Database, $Lib;

        private $uploadDate;

        public function __construct() {
            $this->Main = new Main();
            $this->Database = new Database();
            $this->Lib = new Lib();

            $this->connection = $this->Database->open_connection();
            $this->uploadDate = time();
        }
        public function getData($accountID, int $levelID, $type = null, $mode = "points", int $time = 0, int $points = 0, int $count = 0): string {
            $creators = $this->connection->prepare("SELECT * FROM users WHERE isCreatorBanned = '0' ORDER BY creatorPoints DESC LIMIT 100");
            $creators->execute();
            $creators = $creators->fetchAll();
            $xi = 0;

            foreach($creators as &$creator) {
                $extID = (is_numeric($creator["extID"])) ? $creator["extID"] : 0;
                $xi++;

                $creatorsString .= "1:".$creator["userName"].":2:".$creator["userID"].":13:".$creator["coins"].":17:".$creator["userCoins"].":6:".$xi.":9:".$creator["icon"].":10:".$creator["color1"].":11:".$creator["color2"].":14:".$creator["iconType"].":15:".$creator["special"].":16:".$extID.":3:".$creator["stars"].":8:".round($creator["creatorPoints"], 0, PHP_ROUND_HALF_DOWN).":4:".$creator["demons"].":7:".$extID.":46:".$creator["diamonds"]."|";
            }

            $creatorsString = substr($creatorsString, 0, -1);

            return $creatorsString;
        }

        public function update(int $accountID, int $userID, string $hostname): string { return "lol"; }
    }

    class Score implements Scores {
        protected $connection;
        protected $Main, $Database, $Lib;

        private $uploadDate;
        public $userName;
        public $percent, $attempts, $clicks, $progresses, $dailyID, $time;
        public $secret;				
		public $stars; 				
		public $demons; 				
		public $icon; 				
		public $color1; 				
		public $color2;				

		public $gameVersion;		
		public $binaryVersion;	
		public $coins; 				
		public $iconType;			
		public $userCoins;			
		public $special;			
		public $accIcon;			
		public $accShip;			
		public $accBall;			
		public $accBird;			
		public $accDart;			
		public $accRobot;			
		public $accGlow;			
		public $accSpider;				
        public $accExplosion;		
		public $diamonds;			
		public $moons;			
		public $color3;			
		public $accSwing;			
		public $accJetpack;		

        public function __construct() {
            $this->Main = new Main();
            $this->Database = new Database();
            $this->Lib = new Lib();

            $this->connection = $this->Database->open_connection();
            $this->uploadDate = time();
        }

        public function getData($accountID, int $levelID, $type = null, $mode = "points", int $time = 0, int $points = 0, int $count = 0): string {
            // wtf in code this wasn't used later ??? $userID = $this->Main->get_user_id($accountID);
            $condition = ($this->dailyID > 0) ? ">" : "=";

            $level_score = $this->connection->prepare("SELECT percent FROM levelscores WHERE accountID = :accountID AND levelID = :levelID AND dailyID $condition 0");
            $level_score->execute([":accoundID" => $accountID, ":levelID" => $levelID]);
            $old_percent = $level_score->fetchColumn();
            
            $level_score = ($level_score->rowCount() == 0) ?
                $this->connection->prepare("INSERT INTO levelscores (accountID, levelID, percent, uploadDate, coins, attempts, clicks, time, progresses, dailyID) VALUES (:accountID, :levelID, :percent, :uploadDate, :coins, :attempts, :clicks, :time, :progresses, :dailyID)") :
                (($old_percent <= $this->percent) ? 
                    $this->connection->prepare("UPDATE levelscores SET percent = :percent, uploadDate = :uploadDate, coins = :coins, attempts = :attempts, clicks = :clicks, time = :time, progresses = :progresses, dailyID = :dailyID WHERE accountID = :accountID AND levelID = :levelID AND dailyID $condition 0") :
                    $this->connection->prepare("SELECT count(*) FROM levelscores WHERE percent=:percent AND uploadDate=:uploadDate AND accountID=:accountID AND levelID=:levelID AND coins = :coins AND attempts = :attempts AND clicks = :clicks AND time = :time AND progresses = :progresses AND dailyID = :dailyID"));
            
            $level_score->execute([':accountID' => $accountID, ':levelID' => $levelID, ':percent' => $this->percent, ':uploadDate' => $this->uploadDate, ':coins' => $this->coins, ':attempts' => $this->attempts, ':clicks' => $this->clicks, ':time' => $this->time, ':progresses' => $this->progresses, ':dailyID' => $this->dailyID]);
            
            if ($this->percent > 100) 
            {
                $banned = $this->connection->prepare("UPDATE users SET isBanned = 1 WHERE extID = :accountID");
                $banned->execute([":accountID" => $accountID]);
            }

            switch($type) {
                case 0:
                    $friends = $this->Main->get_friends($accountID);
                    $friends[] = $accountID;
                    $friends = implode(",", $friends);
                    $type_result = $this->connection->prepare("SELECT accountID, uploadDate, percent, coins FROM levelscores WHERE dailyID $condition 0 AND levelID = :levelID AND accountID IN ($friends) ORDER BY percent DESC");
                    $type_result->execute([":levelID"=> $levelID]);
                    break;
                
                case 1:
                    $type_result = $this->connection->prepare("SELECT accountID, uploadDate, percent, coins FROM levelscores WHERE dailyID $condition 0 AND levelID = :levelID ORDER BY percent DESC");
                    $type_result->execute([":levelID" => $levelID]);
                    break;

                case 2:
                    $type_result = $this->connection->prepare("SELECT accountID, uploadDate, percent, coins FROM levelscores WHERE dailyID $condition 0 AND levelID = :levelID AND uploadDate > :time ORDER BY percent DESC");
                    $type_result->execute([":levelID", ":time" => time() - 604800]);
                    break;

                default:
                    return -1;
            } 

            $type_result = $type_result->fetchAll();

            foreach($type_result as &$score) {
                $extID = $score["accountID"];
                $level_score = $this->connection->prepare("SELECT userName, userID, icon, color1, color2, color3, iconType, special, extID, isBanned FROM users WHERE extID = :extID");
                $level_score->execute([":extID" => $extID]);
                $users = $level_score->fetchAll();
                $user = $users[0];
                $time = $this->Lib->make_time($score["uploadDate"]);
                
                if ($user["isBanned"] == 0)
                {
                    $place = ($score["percent"]) ? 1 : (($score["percent"] > 75) ? 2 : 3);
                }

                $scoreString .= "1:".$user["userName"].":2:".$user["userID"].":9:".$user["icon"].":10:".$user["color1"].":11:".$user["color2"].":51:".$user["color3"].":14:".$user["iconType"].":15:".$user["special"].":16:".$user["extID"].":3:".$score["percent"].":6:".$place.":13:".$score["coins"].":42:".$this->time."|";
            }

            return $scoreString;
        }

        public function update(int $accountID, int $userID, string $hostname): string {
            $update = $this->connection->prepare("SELECT stars, coins, demons, userCoins, diamonds, moons FROM users WHERE userID = :userID LIMIT 1");
            $update->execute([":userID" => $userID]);
            $old = $update->fetch();

            $update = $this->connection->prepare("UPDATE users SET gameVersion=:gameVersion, userName=:userName, coins=:coins,  secret=:secret, stars = :stars, demons = :demons, icon = :icon, color1 = :color1, color2 = :color2, iconType = :iconType, userCoins = :userCoins, special = :special, accIcon = :accIcon, accShip = :accShip, accBall = :accBall, accBird = :accBird, accDart = :accDart, accRobot = :accRobot, accGlow = :accGlow, IP = :hostname, lastPlayed = :uploadDate, accSpider = :accSpider, accExplosion=:accExplosion, diamonds=:diamonds, moons=:moons, color3=:color3, accSwing = :accSwing, accJetpack = :accJetpack WHERE userID = :userID");
            $update->execute([':gameVersion' => $this->gameVersion, ':userName' => $this->userName, ':coins' => $this->coins, ':secret' => $this->secret, ':stars' => $this->stars, ':demons' => $this->demons, ':icon' => $this->icon, ':color1' => $this->color1, ':color2' => $this->color2, ':iconType' => $this->iconType, ':userCoins' => $this->userCoins, ':special' => $this->special, ':accIcon' => $this->accIcon, ':accShip' => $this->accShip, ':accBall' => $this->accBall, ':accBird' => $this->accBird, ':accDart' => $this->accDart, ':accRobot' => $this->accRobot, ':accGlow' => $this->accGlow, ':hostname' => $hostname, ':uploadDate' => $this->uploadDate, ':userID' => $userID, ':accSpider' => $this->accSpider, ':accExplosion' => $this->accExplosion, ':diamonds' => $this->diamonds, ':moons' => $this->moons, ':color3' => $this->color3, ':accSwing' => $this->accSwing, ':accJetpack' => $this->accJetpack]);
            
            $starsdiff = $this->stars - $old["stars"];
            $coindiff = $this->coins - $old["coins"];
            $demondiff = $this->demons - $old["demons"];
            $ucdiff = $this->userCoins - $old["userCoins"];
            $diadiff = $this->diamonds - $old["diamonds"];
            $moondiff = $this->moons - $old["moons"];

            $update = $this->connection->prepare("INSERT INTO actions (type, value, timestamp, account, value2, value3, value4, value5, value6) VALUES ('9', :stars, :timestamp, :account, :coinsd, :demon, :usrco, :diamond, :moons)");
            $update->execute([':timestamp' => time(), ':stars' => $starsdiff, ':account' => $userID, ':coinsd' => $coindiff, ':demon' => $demondiff, ':usrco' => $ucdiff, ':diamond' => $diadiff, ':moons' => $moondiff]);

            return $userID;
        }
    }

    class Platformer implements Scores {
        protected $connection;
        protected $Main, $Database, $Lib;

        private $uploadDate;

        public function __construct() {
            $this->Main = new Main();
            $this->Database = new Database();
            $this->Lib = new Lib();

            $this->connection = $this->Database->open_connection();
            $this->uploadDate = time();
        }

        public function getData($accountID, int $levelID, $type = null, $mode = "points", int $time = 0, int $points = 0, int $count = 0): string {
            $plat_scores["time"] = $time;
            $plat_scores["points"] = $points;
            
            $query = $this->connection->prepare("SELECT {$mode} FROM platscores WHERE accountID = :accountID AND levelID = :levelID");
            $query->execute([':accountID' => $accountID, ':levelID' => $levelID]);
            $old_percent = $query->fetchColumn();
            
            if ($query->rowCount() == 0) 
            {
                $query = $this->connection->prepare("INSERT INTO platscores (accountID, levelID, {$mode}, timestamp) VALUES (:accountID, :levelID, :{$mode}, :timestamp)");       
            }
            else
            {
                if(($mode == "time" && $old_percent > $plat_scores['time'] && $plat_scores['time'] > 0) || ($mode == "points" && $old_percent < $plat_scores['points'] && $plat_scores['points'] > 0)) 
                {
		            $query = $this->connection->prepare("UPDATE platscores SET {$mode}=:{$mode}, timestamp=:timestamp WHERE accountID=:accountID AND levelID=:levelID");
	            } 
	            else 
	            {
		            $query = $this->connection->prepare("SELECT count(*) FROM platscores WHERE {$mode} = :{$mode} AND timestamp = :timestamp AND accountID=:accountID AND levelID=:levelID");
	            }
            }
            
            $query->execute([':accountID' => $accountID, ':levelID' => $levelID, ":{$mode}" => $plat_scores[$mode], ':timestamp' => $this->uploadDate]);
        
            switch ($type)
            {
                case 0:
		            $friends = $this->Main->get_friends($accountID);
		            $friends[] = $accountID;
		            $friends = implode(",", $friends);

		            $query = $this->connection->prepare("SELECT * FROM platscores WHERE levelID = :levelID AND accountID IN ($friends) ORDER BY {$mode} DESC");
		            $query_args = [':levelID' => $levelID];
		            break;
	            
	            case 1:
		            $query = $this->connection->prepare("SELECT * FROM platscores WHERE levelID = :levelID ORDER BY {$mode} DESC");
		            $query_args = [':levelID' => $levelID];
		            break;
	            
	            case 2:
		            $query = $this->connection->prepare("SELECT * FROM platscores WHERE levelID = :levelID AND timestamp > :time ORDER BY {$mode} DESC");
		            $query_args = [':levelID' => $levelID, ':time' => $this->uploadDate - 604800];
		            break;
	            
	            default:
		            return -1;
            }

            $query->execute($query_args);
            $result = $query->fetchAll();

            $x = 0;

            foreach ($result as &$score)
            {
                $ext_id = $score["accountID"];
                $query = $this->connection->prepare("SELECT userName, userID, icon, color1, color2, color3, iconType, special, extID, isBanned FROM users WHERE extID = :extID");
                $query->execute([":extID" => $ext_id]);
                $user = $query->fetchAll();
                $user = $user[0];

                if ($user["isBanned"] != 0) continue;

                $x++;

                $time = $this->Lib->make_time($score["timestamp"]);
                $score_mode = $score[$mode];
                $levelString .= "1:{$user['userName']}:2:{$user['userID']}:9:{$user['icon']}:10:{$user['color1']}:11:{$user['color2']}:14:{$user['iconType']}:15:{$user['color3']}:16:{$ext_id}:3:{$score_mode}:6:{$x}:42:{$time}|";
            }

            $levelString = substr($levelString, 0, -1);

            return $levelString;
        }

        public function update(int $accountID, int $userID, string $hostname): string { return "lol"; }
    }

    class Leaderboard implements Scores {
        protected $connection;
        protected $Main, $Database, $Lib;

        private $uploadDate;
        public $gameVersion;

        public function __construct() {
            $this->Main = new Main();
            $this->Database = new Database();
            $this->Lib = new Lib();

            $this->connection = $this->Database->open_connection();
            $this->uploadDate = time();
        }

        
        public function getData($accountID, int $levelID, $type = null, $mode = "points", int $time = 0, int $points = 0, int $count = 0): string {
            $sign = (empty($this->gameVersion)) ? "< 20 AND gameVersion <> 0" : "> 19";
            $leaderboardString = "";
            $xi = 0;
            
            if ($type == "top" || $type == "creators" || $type == "relative")
            {
                switch($type) {
                    case "top":
                        $leaderboard = $this->connection->prepare("SELECT * FROM users WHERE isBanned = '0' AND gameVersion $sign AND stars > 0 ORDER BY stars DESC LIMIT 100");
                        $leaderboard->execute();
                        break;
                
                    case "creators":
                        $leaderboard = $this->connection->prepare("SELECT * FROM users WHERE isCreatorBanned = '0' AND creatorPoints > 0 ORDER BY creatorPoints DESC LIMIT 100");
                        $leaderboard->execute();
                        break;

                    case "relative":
                        $leaderboard = $this->connection->prepare("SELECT * FROM users WHERE extID = :accountID");
                        $leaderboard->execute([":accountID" => $accountID]);
                        $leaderboard = $leaderboard->fetchAll();
                        $user = $leaderboard[0];
                        $stars = $user["stars"];
                        $count = (isset($count)) ? $count : 50;
                        $count = floor($count / 2);
                        $leaderboard = $this->connection->prepare("SELECT	A.* FROM	(
                            (
                                SELECT	*	FROM users
                                WHERE stars <= :stars
                                AND isBanned = 0
                                AND gameVersion $sign
                                ORDER BY stars DESC
                                LIMIT $count
                            )
                            UNION
                            (
                                SELECT * FROM users
                                WHERE stars >= :stars
                                AND isBanned = 0
                                AND gameVersion $sign
                                ORDER BY stars ASC
                                LIMIT $count
                            )
                        ) as A
                        ORDER BY A.stars DESC");
                        $leaderboard->execute([":stars" => $stars]);
                        break;
                }
            
                $leaderboard_result = $leaderboard->fetchAll();
            
                if ($type == "relative")
                {
                    $user = $leaderboard_result[0];
                    $extID = $user["extID"];
                    $leaderboard = $this->connection->prepare("SET @rownum := 0;");
                    $leaderboard->execute();
                    $leaderboard = $this->connection->prepare("SELECT rank, stars FROM (
                        SELECT @rownum := @rownum + 1 AS rank, stars, extID, isBanned
                        FROM users WHERE isBanned = '0' AND gameVersion $sign ORDER BY stars DESC
                        ) as result WHERE extID = :extID");
                    $leaderboard->execute([":extID" => $extID]);
                    $leaderboard = $leaderboard->fetchAll();
                    $leaderboard = $leaderboard[0];
                    $xi = $leaderboard["rank"] - 1;
                }
                
                foreach($leaderboard_result as &$user) {
                    $extID = (is_numeric($user["extID"])) ? $user["extID"] : 0;
                    $xi++;
                    $leaderboardString .= "1:".$user["userName"].":2:".$user["userID"].":13:".$user["coins"].":17:".$user["userCoins"].":6:".$xi.":9:".$user["icon"].":10:".$user["color1"].":11:".$user["color2"].":51:".$user["color3"].":14:".$user["iconType"].":15:".$user["special"].":16:".$extID.":3:".$user["stars"].":8:".round($user["creatorPoints"], 0, PHP_ROUND_HALF_DOWN).":4:".$user["demons"].":7:".$extID.":46:".$user["diamonds"].":52:".$user["moons"]."|";
                }
            }

            if ($type == "friends") {
                $leaderboard = $this->connection->prepare("SELECT * FROM friendships WHERE person1 = :accountID OR person2 = :accountID");
                $leaderboard->execute([":accountID" => $accountID]);
                $leaderboard_result = $leaderboard->fetchAll();
                $users = "";

                foreach($leaderboard_result as &$friendship) {
                    $person = $friendship["person1"];
                    if ($person == $accountID) $person = $friendship["person2"];
                    $users .= ",".$person;
                }

                $leaderboard = $this->connection->prepare("SELECT * FROM users WHERE extID IN (:accountID $users) ORDER BY stars DESC");
                $leaderboard->execute([":accountID" => $accountID]);
                $leaderboard_result = $leaderboard->fetchAll();

                foreach($leaderboard_result as &$user) {
                    $extID = (is_numeric($user["extID"])) ? $user["extID"] : 0;
                    $xi++;
                    $leaderboardString .= "1:".$user["userName"].":2:".$user["userID"].":13:".$user["coins"].":17:".$user["userCoins"].":6:".$xi.":9:".$user["icon"].":10:".$user["color1"].":11:".$user["color2"].":14:".$user["iconType"].":15:".$user["special"].":16:".$extID.":3:".$user["stars"].":8:".round($user["creatorPoints"], 0, PHP_ROUND_HALF_DOWN).":4:".$user["demons"].":7:".$extID.":46:".$user["diamonds"]."|";
                }
            }

            if ($leaderboardString == "") return -1;

            $leaderboardString = substr($leaderboardString, 0, -1);

            return $leaderboardString;
        }

        public function update(int $accountID, int $userID, string $hostname): string { return "lol"; }
    }