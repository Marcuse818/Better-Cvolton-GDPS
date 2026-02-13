<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/Lib.php";

interface Communication {
    public function getData(
        int $accountId = 0, 
        int $userId = 0, 
        int $page, 
        int $getSent = 0, 
        int $levelId = 0, 
        int $gameVersion = 0, 
        int $binaryVersion = 0
    ): string;
    
    public function delete(
        int $accountId, 
        int $userId, 
        int $permission = 0, 
        int $commentId = 0, 
        int $messageId = 0, 
        string $messages = ""
    ): string;
    
    public function upload(
        int $accountId, 
        int $userId, 
        int $levelId = 0, 
        int $toAccountId = 0, 
        string $userName = "", 
        string $comment = "", 
        string $subject = "", 
        string $body = "",
        string $secret = ""
    ): string;
    
    public function download(int $accountId, int $messageId, int $isSender): string;
}

class Message implements Communication {
    protected Database $database;
    protected Main $main;
    protected Lib $lib;

    public function __construct() {
        $this->database = new Database();
        $this->main = new Main();
        $this->lib = new Lib();
    }

    public function download(int $accountId, int $messageId, int $isSender): string {
        try {
            $message = $this->database->fetchOne(
                "SELECT accID, toAccountID, timestamp, userName, messageID, subject, isNew, body 
                 FROM messages 
                 WHERE messageID = ? AND (accID = ? OR toAccountID = ?) 
                 LIMIT 1",
                [$messageId, $accountId, $accountId]
            );

            if (!$message) {
                return "-1";
            }

            if (empty($isSender)) {
                $this->database->update(
                    'messages',
                    ['isNew' => 1],
                    'messageID = ? AND toAccountID = ?',
                    [$messageId, $accountId]
                );
                $targetAccountId = $message['accID'];
                $isSender = 0;
            } else {
                $targetAccountId = $message['toAccountID'];
                $isSender = 1;
            }

            $user = $this->database->fetchOne(
                "SELECT userName, userID, extID FROM users WHERE extID = ?",
                [$targetAccountId]
            );

            if (!$user) {
                return "-1";
            }

            $uploadDate = $this->lib->makeTime($message["timestamp"]);

            return sprintf(
                "6:%s:3:%d:2:%d:1:%d:4:%s:8:%d:9:%d:5:%s:7:%s",
                $user["userName"],
                $user["userID"],
                $user["extID"],
                $message["messageID"],
                $message["subject"],
                $message["isNew"],
                $isSender,
                $message["body"],
                $uploadDate
            );

        } catch (Exception $e) {
            error_log("Message download error: " . $e->getMessage());
            return "-1";
        }
    }

    public function upload(int $accountId, int $userId, int $levelId = 0, int $toAccountId = 0, string $userName = "", string $comment = "", string $subject = "", string $body = "", string $secret = ""): string {
        try {
            if ($accountId == $toAccountId) {
                return "-1";
            }

            $senderName = $this->database->fetchColumn(
                "SELECT userName FROM users WHERE extID = ? ORDER BY userName DESC LIMIT 1",
                [$accountId]
            );

            if (!$senderName) {
                return "-1";
            }

            $isBlocked = $this->database->exists(
                "blocks", 
                "person1 = ? AND person2 = ?",
                [$toAccountId, $accountId]
            );

            $messageSettings = $this->database->fetchColumn(
                "SELECT mS FROM accounts WHERE accountID = ? AND mS > 0",
                [$accountId]
            );

            $isFriend = $this->database->exists(
                "friendships",
                "(person1 = ? AND person2 = ?) OR (person2 = ? AND person1 = ?)",
                [$accountId, $toAccountId, $accountId, $toAccountId]
            );

            if (!empty($messageSettings) && $messageSettings == 2) {
                return "-1";
            }
            if ($isBlocked || (empty($messageSettings) && empty($isFriend))) {
                return "-1";
            }

            $this->database->insert('messages', [
                'subject' => $subject,
                'body' => $body,
                'accID' => $accountId,
                'userID' => $userId,
                'userName' => $senderName,
                'toAccountID' => $toAccountId,
                'secret' => $secret,
                'timestamp' => time()
            ]);

            return "1";

        } catch (Exception $e) {
            error_log("Message upload error: " . $e->getMessage());
            return "-1";
        }
    }
    
    public function getData(int $accountId = 0, int $userId = 0, int $page, int $getSent = 0, int $levelId = 0, int $gameVersion = 0, int $binaryVersion = 0): string {
        try {
            $offset = $page * 10;

            if (empty($getSent)) {
                $messages = $this->database->fetchAll(
                    "SELECT * FROM messages 
                     WHERE toAccountID = ? 
                     ORDER BY messageID DESC 
                     LIMIT 10 OFFSET ?",
                    [$accountId, $offset]
                );
                
                $totalCount = $this->database->count("messages", "toAccountID = ?", [$accountId]);
                $getSent = 0;
            } else {
                $messages = $this->database->fetchAll(
                    "SELECT * FROM messages 
                     WHERE accID = ? 
                     ORDER BY messageID DESC 
                     LIMIT 10 OFFSET ?",
                    [$accountId, $offset]
                );
                
                $totalCount = $this->database->count("messages", "accID = ?", [$accountId]);
                $getSent = 1;
            }

            if (empty($messages)) {
                return "-2";
            }

            $messageString = "";

            foreach ($messages as $message) {
                $targetAccountId = ($getSent == 1) ? $message["toAccountID"] : $message["accID"];
                
                $user = $this->database->fetchOne(
                    "SELECT * FROM users WHERE extID = ?",
                    [$targetAccountId]
                );

                if (!$user) {
                    continue;
                }

                $uploadDate = $this->lib->makeTime($message["timestamp"]);
                $messageString .= sprintf(
                    "6:%s:3:%d:2:%d:1:%d:4:%s:8:%d:9:%d:7:%s|",
                    $user["userName"],
                    $user["userID"],
                    $user["extID"],
                    $message["messageID"],
                    $message["subject"],
                    $message["isNew"],
                    $getSent,
                    $uploadDate
                );
            }

            $messageString = rtrim($messageString, "|");

            return $messageString . "#" . $totalCount . ":" . $offset . ":10";

        } catch (Exception $e) {
            error_log("Message getData error: " . $e->getMessage());
            return "-1";
        }
    }

    public function delete(int $accountId, int $userId, int $permission = 0, int $commentId = 0, int $messageId = 0, string $messages = ""): string {
        try {
            if (!empty($messages)) {
                $messageIds = explode(",", $messages);
                
                foreach ($messageIds as $id) {
                    $this->database->delete(
                        'messages',
                        'messageID = ? AND accID = ?',
                        [(int)$id, $accountId]
                    );
                    $this->database->delete(
                        'messages',
                        'messageID = ? AND toAccountID = ?',
                        [(int)$id, $accountId]
                    );
                }
            } else {
                $this->database->delete(
                    'messages',
                    'messageID = ? AND accID = ?',
                    [$messageId, $accountId]
                );
                $this->database->delete(
                    'messages',
                    'messageID = ? AND toAccountID = ?',
                    [$messageId, $accountId]
                );
            }

            return "1";

        } catch (Exception $e) {
            error_log("Message delete error: " . $e->getMessage());
            return "-1";
        }
    }
}