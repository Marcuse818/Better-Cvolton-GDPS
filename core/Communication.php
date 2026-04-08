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