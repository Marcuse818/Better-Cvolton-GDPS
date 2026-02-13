<?php
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/Lib.php";

interface FriendInterface {
    public function accept(int $accountId, int $requestId): string;
    public function block(int $accountId, int $targetAccountId): string;
    public function read(int $accountId, int $requestId): string;
    public function delete(int $accountId, int $targetAccountId, int $isSender): string;
    public function remove(int $accountId, int $targetAccountId): string;
    public function unblock(int $accountId, int $targetAccountId): string;
    public function upload(int $accountId, int $targetAccountId, string $comment): string;
    public function getData(int $accountId, int $page, int $getSent): string;
    public function getDataList(int $accountId, int $type): string;
}

class Friend implements FriendInterface {
    protected Database $database;
    protected Lib $lib;

    public function __construct() {
        $this->database = new Database();
        $this->lib = new Lib();
    }

    public function accept(int $accountId, int $requestId): string {
        try {
            $request = $this->database->fetchOne(
                "SELECT accountID, toAccountID FROM friendreqs WHERE ID = ?",
                [$requestId]
            );

            if (!$request || $request["toAccountID"] != $accountId || $request["accountID"] == $accountId) {
                return "-1";
            }

            $this->database->insert('friendships', [
                'person1' => $request["accountID"],
                'person2' => $request["toAccountID"],
                'isNew1' => 1,
                'isNew2' => 1
            ]);

            $this->database->delete(
                'friendreqs',
                'ID = ?',
                [$requestId]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend accept error: " . $e->getMessage());
            return "-1";
        }
    }

    public function block(int $accountId, int $targetAccountId): string {
        try {
            if ($accountId == $targetAccountId) {
                return "-1";
            }

            $this->database->insert('blocks', [
                'person1' => $accountId,
                'person2' => $targetAccountId
            ]);

            return "1";

        } catch (Exception $e) {
            error_log("Friend block error: " . $e->getMessage());
            return "-1";
        }
    }

    public function read(int $accountId, int $requestId): string {
        try {
            $this->database->update(
                'friendreqs',
                ['isNew' => 0],
                'ID = ? AND toAccountID = ?',
                [$requestId, $accountId]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend read error: " . $e->getMessage());
            return "-1";
        }
    }

    public function delete(int $accountId, int $targetAccountId, int $isSender): string {
        try {
            if ($isSender == 1) {
                $this->database->delete(
                    'friendreqs',
                    'accountID = ? AND toAccountID = ?',
                    [$accountId, $targetAccountId]
                );
            } else {
                $this->database->delete(
                    'friendreqs',
                    'toAccountID = ? AND accountID = ?',
                    [$accountId, $targetAccountId]
                );
            }

            return "1";

        } catch (Exception $e) {
            error_log("Friend delete error: " . $e->getMessage());
            return "-1";
        }
    }

    public function remove(int $accountId, int $targetAccountId): string {
        try {
            $this->database->execute(
                "DELETE FROM friendships 
                 WHERE (person1 = ? AND person2 = ?) 
                    OR (person2 = ? AND person1 = ?)",
                [$accountId, $targetAccountId, $accountId, $targetAccountId]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend remove error: " . $e->getMessage());
            return "-1";
        }
    }

    public function unblock(int $accountId, int $targetAccountId): string {
        try {
            $this->database->delete(
                'blocks',
                'person1 = ? AND person2 = ?',
                [$accountId, $targetAccountId]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend unblock error: " . $e->getMessage());
            return "-1";
        }
    }

    public function upload(int $accountId, int $targetAccountId, string $comment): string {
        try {
            if ($accountId == $targetAccountId) {
                return "-1";
            }

            $isBlocked = $this->database->exists(
                "blocks",
                "person1 = ? AND person2 = ?",
                [$targetAccountId, $accountId]
            );

            $friendsOnly = $this->database->fetchColumn(
                "SELECT frS FROM accounts WHERE accountID = ? AND frS = 1",
                [$targetAccountId]
            );

            $existingRequest = $this->database->fetchColumn(
                "SELECT COUNT(*) FROM friendreqs 
                 WHERE (accountID = ? AND toAccountID = ?) 
                    OR (toAccountID = ? AND accountID = ?)",
                [$accountId, $targetAccountId, $accountId, $targetAccountId]
            );

            if ($existingRequest > 0 || $isBlocked || $friendsOnly) {
                return "-1";
            }

            $this->database->insert('friendreqs', [
                'accountID' => $accountId,
                'toAccountID' => $targetAccountId,
                'comment' => $comment,
                'uploadDate' => time()
            ]);

            return "1";

        } catch (Exception $e) {
            error_log("Friend upload error: " . $e->getMessage());
            return "-1";
        }
    }

    public function getData(int $accountId, int $page, int $getSent): string {
        try {
            $offset = $page * 10;

            if ($getSent == 0) {
                $requests = $this->database->fetchAll(
                    "SELECT accountID, toAccountID, uploadDate, ID, comment, isNew 
                     FROM friendreqs 
                     WHERE toAccountID = ? 
                     LIMIT 10 OFFSET ?",
                    [$accountId, $offset]
                );
                
                $totalCount = $this->database->count(
                    "friendreqs", 
                    "toAccountID = ?", 
                    [$accountId]
                );
            } else {
                $requests = $this->database->fetchAll(
                    "SELECT accountID, toAccountID, uploadDate, ID, comment, isNew 
                     FROM friendreqs 
                     WHERE accountID = ? 
                     LIMIT 10 OFFSET ?",
                    [$accountId, $offset]
                );
                
                $totalCount = $this->database->count(
                    "friendreqs", 
                    "accountID = ?", 
                    [$accountId]
                );
            }

            if (empty($requests)) {
                return "-2";
            }

            $requestString = "";

            foreach ($requests as $request) {
                $requesterId = ($getSent == 0) ? $request["accountID"] : $request["toAccountID"];
                
                $user = $this->database->fetchOne(
                    "SELECT userName, userID, icon, color1, color2, iconType, special, extID 
                     FROM users 
                     WHERE extID = ?",
                    [$requesterId]
                );

                if (!$user) {
                    continue;
                }

                $uploadDate = $this->lib->makeTime($request["uploadDate"]);
                $extId = is_numeric($user["extID"]) ? $user["extID"] : 0;

                $requestString .= sprintf(
                    "1:%s:2:%d:9:%d:10:%d:11:%d:14:%d:15:%d:16:%d:32:%d:35:%s:41:%d:37:%s|",
                    $user["userName"],
                    $user["userID"],
                    $user["icon"],
                    $user["color1"],
                    $user["color2"],
                    $user["iconType"],
                    $user["special"],
                    $extId,
                    $request["ID"],
                    $request["comment"],
                    $request["isNew"],
                    $uploadDate
                );
            }

            $requestString = rtrim($requestString, "|");

            return $requestString . "#" . $totalCount . ":" . $page . ":10";

        } catch (Exception $e) {
            error_log("Friend getData error: " . $e->getMessage());
            return "-1";
        }
    }

    public function getDataList(int $accountId, int $type): string {
        try {
            $userStatusMap = [];

            if ($type == 0) {
                $relationships = $this->database->fetchAll(
                    "SELECT person1, isNew1, person2, isNew2 
                     FROM friendships 
                     WHERE person1 = ? OR person2 = ?",
                    [$accountId, $accountId]
                );

                if (empty($relationships)) {
                    return "-2";
                }

                $userIds = [];
                foreach ($relationships as $rel) {
                    if ($rel["person1"] == $accountId) {
                        $userIds[] = $rel["person2"];
                        $userStatusMap[$rel["person2"]] = $rel["isNew2"];
                    } else {
                        $userIds[] = $rel["person1"];
                        $userStatusMap[$rel["person1"]] = $rel["isNew1"];
                    }
                }

            } else {
                $blocks = $this->database->fetchAll(
                    "SELECT person2 FROM blocks WHERE person1 = ?",
                    [$accountId]
                );

                if (empty($blocks)) {
                    return "-2";
                }

                $userIds = array_column($blocks, 'person2');

                foreach ($userIds as $userId) {
                    $userStatusMap[$userId] = 0;
                }
            }

            if (empty($userIds)) {
                return "-2";
            }

            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $users = $this->database->fetchAll(
                "SELECT userName, userID, icon, color1, color2, iconType, special, extID 
                 FROM users 
                 WHERE extID IN ($placeholders) 
                 ORDER BY userName ASC",
                $userIds
            );

            $userString = "";

            foreach ($users as $user) {
                $status = $userStatusMap[$user["extID"]] ?? 0;
                $userString .= sprintf(
                    "1:%s:2:%d:9:%d:10:%d:11:%d:14:%d:15:%d:16:%d:18:0:41:%d|",
                    $user["userName"],
                    $user["userID"],
                    $user["icon"],
                    $user["color1"],
                    $user["color2"],
                    $user["iconType"],
                    $user["special"],
                    $user["extID"],
                    $status
                );
            }

            $userString = rtrim($userString, "|");

            if ($type == 0) {
                $this->database->update(
                    'friendships',
                    ['isNew1' => 0],
                    'person2 = ?',
                    [$accountId]
                );
                
                $this->database->update(
                    'friendships',
                    ['isNew2' => 0],
                    'person1 = ?',
                    [$accountId]
                );
            }

            return empty($userString) ? "-1" : $userString;

        } catch (Exception $e) {
            error_log("Friend getDataList error: " . $e->getMessage());
            return "-1";
        }
    }
}