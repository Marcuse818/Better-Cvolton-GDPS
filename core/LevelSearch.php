<?php
    require_once __DIR__."/Level.php";
    require_once __DIR__."/Main.php";
    
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/generateHash.php";

    interface SearchInterface {
        public function search(
            int $accountID, 
            int $page, 
            int $type,
            $gameVersion = 0,
            $binaryVersion = 0,
            $difficulty = 0,
            $demonFilter = 0,
            $starFeatured = 0,
            $original = 0,
            $coins = 0,
            $starEpic = 0,
            $uncompleted = 0,
            $onlyCompleted = 0,
            $completedLevels = 0,
            $song = 0,
            $customSong = 0,
            $twoPlayer = 0,
            $star = 0,
            $noStar = 0,
            $gauntlet = 0,
            $len = 0,
            $legendary = 0,
            $mythic = 0,
            $followed = 0,
            $string = ""
        ): string;
    }
    
    class LevelSearch implements SearchInterface {
        protected $connection; 
        protected $Main, $Level, $Database;
        
        private $order, $params, $epicParams, $joins, $isGauntlet, $isSearchID;

        public function __construct() {
            $this->Database = new Database();
            $this->Main = new Main();

            $this->connection = $this->Database->open_connection();
        }

        public function search(
            int $accountID, 
            int $page, 
            int $type,
            $gameVersion = 0,
            $binaryVersion = 0,
            $difficulty = 0,
            $demonFilter = 0,
            $starFeatured = 0,
            $original = 0,
            $coins = 0,
            $starEpic = 0,
            $uncompleted = 0,
            $onlyCompleted = 0,
            $completedLevels = 0,
            $song = 0,
            $customSong = 0,
            $twoPlayer = 0,
            $star = 0,
            $noStar = 0,
            $gauntlet = 0,
            $len = 0,
            $legendary = 0,
            $mythic = 0,
            $followed = 0,
            $string = ""
        ): string {
            $levelsMultiString = array();
            $levelString = "";
            $songString = "";
            $userString = "";

            $this->order = "uploadDate";

            if (empty($gameVersion)) $gameVersion = 30;
            if (!is_numeric($gameVersion)) return "-1";
            
            if ($gameVersion == 20) 
            {
                if ($binaryVersion > 27) $gameVersion++;
            }
            
            if (empty($type)) $type = 0;
            if (empty($this->difficulty)) $this->difficulty = "-";
            
            $this->params[] = ($gameVersion == 0) ? "levels.gameVersion <= 18" : "levels.gameVersion <= '$gameVersion'";
           
            if (!empty($original)) $this->params[] = "original = 0";
            if (!empty($coins)) $this->params[] = "starCoins = 1 AND NOT levels.coins = 0";
            if (!empty($uncompleted)) $this->params[] = "NOT levelID IN ($completedLevels)";
            if (!empty($onlyCompleted)) $this->params[] = "levelID IN ($completedLevels)";
            
            if (!empty($song)) 
            {
                if (empty($customSong)) 
                {
                    $song = $song - 1;
                    $this->params[] = "audioTrack = '$song' AND songID = 0";
                } 
                else
                {
                    $this->params[] = "songID = '$song'";
                }
            }

            if (!empty($twoPlayer) && $twoPlayer == 1) $this->params[] = "twoPlayer = 1";
            if (!empty($star)) $this->params[] = "NOT starStars = 0";
            if (!empty($noStar)) $this->params[] = "starStars = 0";

            if (!empty($gauntlet)) 
            {
                $this->isGauntlet = true;
                $this->order = "starStars";
                $gauntlet_result = $this->connection->prepare("SELECT * FROM gauntlets WHERE ID = :gauntlet");
                $gauntlet_result->execute([':gauntlet' => $gauntlet]);
                $actualGauntlet = $gauntlet_result->fetch();
                $str = $actualGauntlet["level1"].",".$actualGauntlet["level2"].",".$actualGauntlet["level3"].",".$actualGauntlet["level4"].",".$actualGauntlet["level5"];
                $this->params[] = "levelID IN ($str)";
                $this->Main->add_gauntlet_level($gauntlet);
                $type = -1;
            }

            if (empty($len)) $len = "-";

            if ($len != "-" && !empty($len)) $this->params[] = "levelLength IN ($len)";
            
            if (!empty($starFeatured)) $this->epicParams[] = "starFeatured = 1";
            if (!empty($starEpic)) $this->epicParams[] = "starEpic = 1";
            if (!empty($mythic)) $this->epicParams[] = "starEpic = 2";
            if (!empty($legendary)) $this->epicParams[] = "starEpic = 3";
            
            if (!empty($starFeatured) || !empty($starEpic) || !empty($mythic) || !empty($legendary)) $epicFilter = implode(" OR ", $this->epicParams);
            
            if (!empty($epicFilter)) $this->params[] = $epicFilter;
            
            switch ($difficulty) 
            {
                case -1:
                    $this->params[] = "starDifficulty = '0'";
                    break;

                case -3: 
                    $this->params[] = "starAuto = '1'";
                    break;
                
                case -2:
                    if (empty($demonFilter)) $demonFilter = 0;
                    $this->params[] = "starDemon = 1";

                    switch ($demonFilter)
                    {
                        case 1:
                            $this->params[] = "starDemonDiff = '3'";
                            break;

                        case 2:
                            $this->params[] = "starDemonDiff = '4'";
                            break;

                        case 3:
                            $this->params[] = "starDemonDiff = '0'";
                            break;
                        
                        case 4:
                            $this->params[] = "starDemonDiff = '5'";
                            break;
                        
                        case 5:
                            $this->params[] = "starDemonDiff = '6'";
                            break;

                        default:
                            break;
                    }
                    break;

                case "-":
                    break;

                default:
                    if ($difficulty) 
                    {
                        $difficulty = str_replace(",", "0,", $difficulty)."0";
                        $this->params[] = "starDifficulty IN ($difficulty) AND starAuto = '0' AND starDemon = '0'";
                    }
                    break;
            }

            switch ($type) 
            {
                case 0:
                case 15:
                    $this->order = "likes";
                    if (!empty($string)) 
                    {
                        $this->params[] = (is_numeric($string)) ? "levelID = '$string'" : "levelName LIKE '%$string%'";
                        if (is_numeric($string)) $this->isSearchID = true;
                    }
                    break;

                case 1:
                    $this->order = "downloads";
                    break;

                case 2:
                    $this->order = 'likes';
                    break;

                case 3:
                    $uploadDate = time() - (7 * 24 * 60 * 60);
                    $this->params[] = "uploadDate > $uploadDate ";
                    $this->order = "likes";
                    break;
                
                case 5:
                    $this->params[] = ($string == 0) ? "levels.userID = '".$this->Main->get_user_id($accountID)."'" : "levels.userID = '$string'";
                    break;

                case 6:
                case 17:
                    $this->params[] = ($gameVersion > 21) ? "NOT starFeatured = 0 OR NOT starEpic = 0" : "NOT starFeatured = 0";  
                    $this->order = "rateDate DESC,uploadDate";
                    break;
                
                case 16:
                    $this->params[] = "NOT starEpic = 0";
                    $this->order = "rateDate DESC,uploadDate";
                    break;
                
                case 7:
                    $this->params[] = "objects > 9999";
                    break;
                
                case 10:
                case 19:
                    $this->order = false;
                    $this->params[] = "levelID IN ($string)";
                    break;

                case 11:
                    $this->params[] = "NOT starStars = 0";
                    $this->order = "rateDate DESC,uploadDate";
                    break;

                case 12: 
                    $this->params[] = "users.extID IN ($followed)";
                    break;
                
                case 13:
                    $accountID = GJPCheck::getAccountIDOrDie();
                    $peopleArray = $this->Main->get_friends($accountID);
                    $whereor = implode(",", $peopleArray);
                    $this->params[] = "users.extID IN ($whereor)";
                    break;

                case 21:
                    $this->joins = "INNER JOIN dailyfeatures ON levels.levelID = dailyfeatures.levelID";
                    $this->params[] = "dailyfeatures.type = 0";
                    $this->order = "dailyfeatures.feaID";
                    break;
                
                case 22:
                    $this->joins = "INNER JOIN dailyfeatures ON levels.levelID = dailyfeatures.levelID";
                    $this->params[] = "dailyfeatures.type = 1";
                    $this->order = "dailyfeatures.feaID";
                    break;

                case 23:
                    $this->joins = "INNER JOIN dailyfeatures ON levels.levelID = dailyfeatures.levelID";
                    $this->params[] = "dailyfeatures.type = 2";
                    $this->order = "dailyfeatures.feaID";
                    break;
                
                case 25:
                    $list_level = $this->Main->get_list_levels($string);
                    $this->params = array("levelID IN (".$list_level.")");
                    break;
                
                case 27:
                    $this->joins = "INNER JOIN sendLevel ON levels.levelID = sendLevel.levelID";
		            $this->params[] = "sendLevel.isRated = 0";
    		        $this->order = 'sendLevel.timestamp';
    		        break;
            }
            
            $unlst = null;
            $unlst_friend = 0;
            if (is_numeric($string) && $this->isSearchID) 
            {
                $unlst = $this->connection->prepare("SELECT unlisted FROM levels WHERE levelID = " . (int) $string);
                $unlst->execute();
                $unlst = $unlst->fetch();
            }
            
            if ($unlst != null)
            {
                $this->params[] = (($unlst["unlisted"] == 2) ? "unlisted = 2" :  (($unlst["unlisted"] == 1) ? "unlisted = 1" : "unlisted = 0"));
                if ($unlst["unlisted"] == 1) $unlst_friend = 1;
            }

            $level_query = "FROM levels LEFT JOIN songs ON levels.songID = songs.ID LEFT JOIN users ON levels.userID = users.userID $this->joins";
            if (!empty($this->params)) $level_query .= " WHERE (" . implode(") AND (", $this->params) . ")";
            
            $levels = "SELECT levels.*, songs.ID, songs.name, songs.authorID, songs.authorName, songs.size, songs.isDisabled, songs.download, users.userName, users.extID $level_query ";
            
            if ($this->order)
            {
                $levels .= ($this->isGauntlet) ? "ORDER BY gauntletLevel ASC" : "ORDER BY $this->order DESC";
            }
            
            if (!is_numeric($page)) $page = 0;

            $levels .= " LIMIT 10 OFFSET $page";
            $count_query = "SELECT count(*) $level_query";
            
            $levels = $this->connection->prepare($levels);
            $levels->execute();
           
            $count_query = $this->connection->prepare($count_query);
            $count_query->execute();

            $total_lvl_count = $count_query->fetchColumn();
            $level_result = $levels->fetchAll();

            foreach($level_result as $lvl) 
            {
                if ($lvl["levelID"])
                {
                    if ($unlst_friend == 1) 
                    {
                        if (!isset($accountID)) $accountID = GJPCheck::getAccountIDOrDie();
                        if (!$this->Main->is_friends($accountID, $lvl["extID"]) && $accountID != $lvl["extID"]) break;
                    }

                    $levelsMultiString[] = ["levelID" => $lvl["levelID"], "stars" => $lvl["starStars"], 'coins' => $lvl["starCoins"]];
                    if (!empty($gauntlet)) $levelString .= "44:$gauntlet:";
                    
                    $levelString .= "1:".$lvl["levelID"].":2:".$lvl["levelName"].":5:".$lvl["levelVersion"].":6:".$lvl["userID"].":8:10:9:".$lvl["starDifficulty"].":10:".$lvl["downloads"].":12:".$lvl["audioTrack"].":13:".$lvl["gameVersion"].":14:".$lvl["likes"].":17:".$lvl["starDemon"].":43:".$lvl["starDemonDiff"].":25:".$lvl["starAuto"].":18:".$lvl["starStars"].":19:".$lvl["starFeatured"].":42:".$lvl["starEpic"].":45:".$lvl["objects"].":3:".$lvl["levelDesc"].":15:".$lvl["levelLength"].":30:".$lvl["original"].":31:".$lvl['twoPlayer'].":37:".$lvl["coins"].":38:".$lvl["starCoins"].":39:".$lvl["requestedStars"].":46:1:47:2:40:".$lvl["isLDM"].":35:".$lvl["songID"]."|";
		
                    if ($lvl["songID"] != 0)
                    {
                        $song = $this->Main->get_song_string($lvl);
                        if ($song) $songString .= $song."~:~";
                    }
                    $userString .= $this->Main->get_user_string($lvl)."|";
                }
            }
            

            $levelString = substr($levelString, 0, -1);
            $userString = substr($userString, 0, -1);
            $songString = substr($songString, 0, -3);
            
            if ($gameVersion > 18) $e1 = "#".$songString;

            return $levelString."#".$userString.$e1."#".$total_lvl_count.":".$page.":10#".GenerateHash::genMulti($levelsMultiString);
        }
    }
