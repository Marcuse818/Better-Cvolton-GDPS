<?php
    require_once __DIR__."/Main.php";
    
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/Lib.php";
    
    interface Communication {
        public function getData(
            int $accountID = 0, 
            int $userID = 0, 
            int $page, 
            int $getSent = 0, 
            int $levelID = 0, 
            int $gameVersion = 0, 
            int $binaryVersion = 0
        ): string;
        public function delete(
            int $accountID, 
            int $userID, 
            int $permission = 0, 
            int $commentID = 0, 
            int $messageID = 0, 
            $messages = ""
        ): string;
        public function upload(
            int $accountID, 
            int $userID, 
            int $levelID = 0, 
            int $toAccountID = 0, 
            string $userName = "", 
            $comment = "", 
            string $subject = "", 
            string $body = "",
            string $secret = ""
        ): string;
        public function download(int $accountID, int $messageID, int $isSender): string;
    }

    class Message implements Communication {
        protected $connection;
        protected $Main, $Lib, $Database;

        private $uploadDate;

        public function __construct() {
            $this->Database = new Database();

            $this->Main = new Main();
            $this->Lib = new Lib();

            $this->connection = $this->Database->open_connection();
            $this->uploadDate = time();
        }

        public function download(int $accountID, int $messageID, int $isSender): string {
            $message = $this->connection->prepare("SELECT accID, toAccountID, timestamp, userName, messageID, subject, isNew, body FROM messages WHERE messageID = :messageID AND (accID = :accountID OR toAccountID = :accountID) LIMIT 1");
            $message->execute([":messageID" => $messageID, ":accountID" => $accountID]);
            $messageFetch = $message->fetch();
    
            if ($message->rowCount() == 0) return "-1";

            if (empty($isSender))
            {
                $message = $this->connection->prepare("UPDATE messages SET isNew = 1 WHERE messageID = :messageID AND toAccountID = :accountID");
                $message->execute([":messageID" => $messageID, ":accountID" => $accountID]);
                $accountID = $messageFetch['accID'];
                $isSender = 0;
            }
            else
            {
                $accountID = $messageFetch['toAccountID'];
                $isSender = 1;
            }

            $message = $this->connection->prepare("SELECT userName, userID, extID FROM users WHERE extID = :accountID");
            $message->execute([":accountID" => $accountID]);
            $userFetch = $message->fetch();
            
            $this->uploadDate = $this->Lib->make_time($messageFetch["timestamp"]);

            return "6:".$userFetch["userName"].":3:".$userFetch["userID"].":2:".$userFetch["extID"].":1:".$messageFetch["messageID"].":4:".$messageFetch["subject"].":8:".$messageFetch["isNew"].":9:".$isSender.":5:".$messageFetch["body"].":7:".$this->uploadDate."";
        }

        public function upload(int $accountID, int $userID, int $levelID = 0, int $toAccountID = 0, string $userName = "", $comment = "", string $subject = "", string $body = "", string $secret = ""): string {
            if ($accountID == $toAccountID) return -1;

            $message = $this->connection->prepare("SELECT userName FROM users WHERE extID = :accountID ORDER BY userName DESC");
            $message->execute([":accountID"=> $accountID]);
            $userName = $message->fetchColumn();

            $messageBlocked = $this->connection->prepare("SELECT ID FROM `blocks` WHERE person1 = $toAccountID AND person2 = $accountID")->fetchAll(PDO::FETCH_COLUMN);
            $messageMs = $this->connection->prepare("SELECT mS FROM `accounts` WHERE accountID = $accountID AND mS > 0")->fetchAll(PDO::FETCH_COLUMN);
            $messageFriend = $this->connection->prepare("SELECT ID FROM `friendships` WHERE (person1 = $accountID AND person2 = $toAccountID) || (person2 = $accountID AND person1 = $toAccountID)")->fetchAll(PDO::FETCH_COLUMN);

            $message = $this->connection->prepare("INSERT INTO messages (subject, body, accID, userID, userName, toAccountID, secret, timestamp) VALUES (:subject, :body, :accID, :userID, :userName, :toAccountID, :secret, :uploadDate)");

            if (!empty($messageMs[0]) && $messageMs[0] == 2) return -1;
            if (empty($messageBlocked[0]) && (empty($messageMs[0]) || !empty($messageFriend[0]))) 
            {
                $message->execute([':subject' => $subject, ':body' => $body, ':accID' => $accountID, ':userID' => $userID, ':userName' => $userName, ':toAccountID' => $toAccountID, ':secret' => $secret, ':uploadDate' => $this->uploadDate]);
                return "1";
            }

            return "-1";
        }
        
        public function getData(int $accountID = 0, int $userID = 0, int $page, int $getSent = 0, int $levelID = 0, int $gameVersion = 0, int $binaryVersion = 0): string {
            $page = $page * 10;

            if (!isset($getSent) || $getSent != 1) 
            {
                $message = $this->connection->prepare("SELECT * FROM messages WHERE toAccountID = :toAccountID ORDER BY messageID DESC LIMIT 10 OFFSET $page");
                $message->execute([":toAccountID"=> $accountID]);
                $messageCount = $this->connection->prepare("SELECT count(*) FROM messages WHERE toAccountID = :toAccountID");
                $messageCount->execute([":toAccountID"=> $accountID]);
                $getSent = 0;
            }
            else
            {
                $message = $this->connection->prepare("SELECT * FROM messages WHERE accID = :toAccountID ORDER BY messageID DESC LIMIT 10 OFFSET $page");
                $message->execute([":toAccountID"=> $accountID]);
                $messageCount = $this->connection->prepare("SELECT count(*) FROM messages WHERE accID = :toAccountID");
                $messageCount->execute([":toAccountID"=> $accountID]);
                $getSent = 1;
            }

            $messageFetchAll = $message->fetchAll();
            $messageCountFetchColumn = $messageCount->fetchColumn();

            if ($messageCountFetchColumn == 0) return "-2"; 

            foreach ($messageFetchAll as $messages) {
                if ($messages["messageID"] != 0)
                {
                    $this->uploadDate = $this->Lib->make_time($messages["timestamp"]);
                    $accountID = ($getSent == 1) ? $messages["toAccountID"] : $messages["accID"];
                }

                $message = $this->connection->prepare("SELECT * FROM users WHERE extID = :accountID");
                $message->execute([":accountID" => $accountID]);
                $messagesFetchAll = $message->fetchAll()[0];
                
                $messageString .="6:".$messagesFetchAll["userName"].":3:".$messagesFetchAll["userID"].":2:".$messagesFetchAll["extID"].":1:".$messages["messageID"].":4:".$messages["subject"].":8:".$messages["isNew"].":9:".$getSent.":7:".$this->uploadDate."|";
            }

            $messageString = substr($messageString, 0, -1);

            return $messageString."#".$messageCountFetchColumn.":".$page.":10";
        }

        public function delete(int $accountID, int $userID, int $permission = 0, int $commentID = 0, int $messageID = 0, $messages = ""): string {
            if (isset($messages)) 
            {
                $message = $this->connection->prepare("DELETE FROM messages WHERE messageID IN (".$messages.") AND accID = :accountID LIMIT 10");
                $message->execute([":accountID" => $accountID]);
                $message = $this->connection->prepare("DELETE FROM messages WHERE messageID IN (".$messages.") AND toAccountID = :accountID LIMIT 10");
                $message->execute([":accountID" => $accountID]);
            }
            else
            {
                $message = $this->connection->prepare("DELETE FROM messages WHERE messageID = :messageID AND accID = :accountID LIMIT 1");
                $message->execute([":messageID" => $messageID, ":accountID"=> $accountID]);
                $message = $this->connection->prepare("DELETE FROM messages WHERE messageID = :messageID AND toAccountID = :accountID LIMIT 1");
                $message->execute([":messageID" => $messageID, ":accountID"=> $accountID]);
            }

            return "1";
        }
    }

    class LevelComments implements Communication {
        protected $connection;
        protected $Main, $Lib, $Database;

        private $commentString, $userString, $uploadDate;
        private $modeColumn, $filterColumn, $filterToFilter, $displayLevelID, $filterID, $userListJoin, $userListWhere;

        public $count, $mode, $percent;

        public function __construct() {
            $this->Database = new Database();
            $this->Main = new Main();
            $this->Lib = new Lib();

            $this->connection = $this->Database->open_connection();
            $this->uploadDate = time();
        }

        public function getData(int $accountID = 0, int $userID = 0, int $page, int $getSent = 0, int $levelID = 0, int $gameVersion = 0, int $binaryVersion = 0): string {
            $page = $page * $this->count;
            $users = array();
            $commentString = "";

            $this->modeColumn = ($this->mode == 0) ? "timestamp" : "likes";

            if ($levelID) 
            {
                $this->filterColumn = 'levelID';
                $this->filterToFilter = '';
                $this->displayLevelID = false;
                $this->filterID = $levelID;
                $this->userListJoin = '';
                $this->userListWhere = '';
            } 
            elseif ($userID)
            {
                $this->filterColumn = 'userID';
                $this->filterToFilter = 'comments.';
                $this->displayLevelID = true;
                $this->filterID = $userID;
                $this->userListJoin = 'INNER JOIN levels ON comments.levelID = levels.levelID';
                $this->userListWhere = 'AND levels.unlisted = 0';
            } 
            else 
            {
                return "-1";
            }

            $commentCount = $this->connection->prepare("SELECT count(*) FROM comments $this->userListJoin WHERE $this->filterToFilter$this->filterColumn = :filterID $this->userListWhere");
            $commentCount->execute([":filterID" => $this->filterID]);
            $commentCount = $commentCount->fetchColumn();

            if ($commentCount == 0) return "-2";

            $comment = $this->connection->prepare("SELECT comments.levelID, comments.commentID, comments.timestamp, comments.comment, comments.userID, comments.likes, comments.isSpam, comments.percent, users.userName, users.icon, users.color1, users.color2, users.iconType, users.special, users.extID FROM comments LEFT JOIN users ON comments.userID = users.userID $this->userListJoin WHERE comments.$this->filterColumn = :filterID $this->userListWhere ORDER BY comments.$this->modeColumn DESC LIMIT $this->count OFFSET $page");
            $comment->execute([":filterID" => $this->filterID]);
            $commentResult = $comment->fetchAll();
            $commentVisible = $comment->rowCount();

            foreach ($commentResult as $comments) {
                if ($comments['commentID'] != 0) 
                {
                    $this->uploadDate = $this->Lib->make_time($comments["timestamp"]);
                    $commentText = ($gameVersion < 20) ? base64_decode($comments["comment"]) : $comments["comment"];

                    if ($this->displayLevelID) $commentString .= "1~".$comments["levelID"]."~";

                    $commentString .= "2~".$commentText."~3~".$comments["userID"]."~4~".$comments["likes"]."~5~0~7~".$comments["isSpam"]."~9~".$this->uploadDate."~6~".$comments["commentID"]."~10~".$comments["percent"];

                    if ($comments["extID"]) 
                    {
                        $extID = is_numeric($comments["extID"]) ? $comments['extID'] : 0;

                        if ($binaryVersion > 31)
                        {
                            $badge = $this->Main->getRolePermission($extID, "modBadgeLevel");
                            $colorString = $badge > 0 ? "~12~".$this->Main->getRolePermission($extID, "commentColor") : "";
                          
                            $commentString .= "~11~$badge$colorString:1~".$comments["userName"]."~7~1~9~".$comments["icon"]."~10~".$comments["color1"]."~11~".$comments["color2"]."~14~".$comments["iconType"]."~15~".$comments["special"]."~16~".$extID;
                        }
                        elseif (!in_array($comments["userID"], $users))
                        {
                            $users[] = $comments["userID"];
                            $userString .= $comments["userID"].":".$comments["userName"].":".$extID."|";
                        }

                        $commentString .= "|";
                    }
                }
            }

            $commentString = substr($commentString, 0, -1);

            if ($binaryVersion < 32) 
            {
                $userString = substr($userString, 0, -1);
                return "$commentString#$userString#$commentCount:$page:$commentVisible";
            }

            return "$commentString#$commentCount:$page:$commentVisible";
        }

        public function delete(int $accountID, int $userID, int $permission = 0, int $commentID = 0, int $messageID = 0, $messages = ""): string {
            $comment = $this->connection->prepare("DELETE FROM comments WHERE commentID = :commentID AND userID = :userID LIMIT 1");
            $comment->execute([":commentID" => $commentID, ":userID" => $userID]);

            if ($comment->rowCount() == 0)
            {
                $comment = $this->connection->prepare("SELECT users.extID FROM comments INNER JOIN levels ON levels.levelID = comments.levelID INNER JOIN users ON levels.userID = users.userID WHERE commentID = :commentID");
                $comment->execute([":commentID" => $commentID]);
                $commentFetchColumn = $comment->fetchColumn();

                if ($commentFetchColumn == $accountID || $permission) 
                {
                    $comment = $this->connection->prepare("DELETE FROM comments WHERE commentID = :commentID LIMIT 1");
                    $comment->execute([":commentID" => $commentID]);
                }
            }

            return "1";
        }

        public function upload(int $accountID, int $userID, int $levelID = 0, int $toAccountID = 0, string $userName = "", $comment = "", string $subject = "", string $body = "", string $secret = ""): string {
            if ($accountID != "" || $comment != "") 
            {
                $commentUpload = $this->connection->prepare("INSERT INTO comments (userName, comment, levelID, userID, timeStamp, percent) VALUES (:userName, :comment, :levelID, :userID, :uploadDate, :percent)");
                $commentUpload->execute([":userName" => $userName, ":comment" => $comment, ":levelID" => $levelID, ":userID" => $userID, ":uploadDate" => $this->uploadDate, ':percent' => $this->percent]);

                if (is_numeric($accountID)) 
                {
                    if ($this->percent != 0)
                    {
                        $commentUpload = $this->connection->prepare("SELECT percent FROM levelscores WHERE accountID = :accountID AND levelID = :levelID");
                        $commentUpload->execute([":accountID" => $accountID, ":levelID" => $levelID]);
                        $comment_result = $commentUpload->fetchColumn();

                        if ($commentUpload->rowCount() == 0)
                        {
                            $commentUpload = $this->connection->prepare("INSERT INTO levelscores (accountID, levelID, percent, uploadDate) VALUES (:accountID, :levelID, :percent, :uploadDate)");
                        }
                        else
                        {
                            if ($comment_result < $this->percent) 
                            {
                                $commentUpload = $this->connection->prepare("UPDATE levelscores SET percent = :percent, uploadDate = :uploadDate WHERE accountID = :accountID AND levelID = :levelID");
                                $commentUpload->execute([":percent" => $this->percent, ":uploadDate" => $this->uploadDate, ":accountID" => $accountID, ":levelID" => $levelID]);
                            }
                        }
                    }
                }
            }

            return "1";
        }


        public function download(int $accountID, int $messageID, int $isSender): string { return "lol"; }
    }