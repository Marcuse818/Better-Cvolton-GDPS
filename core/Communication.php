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
        string $messages = ""
    ): string;
    
    public function upload(
        int $accountID, 
        int $userID, 
        int $levelID = 0, 
        int $toAccountID = 0, 
        string $userName = "", 
        string $comment = "", 
        string $subject = "", 
        string $body = "",
        string $secret = ""
    ): string;
    
    public function download(int $accountID, int $messageID, int $isSender): string;
}

class Message implements Communication {
    protected $database;
    protected $main, $lib;

    public function __construct() {
        $this->database = new Database();
        $this->main = new Main();
        $this->lib = new Lib();
    }

    public function download(int $accountID, int $messageID, int $isSender): string {
        try {
            $message = $this->database->fetch_one(
                "SELECT accID, toAccountID, timestamp, userName, messageID, subject, isNew, body 
                 FROM messages 
                 WHERE messageID = :messageID AND (accID = :accountID OR toAccountID = :accountID) 
                 LIMIT 1",
                [":messageID" => $messageID, ":accountID" => $accountID]
            );

            if (!$message) return "-1";

            if (empty($isSender)) {
                $this->database->execute(
                    "UPDATE messages SET isNew = 1 WHERE messageID = :messageID AND toAccountID = :accountID",
                    [":messageID" => $messageID, ":accountID" => $accountID]
                );
                $targetAccountID = $message['accID'];
                $isSender = 0;
            } else {
                $targetAccountID = $message['toAccountID'];
                $isSender = 1;
            }

            $user = $this->database->fetch_one(
                "SELECT userName, userID, extID FROM users WHERE extID = :accountID",
                [":accountID" => $targetAccountID]
            );

            if (!$user) return "-1";

            $uploadDate = $this->lib->make_time($message["timestamp"]);

            return "6:".$user["userName"].":3:".$user["userID"].":2:".$user["extID"].":1:".$message["messageID"].":4:".$message["subject"].":8:".$message["isNew"].":9:".$isSender.":5:".$message["body"].":7:".$uploadDate;

        } catch (Exception $e) {
            error_log("Message download error: " . $e->getMessage());
            return "-1";
        }
    }

    public function upload(int $accountID, int $userID, int $levelID = 0, int $toAccountID = 0, string $userName = "", string $comment = "", string $subject = "", string $body = "", string $secret = ""): string {
        try {
            if ($accountID == $toAccountID) return "-1";

            $userName = $this->database->fetch_column(
                "SELECT userName FROM users WHERE extID = :accountID ORDER BY userName DESC LIMIT 1",
                [":accountID" => $accountID]
            );

            if (!$userName) return "-1";

            $isBlocked = $this->database->exists(
                "blocks", 
                "person1 = :toAccountID AND person2 = :accountID",
                [":toAccountID" => $toAccountID, ":accountID" => $accountID]
            );

            $messageSettings = $this->database->fetch_column(
                "SELECT mS FROM accounts WHERE accountID = :accountID AND mS > 0",
                [":accountID" => $accountID]
            );

            $isFriend = $this->database->exists(
                "friendships",
                "(person1 = :accountID AND person2 = :toAccountID) OR (person2 = :accountID AND person1 = :toAccountID)",
                [":accountID" => $accountID, ":toAccountID" => $toAccountID]
            );

            if (!empty($messageSettings) && $messageSettings == 2) return "-1";
            if ($isBlocked || (empty($messageSettings) && empty($isFriend))) return "-1";

            $this->database->insert(
                "INSERT INTO messages (subject, body, accID, userID, userName, toAccountID, secret, timestamp) 
                 VALUES (:subject, :body, :accID, :userID, :userName, :toAccountID, :secret, :uploadDate)",
                [
                    ':subject' => $subject, 
                    ':body' => $body, 
                    ':accID' => $accountID, 
                    ':userID' => $userID, 
                    ':userName' => $userName, 
                    ':toAccountID' => $toAccountID, 
                    ':secret' => $secret, 
                    ':uploadDate' => time()
                ]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Message upload error: " . $e->getMessage());
            return "-1";
        }
    }
    
    public function getData(int $accountID = 0, int $userID = 0, int $page, int $getSent = 0, int $levelID = 0, int $gameVersion = 0, int $binaryVersion = 0): string {
        try {
            $offset = $page * 10;
            $messageString = "";

            if (!isset($getSent) || $getSent != 1) {
                $messages = $this->database->fetch_all(
                    "SELECT * FROM messages 
                     WHERE toAccountID = :accountID 
                     ORDER BY messageID DESC 
                     LIMIT 10 OFFSET :offset",
                    [":accountID" => $accountID, ":offset" => $offset]
                );
                
                $totalCount = $this->database->count("messages", "toAccountID = :accountID", [":accountID" => $accountID]);
                $getSent = 0;
            } else {
                $messages = $this->database->fetch_all(
                    "SELECT * FROM messages 
                     WHERE accID = :accountID 
                     ORDER BY messageID DESC 
                     LIMIT 10 OFFSET :offset",
                    [":accountID" => $accountID, ":offset" => $offset]
                );
                
                $totalCount = $this->database->count("messages", "accID = :accountID", [":accountID" => $accountID]);
                $getSent = 1;
            }

            if (empty($messages)) return "-2";

            foreach ($messages as $message) {
                $targetAccountID = ($getSent == 1) ? $message["toAccountID"] : $message["accID"];
                
                $user = $this->database->fetch_one(
                    "SELECT * FROM users WHERE extID = :accountID",
                    [":accountID" => $targetAccountID]
                );

                if (!$user) continue;

                $uploadDate = $this->lib->make_time($message["timestamp"]);
                $messageString .= "6:".$user["userName"].":3:".$user["userID"].":2:".$user["extID"].":1:".$message["messageID"].":4:".$message["subject"].":8:".$message["isNew"].":9:".$getSent.":7:".$uploadDate."|";
            }

            $messageString = rtrim($messageString, "|");

            return $messageString."#".$totalCount.":".$offset.":10";

        } catch (Exception $e) {
            error_log("Message getData error: " . $e->getMessage());
            return "-1";
        }
    }

    public function delete(int $accountID, int $userID, int $permission = 0, int $commentID = 0, int $messageID = 0, string $messages = ""): string {
        try {
            if (!empty($messages)) {
                $messageIDs = explode(",", $messages);
                $placeholders = implode(",", array_fill(0, count($messageIDs), "?"));
                
                $this->database->execute(
                    "DELETE FROM messages WHERE messageID IN ($placeholders) AND accID = ? LIMIT 10",
                    array_merge($messageIDs, [$accountID])
                );
                
                $this->database->execute(
                    "DELETE FROM messages WHERE messageID IN ($placeholders) AND toAccountID = ? LIMIT 10",
                    array_merge($messageIDs, [$accountID])
                );
            } else {
                $this->database->execute(
                    "DELETE FROM messages WHERE messageID = :messageID AND accID = :accountID LIMIT 1",
                    [":messageID" => $messageID, ":accountID" => $accountID]
                );
                
                $this->database->execute(
                    "DELETE FROM messages WHERE messageID = :messageID AND toAccountID = :accountID LIMIT 1",
                    [":messageID" => $messageID, ":accountID" => $accountID]
                );
            }

            return "1";

        } catch (Exception $e) {
            error_log("Message delete error: " . $e->getMessage());
            return "-1";
        }
    }
}