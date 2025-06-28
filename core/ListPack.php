<?php
    require_once __DIR__ ."/Main.php";


    require_once __DIR__ ."/lib/Database.php";
    require_once __DIR__ ."/lib/generateHash.php";

    interface ListInterface {
        public function get_data(int $account_id, int $page, int $type): string;
        public function delete_list(int $account_id, int $list_id): string;
    }

    class Lists implements ListInterface {
        private $connection, $main;
        public $followed, $difficulty, $demon_filter, $star, $levels_list, $secret, $featured, $string;
        public $list_description, $list_version, $list_name, $original, $unlisted;

        public function __construct() {
            $database = new Database();
            $main = new Main();
            $connection = $database->open_connection();
        }

        public function get_data(int $account_id, int $page, int $type): string {
            $user_string = "";
            $level_string = "";
            $params = [];
            $order = "";
            $joins = "";

            if (!empty($this->star) || (!empty($this->featured) && $this->featured == 1)) $params[] = "NOT starStars = 0";

            switch ($this->difficulty) {
                case -1: 
                    $params[] = "starDifficulty = '-1'";
                    break;

                case -2:
                    $params = "starDifficulty = 5+".$this->demon_filter;
                    break;

                case -3:
                    $params = "starDifficulty = '0'";
                    break;

                case "-": break;

                default:
                    if ($this->difficulty) $params[] = "starDifficulty IN ($this->difficulty)";
                    break;
            }

            switch ($type) {
                case 0:
                    $order = "likes";
                    if (!empty($this->string)) $params[] = (is_numeric($this->string)) ? "listID = '$this->string'" : "listName LIKE '%$this->string%'";
                    break;

                case 1:
                    $order = "downloads";
                    break;

                case 2: $order = "likes"; break;
                
                case 3:
                    $order = "downloads";
                    $params[] = "lists.uploadDate > " . time() - 604800;
                    break;

                case 4: $order = "uploadDate"; break;
                case 5: $params[] = "lists.accountID = '$this->string'"; break;

                case 6:
                    $params[] = "lists.starStars > 0";
                    $params[] = "lists.starFeatured > 0";
                    $order = "downloads";
                    break;

                case 11:
                    $params[] = "lists.starStars > 0";
                    $order = "downloads";
                    break;

                case 12:
                    if (empty($this->followed)) $this->followed = 0;
                    $params[] = "lists.accountID IN ($this->followed)";
                    break;
                    
                case 13:
                    $friends = $this->main->get_friends($account_id);
                    $friends = implode(",", $friends);
                    $params[] = "lists.accountID IN ($friends)";
                    break;
                
                case 7:
                case 27:
                    $params[] = "suggest.suggestLevelID < 0";
                    $order = "suggest.timestamp";
                    $joins = "LEFT JOIN suggest ON lists.listID*-1 LIKE suggest.suggestLevelId";
                    break;
            }

            $querybase = "FROM lists LEFT JOIN users ON lists.accountID LIKE users.extID $joins";

            if(!empty($params)) $querybase .= " WHERE (" . implode(" ) AND ( ", $params) . ")";

            $query = "SELECT lists.*, UNIX_TIMESTAMP(uploadDate) AS uploadDateUnix, UNIX_TIMESTAMP(updateDate) AS updateDateUnix, users.userID, users.userName, users.extID $querybase";

            if($order) $query .= "ORDER BY $order DESC";
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
	            $level_string .= "1:{$list['listID']}:2:{$list['listName']}:3:{$list['listDesc']}:5:{$list['listVersion']}:49:{$list['accountID']}:50:{$list['userName']}:10:{$list['downloads']}:7:{$list['starDifficulty']}:14:{$list['likes']}:19:{$list['starFeatured']}:51:{$list['listlevels']}:55:{$list['starStars']}:56:{$list['countForReward']}:28:{$list['uploadDateUnix']}:29:{$list['updateDateUnix']}"."|";
	            
	            $userID = $this->connection->prepare("SELECT userID FROM users WHERE extID = :accountID");
	            $userID->execute([":accountID" => $list["accountID"]]);

	            $user_string .= $this->main->get_user_string($userID->fetchColumn())."|";
            }
            
            if (empty($level_string)) return -1;
            
            if (!empty($this->string && is_numeric($this->string) && $levelcount == 1))
            {
                $ip = $this->main->get_ip();
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
            
            $level_string = substr($level_string, 0, -1);
            $user_string = substr($user_string, 0, -1);
            
            return $level_string."#".$user_string."#".$totallvlcount.":".$page.":10#Sa1ntSosetHuiHelloFromGreenCatsServerLOL";
    
        }

        public function delete_list(int $account_id, int $list_id): string {
            if (is_numeric($list_id) && $account_id == $this->main->get_owner_list($list_id)) {
                $list = $this->connection->prepare("DELETE FROM lists WHERE listID = :listID");
                $list->execute([":listID" => $list_id]);
                return "1";
            }

            return "-1";
        }

        public function upload_list(int $account_id, int $list_id): string {
            if ($this->secret != "Wmfd2893gb7") return "-100";
            if (count(explode(",", $this->levels_list)) == 0) return "-6";
            if (!is_numeric($account_id)) return "-9";

            if ($list_id != 0) {
                $list = $this->connection->prepare("SELECT * FROM lists WHERE listID = :listID AND accountID = :accountID");
                $list->execute([":listID"=> $list_id, ":accountID" => $account_id]);
                $list = $list->fetch();

                if (!empty($list)) {
                    $list = $this->connection->prepare("UPDATE lists SET listDesc = :listDesc, listVersion = :listVersion, listlevels = :listlevels, starDifficulty = :difficulty, original = :original, unlisted = :unlisted, updateDate = :timestamp WHERE listID = :listID");
                    $list->execute([
                        ':listID' => $list_id, 
                        ':listDesc' => $this->list_description, 
                        ':listVersion' => $this->list_version, 
                        ':listlevels' => $this->levels_list, 
                        ':difficulty' => $this->difficulty, 
                        ':original' => $this->original, 
                        ':unlisted' => $this->unlisted, 
                        ':timestamp' => time()
                    ]);

                    return $list_id;
                }
            }

            $list = $this->connection->prepare('INSERT INTO lists (listName, listDesc, listVersion, accountID, listlevels, starDifficulty, original, unlisted, uploadDate) VALUES (:listName, :listDesc, :listVersion, :accountID, :listlevels, :difficulty, :original, :unlisted, :timestamp)');
            $list->execute([
                ':accountID' => $account_id,
                ':listName' => $this->list_name,
                ':listDesc' => $this->list_description, 
                ':listVersion' => $this->list_version, 
                ':listlevels' => $this->levels_list, 
                ':difficulty' => $this->difficulty, 
                ':original' => $this->original, 
                ':unlisted' => $this->unlisted, 
                ':timestamp' => time()
            ]);
            
            $list_id = $this->connection->lastInsertId();

            return $list_id;
        }
    }
?>