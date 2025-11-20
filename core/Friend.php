<?php
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/Lib.php";

interface FriendInterface {
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

class Friend implements FriendInterface {
    protected $database;
    protected $lib;

    public function __construct() {
        $this->database = new Database();
        $this->lib = new Lib();
    }

    public function accept(int $accountID, int $requestID): string {
        try {
            $request = $this->database->fetch_one(
                "SELECT accountID, toAccountID FROM friendreqs WHERE ID = :requestID",
                [":requestID" => $requestID]
            );

            if (!$request || $request["toAccountID"] != $accountID || $request["accountID"] == $accountID) {
                return "-1";
            }

            $this->database->insert(
                "INSERT INTO friendships (person1, person2, isNew1, isNew2) VALUES (:person1, :person2, 1, 1)",
                [
                    ":person1" => $request["accountID"],
                    ":person2" => $request["toAccountID"]
                ]
            );
            $this->database->execute(
                "DELETE FROM friendreqs WHERE ID = :requestID LIMIT 1",
                [":requestID" => $requestID]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend accept error: " . $e->getMessage());
            return "-1";
        }
    }

    public function block(int $accountID, int $targetAccountID): string {
        try {
            if ($accountID == $targetAccountID) return "-1";

            $this->database->insert(
                "INSERT INTO blocks (person1, person2) VALUES (:accountID, :targetAccountID)",
                [
                    ":accountID" => $accountID,
                    ":targetAccountID" => $targetAccountID
                ]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend block error: " . $e->getMessage());
            return "-1";
        }
    }

    public function read(int $accountID, int $requestID): string {
        try {
            $this->database->execute(
                "UPDATE friendreqs SET isNew = '0' WHERE ID = :requestID AND toAccountID = :accountID",
                [
                    ":requestID" => $requestID,
                    ":accountID" => $accountID
                ]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend read error: " . $e->getMessage());
            return "-1";
        }
    }

    public function delete(int $accountID, int $targetAccountID, int $isSender): string {
        try {
            if (isset($isSender) && $isSender == 1) {
                $this->database->execute(
                    "DELETE FROM friendreqs WHERE accountID = :accountID AND toAccountID = :targetAccountID LIMIT 1",
                    [
                        ":accountID" => $accountID,
                        ":targetAccountID" => $targetAccountID
                    ]
                );
            } else {
                $this->database->execute(
                    "DELETE FROM friendreqs WHERE toAccountID = :accountID AND accountID = :targetAccountID LIMIT 1",
                    [
                        ":accountID" => $accountID,
                        ":targetAccountID" => $targetAccountID
                    ]
                );
            }

            return "1";

        } catch (Exception $e) {
            error_log("Friend delete error: " . $e->getMessage());
            return "-1";
        }
    }

    public function remove(int $accountID, int $targetAccountID): string {
        try {
            $this->database->execute(
                "DELETE FROM friendships WHERE (person1 = :accountID AND person2 = :targetAccountID) OR (person2 = :accountID AND person1 = :targetAccountID)",
                [
                    ":accountID" => $accountID,
                    ":targetAccountID" => $targetAccountID
                ]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend remove error: " . $e->getMessage());
            return "-1";
        }
    }

    public function unblock(int $accountID, int $targetAccountID): string {
        try {
            $this->database->execute(
                "DELETE FROM blocks WHERE person1 = :accountID AND person2 = :targetAccountID",
                [
                    ":accountID" => $accountID,
                    ":targetAccountID" => $targetAccountID
                ]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend unblock error: " . $e->getMessage());
            return "-1";
        }
    }

    public function upload(int $accountID, int $targetAccountID, string $comment): string {
        try {
            if ($accountID == $targetAccountID) return "-1";

            $isBlocked = $this->database->exists(
                "blocks",
                "person1 = :targetAccountID AND person2 = :accountID",
                [
                    ":targetAccountID" => $targetAccountID,
                    ":accountID" => $accountID
                ]
            );

            $friendsOnly = $this->database->fetch_column(
                "SELECT frS FROM accounts WHERE accountID = :targetAccountID AND frS = 1",
                [":targetAccountID" => $targetAccountID]
            );
            $existingRequest = $this->database->fetch_column(
                "SELECT COUNT(*) FROM friendreqs WHERE (accountID = :accountID AND toAccountID = :targetAccountID) OR (toAccountID = :accountID AND accountID = :targetAccountID)",
                [
                    ":accountID" => $accountID,
                    ":targetAccountID" => $targetAccountID
                ]
            );

            if ($existingRequest > 0 || $isBlocked || $friendsOnly) {
                return "-1";
            }

            $this->database->insert(
                "INSERT INTO friendreqs (accountID, toAccountID, comment, uploadDate) VALUES (:accountID, :targetAccountID, :comment, :uploadDate)",
                [
                    ":accountID" => $accountID,
                    ":targetAccountID" => $targetAccountID,
                    ":comment" => $comment,
                    ":uploadDate" => time()
                ]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Friend upload error: " . $e->getMessage());
            return "-1";
        }
    }

    public function getData(int $accountID, int $page, int $getSent): string {
        try {
            $offset = $page * 10;
            $requestString = "";

            if ($getSent == 0) {
                $requests = $this->database->fetch_all(
                    "SELECT accountID, toAccountID, uploadDate, ID, comment, isNew 
                     FROM friendreqs 
                     WHERE toAccountID = :accountID 
                     LIMIT 10 OFFSET :offset",
                    [
                        ":accountID" => $accountID,
                        ":offset" => $offset
                    ]
                );
                
                $totalCount = $this->database->count(
                    "friendreqs", 
                    "toAccountID = :accountID", 
                    [":accountID" => $accountID]
                );
            } else {
                $requests = $this->database->fetch_all(
                    "SELECT accountID, toAccountID, uploadDate, ID, comment, isNew 
                     FROM friendreqs 
                     WHERE accountID = :accountID 
                     LIMIT 10 OFFSET :offset",
                    [
                        ":accountID" => $accountID,
                        ":offset" => $offset
                    ]
                );
                
                $totalCount = $this->database->count(
                    "friendreqs", 
                    "accountID = :accountID", 
                    [":accountID" => $accountID]
                );
            }

            if (empty($requests)) {
                return "-2";
            }
            foreach ($requests as $request) {
                $requesterID = ($getSent == 0) ? $request["accountID"] : $request["toAccountID"];
                
                $user = $this->database->fetch_one(
                    "SELECT userName, userID, icon, color1, color2, iconType, special, extID 
                     FROM users 
                     WHERE extID = :requesterID",
                    [":requesterID" => $requesterID]
                );

                if (!$user) continue;

                $uploadDate = $this->lib->make_time($request["uploadDate"]);
                $extID = is_numeric($user["extID"]) ? $user["extID"] : 0;

                $requestString .= "1:".$user["userName"].":2:".$user["userID"].":9:".$user["icon"].":10:".$user["color1"].":11:".$user["color2"].":14:".$user["iconType"].":15:".$user["special"].":16:".$extID.":32:".$request["ID"].":35:".$request["comment"].":41:".$request["isNew"].":37:".$uploadDate."|";
            }

            $requestString = rtrim($requestString, "|");

            return $requestString . "#" . $totalCount . ":" . $page . ":10";

        } catch (Exception $e) {
            error_log("Friend getData error: " . $e->getMessage());
            return "-1";
        }
    }

    public function getDataList(int $accountID, int $type): string {
        try {
            $userString = "";
            $userStatusMap = [];

            if ($type == 0) {
                $relationships = $this->database->fetch_all(
                    "SELECT person1, isNew1, person2, isNew2 
                     FROM friendships 
                     WHERE person1 = :accountID OR person2 = :accountID",
                    [":accountID" => $accountID]
                );

                if (empty($relationships)) {
                    return "-2";
                }

                $userIDs = [];
                foreach ($relationships as $rel) {
                    if ($rel["person1"] == $accountID) {
                        $userIDs[] = $rel["person2"];
                        $userStatusMap[$rel["person2"]] = $rel["isNew2"];
                    } else {
                        $userIDs[] = $rel["person1"];
                        $userStatusMap[$rel["person1"]] = $rel["isNew1"];
                    }
                }

            } else {
                $blocks = $this->database->fetch_all(
                    "SELECT person2 FROM blocks WHERE person1 = :accountID",
                    [":accountID" => $accountID]
                );

                if (empty($blocks)) {
                    return "-2";
                }

                $userIDs = array_column($blocks, 'person2');

                foreach ($userIDs as $userID) {
                    $userStatusMap[$userID] = 0;
                }
            }

            if (empty($userIDs)) {
                return "-2";
            }

            $placeholders = implode(',', array_fill(0, count($userIDs), '?'));
            $users = $this->database->fetch_all(
                "SELECT userName, userID, icon, color1, color2, iconType, special, extID 
                 FROM users 
                 WHERE extID IN ($placeholders) 
                 ORDER BY userName ASC",
                $userIDs
            );

            foreach ($users as $user) {
                $status = $userStatusMap[$user["extID"]] ?? 0;
                $userString .= "1:".$user["userName"].":2:".$user["userID"].":9:".$user["icon"].":10:".$user["color1"].":11:".$user["color2"].":14:".$user["iconType"].":15:".$user["special"].":16:".$user["extID"].":18:0:41:".$status."|";
            }

            $userString = rtrim($userString, "|");

            if ($type == 0) {
                $this->database->execute(
                    "UPDATE friendships SET isNew1 = '0' WHERE person2 = :accountID",
                    [":accountID" => $accountID]
                );
                
                $this->database->execute(
                    "UPDATE friendships SET isNew2 = '0' WHERE person1 = :accountID",
                    [":accountID" => $accountID]
                );
            }

            return empty($userString) ? "-1" : $userString;

        } catch (Exception $e) {
            error_log("Friend getDataList error: " . $e->getMessage());
            return "-1";
        }
    }
}