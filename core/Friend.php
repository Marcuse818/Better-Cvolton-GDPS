<?php
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/Lib.php";

    interface Friendships {
        public function accept(int $accountID, int $requestID): string;
        public function block(int $accountID, int $targetAccountID): string;
        public function read(int $accountID, int $requestID): string;
        public function delete(int $accountID, int $targetAccountID, int $isSender): string;
        public function remove(int $accountID, int $targetAccountID): string;
        public function unblock(int $accountID, int $targetAccountID): string;
        public function upload(int $accountID, int $targetAccountID, string $comment): string;
        public function getData(int $accountID, int $page, int $getSent): string;
        public function getDataList(int $accountID, int $type): string;
    }

    class Friend implements Friendships {
        protected $connection;
        protected $Lib, $Database;

        private $uploadDate;

        public function __construct() {
            $this->Database = new Database();
            $this->Lib = new Lib();

            $this->connection = $this->Database->open_connection();
            $this->uploadDate = time();
        }

        public function accept(int $accountID, int $requestID): string {
            $accept = $this->connection->prepare("SELECT accountID, toAccountID FROM friendreqs WHERE ID = :requestID");
            $accept->execute([":requestID" => $requestID]);
            $accept_request = $accept->fetch();
            
            if ($accept_request["toAccountID"] != $accountID || $accept_request["accountID"] == $accountID) return -1;

            $accept = $this->connection->prepare("INSERT INTO friendships (person1, person2, isNew1, isNew2) VALUES (:accountID, :targetAccountID, 1, 1)");
            $accept->execute([":accountID" => $accept_request["accountID"], ":targetAccountID" => $accept_request["toAccountID"]]);

            $accept = $this->connection->prepare("DELETE from friendreqs WHERE ID = :requestID LIMIT 1");
            $accept->execute([":requestID" => $requestID]);

            return "1";
        }

        public function block(int $accountID, int $targetAccountID): string {
            if ($accountID == $targetAccountID) return "-1";

            $block = $this->connection->prepare("INSERT INTO blocks (person1, person2) VALUES (:accountID, :targetAccountID)");
            $block->execute([":accountID" => $accountID, ":targetAccountID" => $targetAccountID]);

            return "1";
        }

        public function read(int $accountID, int $requestID): string {
            $friend_request = $this->connection->prepare("UPDATE friendreqs SET isNew = '0' WHERE ID = :requestID AND toAccountID = :targetAccountID");
            $friend_request->execute([":requestID" => $requestID, ":targetAccountID" => $accountID]);

            return "1";
        }

        public function delete(int $accountID, int $targetAccountID, int $isSender): string {
            $delete_friend = (isset($isSender) || $isSender == 1) ? 
                $this->connection->prepare("DELETE from friendreqs WHERE accountID = :accountID AND toAccountID = :targetAccountID LIMIT 1") : 
                $this->connection->prepare("DELETE from friendreqs WHERE toAccountID = :accountID AND accountID = :targetAccountID LIMIT 1");
            $delete_friend->execute([":accountID" => $accountID, ":targetAccountID" => $targetAccountID]);

            return "1";
        }

        public function remove(int $accountID, int $targetAccountID): string {
            $delete = $this->connection->prepare("DELETE FROM friendships WHERE person1 = :accountID AND person2 = :targetAccountID");
            $delete->execute([":accountID" => $accountID, ":targetAccountID" => $targetAccountID]);

            $delete = $this->connection->prepare("DELETE FROM friendships WHERE person2 = :accountID AND person1 = :targetAccountID");
            $delete->execute([":accountID" => $accountID, ":targetAccountID" => $targetAccountID]);

            return "1";
        }

        public function unblock(int $accountID, int $targetAccountID): string {
            $unblock = $this->connection->prepare("DELETE FROM blocks WHERE person1 = :accountID AND person2 = :targetAccountID");
            $unblock->execute([":accountID" => $accountID, ":targetAccountID" => $targetAccountID]);

            return "1";
        }

        public function upload(int $accountID, int $targetAccountID, string $comment): string {
            if ($accountID == $targetAccountID) return "-1";

            $blocked = $this->connection->prepare("SELECT ID FROM `blocks` WHERE person1 = :targetAccountID AND person2 = :accountID");
            $blocked->execute([":targetAccountID" => $targetAccountID, ":accountID" => $accountID]);
            $blocked = $blocked->fetchAll(PDO::FETCH_COLUMN);

            $friendsOnly = $this->connection->prepare("SELECT frS FROM `accounts` WHERE accountID = :targetAccountID AND frS = 1");
            $friendsOnly->execute([":targetAccountID" => $targetAccountID]);
            $friendsOnly = $friendsOnly->fetchAll(PDO::FETCH_COLUMN);

            $friendRequest = $this->connection->prepare("SELECT count(*) FROM friendreqs WHERE (accountID = :accountID AND toAccountID = :targetAccountID) OR (toAccountID = :accountID AND accountID = :targetAccountID)");
            $friendRequest->execute([":accountID" => $accountID, ":targetAccountID" => $targetAccountID]);

            if ($friendRequest->fetchColumn() == 0 && empty($blocked[0]) && empty($friendsOnly[0]))
            {
                $friend= $this->connection->prepare("INSERT INTO friendreqs (accountID, toAccountID, comment, uploadDate) VALUES (:accountID, :targetAccountID, :comment, :uploadDate)");
                $friend->execute(["accountID" => $accountID, ":targetAccountID" => $targetAccountID, ":comment" => $comment, ":uploadDate" => $this->uploadDate]);
                
                return "1";
            }
            
            return "-1";
        }

        public function getData(int $accountID, int $page, int $getSent): string {
            switch ($getSent) {
                case 0:
                    $friendRequest = $this->connection->prepare("SELECT accountID, toAccountID, uploadDate, ID, comment, isNew FROM friendreqs WHERE toAccountID = :accountID LIMIT 10 OFFSET $page");
                    $friendRequestCount = $this->connection->prepare("SELECT count(*) FROM friendreqs WHERE toAccountID = :accountID");
                    break;
                
                case 1:
                    $friendRequest = $this->connection->prepare("SELECT * FROM friendreqs WHERE accountID = :accountID LIMIT 10 OFFSET $page");
                    $friendRequestCount = $this->connection->prepare("SELECT count(*) FROM friendreqs WHERE accountID = :accountID");
                    break;
            }

            $friendRequest->execute([":accountID" => $accountID]);
            $friendRequestCount->execute([":accountID" => $accountID]);

            $friendRequest = $friendRequest->fetchAll();
            $friendRequestCount = $friendRequestCount->fetchColumn();

            if ($friendRequestCount == 0) return "-2";

            foreach ($friendRequest as &$request) {
                $requester = ($getSent == 0) ? $request["accountID"] : $request["toAccountID"];

                $user_info = $this->connection->prepare("SELECT userName, userID, icon, color1, color2, iconType, special, extID FROM users WHERE extID = :requester");
                $user_info->execute([":requester" => $requester]);
                $user_info = $user_info->fetchAll();

                $user = $user_info[0];
                $this->uploadDate = $this->Lib->make_time($request["uploadDate"]);
                $extID = (is_numeric($user["extID"])) ? $user["extID"] : 0;

                $requestString .= "1:".$user["userName"].":2:".$user["userID"].":9:".$user["icon"].":10:".$user["color1"].":11:".$user["color2"].":14:".$user["iconType"].":15:".$user["special"].":16:".$extID.":32:".$request["ID"].":35:".$request["comment"].":41:".$request["isNew"].":37:".$this->uploadDate."|";
            }

            $requestString = substr($requestString, 0, -1);

            return $requestString."#".$friendRequestCount.":".$page.":10";
        }

        public function getDataList(int $accountID, int $type): string {
            $friends = ($type == 0) ? 
                $this->connection->prepare("SELECT person1, isNew1, person2, isNew2 FROM friendships WHERE person1 = :accountID OR person2 = :accountID") :
                $this->connection->prepare("SELECT person1, person2 FROM blocks WHERE person1 = :accountID");

            $friends->execute([":accountID" => $accountID]);
            $friendsResult = $friends->fetchAll();

            if ($friends->rowCount() == 0) return "-2";

            foreach ($friendsResult as &$friend) {
                $person = $friend["person1"];
                $is_new = ($type == 0) ? $friend["isNew1"] : 0;

                if ($person == $accountID)
                {
                    $person = $friend["person2"];
                    $is_new = ($type == 0) ? $friend["isNew2"] : 0;
                }

                $new[$person] = $is_new;
                $users .= $person.",";
            }   

            $users = substr($users, 0, -1);

            $friend = $this->connection->prepare("SELECT userName, userID, icon, color1, color2, iconType, special, extID FROM users WHERE extID IN ($users) ORDER BY userName ASC");
            $friend->execute();
            $friendResult = $friend->fetchAll();

            foreach($friendResult as &$user) $userString .= "1:".$user["userName"].":2:".$user["userID"].":9:".$user["icon"].":10:".$user["color1"].":11:".$user["color2"].":14:".$user["iconType"].":15:".$user["special"].":16:".$user["extID"].":18:0:41:".$new[$user["extID"]]."|";

            $userString = substr($userString, 0, -1);

            $friendship = $this->connection->prepare("UPDATE friendships SET isNew1 = '0' WHERE person2 = :accountID");
            $friendship->execute([":accountID" => $accountID]);
            $friendship = $this->connection->prepare("UPDATE friendships SET isNew2 = '0' WHERE person1 = :accountID");
            $friendship->execute([":accountID" => $accountID]);

            if ($userString == "") return "-1";

            return $userString;
        }
    }