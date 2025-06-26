<?php
    require_once __DIR__ ."/Main.php";


    require_once __DIR__ ."/lib/Database.php";
    require_once __DIR__ ."/lib/generateHash.php";

    interface Pack {
        public function getData(int $accountID = 0, int $page = 0, int $type = 0): string;
        public function deleteList(int $accountID, int $listID): string;
        public function upload(int $accountID, int $listID): string;
    }

    class Lists implements Pack {
        protected $connection;
        protected $Main, $Database;

        private $params, $order, $joins;

        public $followed, $difficulty, $demonFilter, $star, $featured, $string;
        public $listLevels, $listDescription, $listVersion, $original, $unlisted, $secret;
        public function __construct() {
            $this->Database = new Database();
            $this->Main = new Main();

            $this->connection = $this->Database->open_connection();
            $this->params = array("NOT unlisted = 1");
        }

        public function getData(int $accountID = 0, int $page = 0, int $type = 0): string {
            $userString = "";
            $levelString = "";

            if (!empty($this->star) || (!empty($this->featured) && $this->featured == 1)) $this->params[] = "NOT starStars = 0";
            
            switch ($this->difficulty) 
            {
                case -1:
                    $this->params[] = "starDifficulty = '-1'";
                    break;
                    
                case -2:
                    $this->params[] = "starDifficulty = 5+".$this->demonFilter;
                    break;
                
                case -3:
                    $this->params[] = "starDifficulty = '0'";
                    break;
                
                case "-":
                    break;
                    
                default:
                    if ($this->difficulty) $this->params[] = "starDifficulty IN ($this->difficulty)";
                    break;
            }
            
            // $this->params[] = "unlisted = 0";
            
            switch ($type)
            {
                case 0:
                    $this->order = "likes";
                    if (!empty($this->string)) $this->params[] = (is_numeric($this->string)) ? "listID = '$this->string'" : "listName LIKE '%$this->string%'";
                    break;
                    
                case 1:
                    $this->order = "downloads";
                    break;
                    
                case 2:
                    $this->order = "likes";
                    break;
                    
                case 3:
                    $this->order = "downloads";
                    $this->params[] = "lists.uploadDate > " . time() - 604800;
                    break;
                    
                case 4:
                    $this->order = "uploadDate";
                    break;
                
                case 5:
                    $this->params[] = "lists.accountID = '$this->string'";
                    break;
                
                case 6: 
                    $this->params[] = "lists.starStars > 0";
                    $this->params[] = "lists.starFeatured > 0";
                    $this->order = "downloads";
                    break;
                
                case 11:
                    $this->params[] = "lists.starStars > 0";
                    $this->order = "downloads";
                    break;
                
                case 12:
                    if (empty($this->followed)) $this->followed = 0;
                    $this->params[] = "lists.accountID IN ($this->followed)";
                    break;
                    
                case 13:
                    $friends = $this->Main->get_friends($accountID);
                    $friends = implode(",", $friends);
                    $this->params[] = "lists.accountID IN ($friends)";
                    break;
                
                case 7:
                case 27:
                    $this->params[] = "suggest.suggestLevelID < 0";
                    $this->order = "suggest.timestamp";
                    $this->joins = "LEFT JOIN suggest ON lists.listID*-1 LIKE suggest.suggestLevelId";
                    break;
            }
            
            $querybase = "FROM lists LEFT JOIN users ON lists.accountID LIKE users.extID $this->joins";

            if(!empty($this->params)) $querybase .= " WHERE (" . implode(" ) AND ( ", $this->params) . ")";

            $query = "SELECT lists.*, UNIX_TIMESTAMP(uploadDate) AS uploadDateUnix, UNIX_TIMESTAMP(updateDate) AS updateDateUnix, users.userID, users.userName, users.extID $querybase";
            
            if($this->order) $query .= "ORDER BY $this->order DESC";

            $query .= " LIMIT 10 OFFSET $page";
            $countquery = "SELECT count(*) $querybase";

            $query = $this->connection->prepare($query);
            $query->execute();

            $countquery = $this->connection->prepare($countquery);
            $countquery->execute();

            $totallvlcount = $countquery->fetchColumn();
            $result = $query->fetchAll();
            $levelcount = $query->rowCount();
            
            foreach ($result as &$list) 
            {
                if(!$list['uploadDateUnix']) $list['uploadDateUnix'] = 0;
	            if(!$list['updateDateUnix']) $list['updateDateUnix'] = 0;
	            $levelString .= "1:{$list['listID']}:2:{$list['listName']}:3:{$list['listDesc']}:5:{$list['listVersion']}:49:{$list['accountID']}:50:{$list['userName']}:10:{$list['downloads']}:7:{$list['starDifficulty']}:14:{$list['likes']}:19:{$list['starFeatured']}:51:{$list['listlevels']}:55:{$list['starStars']}:56:{$list['countForReward']}:28:{$list['uploadDateUnix']}:29:{$list['updateDateUnix']}"."|";
	            
	            $userID = $this->connection->prepare("SELECT userID FROM users WHERE extID = :accountID");
	            $userID->execute([":accountID" => $list["accountID"]]);

	            $userString .= $this->Main->get_user_string($userID->fetchColumn())."|";
            }
            
            if (empty($levelString)) return -1;
            
            if (!empty($this->string && is_numeric($this->string) && $levelcount == 1))
            {
                $ip = $this->Main->get_ip();
                $query6 = $this->connection->prepare("SELECT count(*) FROM actions_downloads WHERE levelID = :listID AND ip = INET6_ATON(:ip)");
                $query6->execute([':listID' => '-'.$this->string, ':ip' => $ip]);
                
                if ($query6->fetchColumn() < 2)
                {
                    $query2 = $this->connection->prepare("UPDATE lists SET downloads = downloads + 1 WHERE listID = :listID");
		            $query2->execute([':listID' => $this->string]);
		            $query6 = $this->connection->prepare("INSERT INTO actions_downloads (levelID, ip) VALUES (:listID, INET6_ATON(:ip))");
		            $query6->execute([':listID' => '-'.$this->string, ':ip' => $ip]);
                }
            }
            
            $levelString = substr($levelString, 0, -1);
            $userString = substr($userString, 0, -1);
            
            return $levelString."#".$userString."#".$totallvlcount.":".$page.":10#Sa1ntSosetHuiHelloFromGreenCatsServerLOL";
       
        }

        public function deleteList(int $accountID, int $listID): string {
            if (is_numeric($listID) && $accountID == $this->Main->get_owner_list($listID))
            {
                $list = $this->connection->prepare("DELETE FROM lists WHERE listID = :listID");
                $list->execute([":listID" => $listID]);

                return "1";
            }
            
            return "-1";
        }

        public function upload(int $accountID, int $listID): string {
            if ($this->secret !== "Wmfd2893gb7") return "-100";
            if (count(explode(",", $this->listLevels)) == 0) return "-6";
            if (!is_numeric($accountID)) return "-9";
            
            if ($listID != 0)
            {
                $list = $this->connection->prepare("SELECT * FROM lists WHERE listID = :listID AND accountID = :accountID");
                $list->execute([":listID" => $listID, ":accountID" => $accountID]);
                $list = $list->fetch();
                
                if (!empty($list))
                {
                    $list = $this->connection->prepare("UPDATE lists SET listDesc = :listDesc, listVersion = :listVersion, listlevels = :listlevels, starDifficulty = :difficulty, original = :original, unlisted = :unlisted, updateDate = :timestamp WHERE listID = :listID");
                    $list->execute([
                        ':listID' => $listID, 
                        ':listDesc' => $this->listDescription, 
                        ':listVersion' => $this->listVersion, 
                        ':listlevels' => $this->listLevels, 
                        ':difficulty' => $this->difficulty, 
                        ':original' => $this->original, 
                        ':unlisted' => $this->unlisted, 
                        ':timestamp' => time()
                    ]);
                    return $listID;
                }
            }
            
            $list = $this->connection->prepare("INSERT INTO lists (listName, listDesc, listVersion, accountID, listlevels, starDifficulty, original, unlisted, uploadDate) VALUES (:listName, :listDesc, :listVersion, :accountID, :listlevels, :difficulty, :original, :unlisted, :timestamp)");
            $list->execute([
                ':accountID' => $accountID,
                ':listName' => $listID,
                ':listDesc' => $this->listDescription, 
                ':listVersion' => $this->listVersion, 
                ':listlevels' => $this->listLevels, 
                ':difficulty' => $this->difficulty, 
                ':original' => $this->original, 
                ':unlisted' => $this->unlisted, 
                ':timestamp' => time()
            ]);
            
            $listID = $this->connection->lastInsertId();
            
            return $listID;
        }
    }

    class Gauntlets implements Pack {
        protected $connection;
        protected $Database;
        
        public function __construct() {
            $this->Database = new Database();

            $this->connection = $this->Database->open_connection();
        }

        public function getData(int $accountID = 0, int $page = 0, int $type = 0): string {
            $gauntletString = "";
            $string = "";
            
            $gauntlets = $this->connection->prepare("SELECT ID, level1, level2, level3, level4, level5 FROM gauntlets WHERE level5 != '0' ORDER BY ID ASC");
            $gauntlets->execute();
            $gauntlets_result = $gauntlets->fetchAll();

            foreach ($gauntlets_result as $gauntlet) {
                $levels = $gauntlet["level1"].",".$gauntlet["level2"].",".$gauntlet["level3"].",".$gauntlet["level4"].",".$gauntlet["level5"];
                $gauntletString .= "1:".$gauntlet['ID'].":3:".$levels."|";
                $string .= $gauntlet['ID'].$levels;
            }

            $gauntletString = substr($gauntletString, 0, -1);

            return $gauntletString."#".GenerateHash::genSolo2($string);
        }

        public function deleteList(int $accountID, int $listID): string { return "lol"; }
        public function upload(int $accountID, int $listID): string { return "lol"; }
    }

    class MapPacks {
        protected $connection;
        protected $Database;
        
        public function __construct() {
            $this->Database = new Database();

            $this->connection = $this->Database->open_connection();
        }

        public function getData(int $accountID = 0, int $page = 0, int $type = 0): string {
            $page = $page * 10;

            $map_packs = $this->connection->prepare("SELECT colors2, rgbcolors, ID, name, levels, stars, coins, difficulty FROM `mappacks` ORDER BY `ID` ASC LIMIT 10 OFFSET $page");
            $map_packs->execute();
            $map_packs_result = $map_packs->fetchAll();

            foreach ($map_packs_result as $map_pack) {
                $levels .= $map_pack["ID"].",";
                $color_2 = $map_pack["colors2"];

                if ($color_2 == "none" || $color_2 == "") $color_2 = $map_pack['rgbcolors'];

                $mappackString .= "1:".$map_pack["ID"].":2:".$map_pack["name"].":3:".$map_pack["levels"].":4:".$map_pack["stars"].":5:".$map_pack["coins"].":6:".$map_pack["difficulty"].":7:".$map_pack["rgbcolors"].":8:".$color_2."|";
            }

            $map_packs = $this->connection->prepare("SELECT count(*) FROM mappacks");
            $map_packs->execute();
            $total_map_packs = $map_packs->fetchColumn();

            $mappackString = substr($mappackString, 0, -1);
            $levels = substr($levels, 0, -1);

            return $mappackString."#".$total_map_packs.":".$page.":10#".GenerateHash::genPack($levels);
        }
        
        public function deleteList(int $accountID, int $listID): string { return "lol"; }
        public function upload(int $accountID, int $listID): string { return "lol"; }
    }