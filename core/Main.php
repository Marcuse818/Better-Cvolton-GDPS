<?php 
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/exploitPatch.php";
    require_once __DIR__."/lib/GJPCheck.php";
    include_once __DIR__."/lib/ip_in_range.php";

    require_once __DIR__."/../config/security.php";

    class Main extends SecurityConfig {
        private $connection;
        private $friends, $max_permission, $role_id_list;
        private $id, $user_id;

        public function __construct() {
            $new_con = new Database();

            $this->connection = $new_con->open_connection();
            $this->max_permission = 0;
        }

        public function getRolePermission(int $accountID, string $permission) 
        {
            if(!is_numeric($accountID)) return false;
            
            $roleID = $this->connection->prepare("SELECT roleID FROM roleassign WHERE accountID = :accountID");
            $roleID->execute([':accountID' => $accountID]);

            if ($roleID->rowCount() == 0) return false;

            $roleID = $roleID->fetch();

            $permissions = $this->connection->prepare("SELECT $permission FROM roles WHERE roleID = " . (int) $roleID["roleID"]);
            $permissions->execute();
            $permissions = $permissions->fetch();

            return $permissions[$permission];
        }

        public function feature_level($accountID, $levelID, $state) {
            if(!is_numeric($accountID)) return false;

            $states = $this->get_state($state);
            $featured = $states["featured"];
            $epic = $states["epic"];

            $feature = $this->connection->prepare("UPDATE levels SET starFeatured = :feature, starEpic = :epic WHERE levelID = :levelID");	
            $feature->execute([':feature' => $featured, ':epic' => $epic, ':levelID' => $levelID]);

            $feature = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('2', :value, :levelID, :timestamp, :id)");
            $feature->execute([':value' => $state, ':timestamp' => time(), ':id' => $accountID, ':levelID' => $levelID]);
        }

        public function get_state(int $state): array {
            switch ($state)
            {
                case 0: 
                    $featured = 0;
                    $epic = 0;
                    break;
                    
                case 1:
                    $featured = 1;
                    $epic = 0;
                    break;
                
                case 2:
                    $featured = 1;
                    $epic = 1;
                    break;
                    
                case 3:
                    $featured = 1;
                    $epic = 2;
                    break;
                    
                case 4: 
                    $featured = 1;
                    $epic = 3;
                    break;
            }
            
            
            return array("featured" => $featured, "epic" => $epic);
        }
        
        public function get_owner_list($list_id) {
            if (!is_numeric($list_id)) return false;
            
            $owner_list = $this->connection->prepare("SELECT accountID FROM lists WHERE listID = :id");
            $owner_list->execute([":id" => $list_id]);
            
            return $owner_list->fetchColumn();
        }
    
        public function get_list_levels($list_id) {
            if (!is_numeric($list_id)) return false;
        
            $list_levels = $this->connection->prepare("SELECT listlevels FROM lists WHERE listID = :id");
            $list_levels->execute([":id" => $list_id]);
            
            return $list_levels->fetchColumn();
        }
        
        public function get_list_difficulty_name($difficulty) {
            if ($difficulty == -1) return "N/A";
            
            $diffs = ['Auto', 'Easy', 'Normal', 'Hard', 'Harder', 'Insane', 'Easy Demon', 'Medium Demon', 'Hard Demon', 'Insane Demon', 'Extreme Demon'];
            
            return $diffs[$difficulty];
        }

        public function get_difficulty(int $stars = 0, string $name = "N/A", string $type = "name"): array {
            switch($type) {
                case "name":
                    $auto = ($name == "auto") ? 1 : 0;
                    $demon = ($name == "demon") ? 1 : 0;
                    
                    $difficulty = array(
                        "N/A" => 0,
                        "auto" => 50,
                        "easy" => 10,
                        "normal" => 20,
                        "hard" => 30,
                        "harder" => 40,
                        "insane" => 50,
                        "demon" => 50
                    );
                
                    return array($difficulty[$name], $demon, $auto);
                
                case "stars":
                    $auto = ($stars == 1) ? 1 : 0;
                    $demon = ($stars == 10) ? 1 : 0;

                    $difficulty_name = ["N/A", "Auto", "Easy", "Normal", "Hard", "Hard", "Harder", "Harder", "Insane", "Insane", "Demon"];
                    $difficulty = [0, 50, 10, 20, 30, 30, 40, 40, 50, 50, 50];

                    return array("difficulty" => $difficulty[$stars], "auto" => $auto, "demon" => $demon, "name" => $difficulty_name[$stars]);
            }

            return array(0, 0, 0);
        }
        
        public function get_list_levels_name($list_id) {
            if (!is_numeric($list_id)) return false;
            
            $list_levels_name = $this->connection->prepare("SELECT listName FROM lists WHERE listID = :id");
            $list_levels_name->execute([":id" => $list_id]);
            
            return $list_levels_name->fetchColumn();
        }
        
        public function get_post_id() {
            if(!empty($_POST["udid"]) && $_POST['gameVersion'] < 20 && self::$unregisteredSubmissions) 
            {
                $this->id = ExploitPatch::remove($_POST["udid"]);
                if(is_numeric($this->id)) exit("-1");
            }
            elseif(!empty($_POST["accountID"]) && $_POST["accountID"] != "0")
            {
                $this->id = GJPCheck::getAccountIDOrDie();
            }
            else
            {
                exit("-1");
            }

            return $this->id;
        }

        public function get_ip() {
            if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && $this->is_cloudflare_ip($_SERVER['REMOTE_ADDR']))
                return $_SERVER['HTTP_CF_CONNECTING_IP'];

            if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && ipInRange::ipv4_in_range($_SERVER['REMOTE_ADDR'], '127.0.0.0/8'))
                return $_SERVER['HTTP_X_FORWARDED_FOR'];

            return $_SERVER['REMOTE_ADDR'];
        }

        public function get_id_from_name(string $userName): int {
            $query = $this->connection->prepare("SELECT accountID FROM accounts WHERE userName LIKE :userName");
            $query->execute([':userName' => $userName]);

            $accountID = ($query->rowCount() > 0) ? $query->fetchColumn() : 0;

            return $accountID;
        }

        public function get_user_id($extID, $userName = "Undefined") {
            $register = (is_numeric($extID)) ? 1 : 0;
    
            $query = $this->connection->prepare("SELECT userID FROM users WHERE extID LIKE BINARY :id");
            $query->execute([':id' => $extID]);

            if ($query->rowCount() > 0) 
            {
                $this->user_id = $query->fetchColumn();
            } 
            else 
            {
                $query = $this->connection->prepare("INSERT INTO users (isRegistered, extID, userName, lastPlayed) VALUES (:register, :id, :userName, :uploadDate)");
    
                $query->execute([':id' => $extID, ':register' => $register, ':userName' => $userName, ':uploadDate' => time()]);
                $this->user_id = $this->connection->lastInsertId();
            }

            return $this->user_id;
        }

        public function get_friends($accountID) {
            if(!is_numeric($accountID)) return false;
    
            $query = $this->connection->prepare("SELECT person1,person2 FROM friendships WHERE person1 = :accountID OR person2 = :accountID");
            $query->execute([':accountID' => $accountID]);
            $result = $query->fetchAll();

            if($query->rowCount() == 0)
            {
                return array();
            }
            else
            {
                foreach($result as &$friendship) {
                    $person = $friendship["person1"];

                    if($friendship["person1"] == $accountID) $person = $friendship["person2"];
                    
                    $this->friends[] = $person;
                }
            }

            return $this->friends;
        }

        public function get_song_string($song) {
            if($song['ID'] == 0 || empty($song['ID'])) return false;
           
            if($song["isDisabled"] == 1) return false;

            $dl = $song["download"];

            if(strpos($dl, ':') !== false) $dl = urlencode($dl);
            
            return "1~|~".$song["ID"]."~|~2~|~".str_replace("#", "", $song["name"])."~|~3~|~".$song["authorID"]."~|~4~|~".$song["authorName"]."~|~5~|~".$song["size"]."~|~6~|~~|~10~|~".$dl."~|~7~|~~|~8~|~1";
        }
        
        public function get_user_string($userdata) {
            $extID = is_numeric($userdata['extID']) ? $userdata['extID'] : 0;
            return $userdata['userID'].":".$userdata['userName'].":".$extID;
        }

        public function is_cloudflare_ip($ip) {
            $cf_ips = array(
                '173.245.48.0/20',
                '103.21.244.0/22',
                '103.22.200.0/22',
                '103.31.4.0/22',
                '141.101.64.0/18',
                '108.162.192.0/18',
                '190.93.240.0/20',
                '188.114.96.0/20',
                '197.234.240.0/22',
                '198.41.128.0/17',
                '162.158.0.0/15',
                '104.16.0.0/13',
                '104.24.0.0/14',
                '172.64.0.0/13',
                '131.0.72.0/22'
            );

            foreach ($cf_ips as $cf_ip) if (ipInRange::ipv4_in_range($ip, $cf_ip)) return true;

            return false;
        }

        public function is_friends($accountID, $targetAccountID) {
            if(!is_numeric($accountID) || !is_numeric($targetAccountID)) return false;
    
            $friendships = $this->connection->prepare("SELECT count(*) FROM friendships WHERE person1 = :accountID AND person2 = :targetAccountID OR person1 = :targetAccountID AND person2 = :accountID");
            $friendships->execute([':accountID' => $accountID, ':targetAccountID' => $targetAccountID]);
            
            return $friendships->fetchColumn() > 0;
        }

        public function rate_level($accountID, $levelID, $stars, $difficulty, $auto, $demon) {
            if(!is_numeric($accountID)) return false;
    
            $query = $this->connection->prepare("UPDATE levels SET starDemon = :demon, starAuto = :auto, starDifficulty = :diff, starStars = :stars, rateDate = :now WHERE levelID = :levelID");	
            $query->execute([':demon' => $demon, ':auto' => $auto, ':diff' => $difficulty, ':stars' => $stars, ':levelID'=>$levelID, ':now' => time()]);
            
            $diff = $this->get_difficulty($stars, "", "stars");
            
            $query = $this->connection->prepare("INSERT INTO modactions (type, value, value2, value3, timestamp, account) VALUES ('1', :value, :value2, :levelID, :timestamp, :id)");
            $query->execute([':value' => $diff["name"], ':timestamp' => time(), ':id' => $accountID, ':value2' => $stars, ':levelID' => $levelID]);
        }

	    public function suggest_level($accountID, $levelID, $difficulty, $stars, $feat, $auto, $demon) {
		    if(!is_numeric($accountID)) return false;
		    
		    $state = $this->get_state($feat);
            	    $featured = $state["featured"];
           	    $epic = $state["epic"];
            
		    $rate_level = $this->connection->prepare("INSERT INTO sendLevel (accountID, levelID, difficulty, stars, featured, state, auto, demon, timestamp) VALUES (:account, :level, :diff, :stars, :featured, :state, :auto, :demon, :timestamp)");
		    $rate_level->execute([':account' => $accountID, ':level' => $levelID, ':diff' => $difficulty, ':stars' => $stars, ':featured' => $featured, ':state' => $epic, ':auto' => $auto, ':demon' => $demon, ':timestamp' => time()]);
	    }

        public function verify_coins($accountID, $levelID, $coins) {
            if(!is_numeric($accountID)) return false;
    
            $verify = $this->connection->prepare("UPDATE levels SET starCoins = :coins WHERE levelID = :levelID");	
            $verify->execute([':coins' => $coins, ':levelID' => $levelID]);
            
            $verify = $this->connection->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('3', :value, :levelID, :timestamp, :id)");
            $verify->execute([':value' => $coins, ':timestamp' => time(), ':id' => $accountID, ':levelID' => $levelID]);
        }

        public function add_gauntlet_level($gauntlet) {
            $levels_gauntlet = $this->connection->prepare("SELECT * FROM gauntlets WHERE ID = :gauntlet");
            $levels_gauntlet->execute([":gauntlet" => $gauntlet]);
            $levels_gauntlet = $levels_gauntlet->fetchAll(PDO::FETCH_ASSOC);

            foreach ($levels_gauntlet as &$level) {
                for ($x = 1; $x <= 5; $x++) {
                    $get_data = $this->connection->prepare('SELECT ID FROM gauntlets WHERE level' . $x . ' = :levelID');
                    $get_data->execute([':levelID' => $level['level' . $x]]);
                    $get_data = $get_data->fetch(PDO::FETCH_ASSOC);
                
                    $this->update_gauntlet_level($get_data['ID'], $x, $level['level' . $x]);
                }
            }
            
            return true;
        }

        public function update_gauntlet_level(int $gauntletID , int $levelPos, int $level) {
            $update = $this->connection->prepare("UPDATE levels SET gauntletID = :gauntletID, gauntletLevel = :gauntletLevel WHERE levelID = :levelID");
            $update->execute([":gauntletID" => $gauntletID, ":gauntletLevel" => $levelPos, ":levelID" => $level]);
        }

        public static function format_bytes($bytes, $precision = 2) { 
            $bytes = log($bytes, 1024); 
            $pow = pow(1024, $bytes - floor($bytes));  

            return round($pow, $precision); 
        }
    }
?>
