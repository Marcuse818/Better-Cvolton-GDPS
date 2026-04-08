<?php
    require_once __DIR__."/Main.php";
    
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/GJPCheck.php";
    require_once __DIR__."/lib/Lib.php";
    
    interface AccountInterface {
        public function getData(int $accountID, int $targetAccountID): string;
        public function getUsers(string $string, int $page): string;
        public function update(
            int $accountID,
            int $privateMessage,
            int $privateFriend,
            int $privateHistory,
            string $youtube,
            string $twitch,
            string $twitter
        ): string;
    }
    
    class Account implements AccountInterface {
        protected $connection;
        protected $Main, $Lib, $Database;

        public function __construct() {
            $this->Database = new Database();
            $this->Main = new Main();
            $this->Lib = new Lib();

            $this->connection = $this->Database->open_connection();
        }

        public function getData(int $accountID, int $targetAccountID): string {
            $friend_state = 0;
            $string = "";
            
            $user_info = $this->connection->prepare("SELECT count(*) FROM blocks WHERE (person1 = :targetAccountID AND person2 = :accountID) OR (person2 = :targetAccountID AND person1 = :accountID)");
            $user_info->execute([":targetAccountID" => $targetAccountID, ":accountID" => $accountID]);
            
            if ($user_info->fetchColumn() > 0) return -1;

            $user_info = $this->connection->prepare("SELECT * FROM users WHERE extID = :targetAccountID");
            $user_info->execute([":targetAccountID" => $targetAccountID]);
            
            if ($user_info->rowCount() == 0) return -1;
            
            $user_stats = $user_info->fetch();
            $creator_point = round($user_stats['creatorPoints'], PHP_ROUND_HALF_DOWN);

            $user_info = $this->connection->prepare("SET @rownum := 0;");
            $user_info->execute();
            
            $user_info = $this->connection->prepare("SELECT count(*) FROM users WHERE stars > :stars AND isBanned = 0");
            $user_info->execute([":stars" => $user_stats["stars"]]);
            $global_rank = ($user_info->rowCount() > 0) ? $user_info->fetchColumn() + 1 : 0;

            if ($user_stats['isBanned']) $this->global_rank = 0;

            $account_info = $this->connection->prepare("SELECT youtubeurl, twitter, twitch, frS, mS, cS FROM accounts WHERE accountID = :targetAccountID");
            $account_info->execute([":targetAccountID" => $targetAccountID]);
            $account_info = $account_info->fetch();

            $private_friends = $account_info["frS"];
            $private_messages = $account_info["mS"];
            $private_comments = $account_info["cS"];
            $badge = $this->Main->getRolePermission($targetAccountID, "modBadgeLevel");

            if ($accountID == $targetAccountID) 
            {
                $friends_req_count = $this->connection->prepare("SELECT count(*) FROM friendreqs WHERE toAccountID = :targetAccountID");
                $friends_req_count->execute([":targetAccountID" => $accountID]);
                $friends_req_count = $friends_req_count->fetchColumn();

                $messages_count = $this->connection->prepare("SELECT count(*) FROM messages WHERE toAccountID = :targetAccountID AND isNew = 0");
                $messages_count->execute([":targetAccountID" => $accountID]);
                $messages_count = $messages_count->fetchColumn();

                $friends_count = $this->connection->prepare("SELECT count(*) FROM friendships WHERE (person1 = :targetAccountID AND isNew2 = '1') OR  (person2 = :targetAccountID AND isNew1 = '1')");
                $friends_count->execute([":targetAccountID" => $accountID]);
                $friends_count = $friends_count->fetchColumn();

                $friend_state = 0;
                $string = ":38:".$messages_count.":39:".$friends_req_count.":40:".$friends_count;
            }
            else
            {
                $friend_state = 0;

                $friend_request_info = $this->connection->prepare("SELECT ID, comment, uploadDate FROM friendreqs WHERE accountID = :targetAccountID AND toAccountID = :accountID");
                $friend_request_info->execute([":targetAccountID" => $targetAccountID, ":accountID" => $accountID]);
                $friend_request_count = $friend_request_info->rowCount();
                $friend_request_info = $friend_request_info->fetch();

                if ($friend_request_count > 0)
                {
                    $uploadDate = $this->Lib->make_time($friend_request_info["uploadDate"]);
                    $friend_state = 3;
                }

                $friend_request_out = $this->connection->prepare("SELECT count(*) FROM friendreqs WHERE toAccountID = :targetAccountID AND accountID = :accountID");
                $friend_request_out->execute([":targetAccountID" => $targetAccountID, ":accountID" => $accountID]);
                $friend_request_out = $friend_request_out->fetchColumn();
                
                if ($friend_request_out > 0) $friend_state = 4;

                $friends_state = $this->connection->prepare("SELECT count(*) FROM friendships WHERE (person1 = :accountID AND person2 = :targetAccountID) OR (person2 = :accountID AND person1 = :targetAccountID)");
                $friends_state->execute([":targetAccountID" => $targetAccountID, ":accountID" => $accountID]);
                $friends_state = $friends_state->fetchColumn();

                if ($friends_state > 0) $friend_state = 1;

                if ($friend_request_count > 0) 
                {
                    $string = ":32:".$friend_request_info["ID"].":35:".$friend_request_info["comment"].":37:".$uploadDate;
                }
            }
            
            return "1:".$user_stats["userName"].":2:".$user_stats["userID"].":13:".$user_stats["coins"].":17:".$user_stats["userCoins"].":10:".$user_stats["color1"].":11:".$user_stats["color2"].":51:".$user_stats["color3"].":3:".$user_stats["stars"].":46:".$user_stats["diamonds"].":52:".$user_stats["moons"].":4:".$user_stats["demons"].":8:".$creator_point.":18:".$private_messages.":19:".$private_friends.":50:".$private_comments.":20:".$account_info["youtubeurl"].":21:".$user_stats["accIcon"].":22:".$user_stats["accShip"].":23:".$user_stats["accBall"].":24:".$user_stats["accBird"].":25:".$user_stats["accDart"].":26:".$user_stats["accRobot"].":28:".$user_stats["accGlow"].":43:".$user_stats["accSpider"].":47:".$user_stats["accExplosion"].":53:".$user_stats["accSwing"].":54:".$user_stats["accJetpack"].":30:".$global_rank.":16:".$targetAccountID.":31:".$friend_state.":44:".$account_info["twitter"].":45:".$account_info["twitch"].":29:1:49:".$badge . $string;
        }

        public function getUsers(string $string, int $page): string {
            $users = $this->connection->prepare("SELECT userName, userID, coins, userCoins, icon, color1, color2, color3, iconType, special, extID, stars, creatorPoints, demons, diamonds, moons FROM users WHERE userID = :str OR userName LIKE CONCAT('%', :str, '%') ORDER BY stars DESC LIMIT 10 OFFSET $page");
            $users->execute([":str" => $string]);
            $users = $users->fetchAll();

            if (count($users) < 1) return -1;

            $users_count = $this->connection->prepare("SELECT count(*) FROM users WHERE userName LIKE CONCAT('%', :str, '%')");
            $users_count->execute([":str" => $string]);
            $users_count = $users_count->fetchColumn();

            foreach ($users as &$user) {
                $userString .= "1:".$user["userName"].":2:".$user["userID"].":13:".$user["coins"].":17:".$user["userCoins"].":9:".$user["icon"].":10:".$user["color1"].":11:".$user["color2"].":51:".$user["color3"].":14:".$user["iconType"].":15:".$user["special"].":16:".(is_numeric($user["extID"]) ? $user["extID"] : 0).":3:".$user["stars"].":8:".round($user["creatorPoints"],0,PHP_ROUND_HALF_DOWN).":4:".$user["demons"].":46:".$user["diamonds"].":52:".$user["moons"]."|";
            }
            
            $userString = substr($userString, 0, -1);

            return $userString."#".$users_count.":".$page.":10"; 
        }
        
        public function update(int $accountID, int $privateMessage, int $privateFriend, int $privateHistory, string $youtube, string $twitch, string $twitter): string {
            $update = $this->connection->prepare("UPDATE accounts SET mS = :mS, frS = :frS, cS = :cS, youtubeurl = :youtubeurl, twitter = :twitter, twitch = :twitch WHERE accountID = :accountID");
            $update->execute([':accountID' => $accountID, ':mS' => $privateMessage, ':frS' => $privateFriend, ':cS' => $privateHistory, ':youtubeurl' => $youtube, ':twitch' => $twitch, ':twitter' => $twitter]);

            return 1;
        }
    }
