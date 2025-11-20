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
    protected $db;
    protected $main, $lib;

    public function __construct() {
        $this->db = new Database();
        $this->main = new Main();
        $this->lib = new Lib();
    }

    public function getData(int $accountID, int $targetAccountID): string {
        if ($accountID <= 0 || $targetAccountID <= 0) return "-1";

        $is_blocked = $this->db->exists(
            "blocks",
            "(person1 = :targetAccountID AND person2 = :accountID) OR (person2 = :targetAccountID AND person1 = :accountID)",
            [
                ':targetAccountID' => $targetAccountID,
                ':accountID' => $accountID
            ]
        );

        if ($is_blocked) return "-1";

        $user_stats = $this->db->fetch_one(
            "SELECT * FROM users WHERE extID = :targetAccountID",
            [':targetAccountID' => $targetAccountID]
        );

        if (!$user_stats) return "-1";

        $creator_point = round($user_stats['creatorPoints'], PHP_ROUND_HALF_DOWN);
        $global_rank = $this->db->fetch_column(
            "SELECT COUNT(*) + 1 FROM users WHERE stars > :stars AND isBanned = 0",
            [':stars' => $user_stats["stars"]]
        );

        if ($user_stats['isBanned']) $global_rank = 0;

        $account_info = $this->db->fetch_one(
            "SELECT youtubeurl, twitter, twitch, frS, mS, cS FROM accounts WHERE accountID = :targetAccountID",
            [':targetAccountID' => $targetAccountID]
        );

        if (!$account_info) return "-1";

        $private_friends = $account_info["frS"] ?? 0;
        $private_messages = $account_info["mS"] ?? 0;
        $private_comments = $account_info["cS"] ?? 0;
        $badge = $this->main->getRolePermission($targetAccountID, "modBadgeLevel");

        $friend_state = 0;
        $additional_string = "";

        if ($accountID == $targetAccountID) {
            $friends_req_count = $this->db->fetch_column(
                "SELECT COUNT(*) FROM friendreqs WHERE toAccountID = :targetAccountID",
                [':targetAccountID' => $accountID]
            );
            $messages_count = $this->db->fetch_column(
                "SELECT COUNT(*) FROM messages WHERE toAccountID = :targetAccountID AND isNew = 0",
                [':targetAccountID' => $accountID]
            );
            $friends_count = $this->db->fetch_column(
                "SELECT COUNT(*) FROM friendships WHERE (person1 = :targetAccountID AND isNew2 = '1') OR (person2 = :targetAccountID AND isNew1 = '1')",
                [':targetAccountID' => $accountID]
            );

            $additional_string = ":38:" . $messages_count . ":39:" . $friends_req_count . ":40:" . $friends_count;
        } else {
            $friend_request_info = $this->db->fetch_one(
                "SELECT ID, comment, uploadDate FROM friendreqs WHERE accountID = :targetAccountID AND toAccountID = :accountID",
                [
                    ':targetAccountID' => $targetAccountID,
                    ':accountID' => $accountID
                ]
            );

            if ($friend_request_info) {
                $uploadDate = $this->lib->make_time($friend_request_info["uploadDate"]);
                $friend_state = 3;
                $additional_string = ":32:" . $friend_request_info["ID"] . ":35:" . $friend_request_info["comment"] . ":37:" . $uploadDate;
            }

            $friend_request_out = $this->db->exists(
                "friendreqs",
                "toAccountID = :targetAccountID AND accountID = :accountID",
                [
                    ':targetAccountID' => $targetAccountID,
                    ':accountID' => $accountID
                ]
            );

            if ($friend_request_out) $friend_state = 4;

            $is_friend = $this->db->exists(
                "friendships",
                "(person1 = :accountID AND person2 = :targetAccountID) OR (person2 = :accountID AND person1 = :targetAccountID)",
                [
                    ':targetAccountID' => $targetAccountID,
                    ':accountID' => $accountID
                ]
            );

            if ($is_friend) $friend_state = 1;
        }

        return $this->buildResponseString([
                'userName' => $user_stats["userName"],
                'userID' => $user_stats["userID"],
                'coins' => $user_stats["coins"],
                'userCoins' => $user_stats["userCoins"],
                'color1' => $user_stats["color1"],
                'color2' => $user_stats["color2"],
                'color3' => $user_stats["color3"],
                'stars' => $user_stats["stars"],
                'diamonds' => $user_stats["diamonds"],
                'moons' => $user_stats["moons"],
                'demons' => $user_stats["demons"],
                'creatorPoints' => $creator_point,
                'privateMessages' => $private_messages,
                'privateFriends' => $private_friends,
                'privateComments' => $private_comments,
                'youtube' => $account_info["youtubeurl"] ?? "",
                'accIcon' => $user_stats["accIcon"],
                'accShip' => $user_stats["accShip"],
                'accBall' => $user_stats["accBall"],
                'accBird' => $user_stats["accBird"],
                'accDart' => $user_stats["accDart"],
                'accRobot' => $user_stats["accRobot"],
                'accGlow' => $user_stats["accGlow"],
                'accSpider' => $user_stats["accSpider"],
                'accExplosion' => $user_stats["accExplosion"],
                'accSwing' => $user_stats["accSwing"],
                'accJetpack' => $user_stats["accJetpack"],
                'globalRank' => $global_rank,
                'targetAccountID' => $targetAccountID,
                'friendState' => $friend_state,
                'twitter' => $account_info["twitter"] ?? "",
                'twitch' => $account_info["twitch"] ?? "",
                'badge' => $badge,
                'additionalString' => $additional_string
        ]);
    }

    public function getUsers(string $string, int $page): string {
        if ($page < 0) return "-1";

        $clean_string = $this->sanitize_search_string($string);
        $offset = $page * 10;

        try {
            $users = $this->db->fetch_all(
                "SELECT userName, userID, coins, userCoins, icon, color1, color2, color3, iconType, special, extID, stars, creatorPoints, demons, diamonds, moons 
                 FROM users 
                 WHERE userID = :str OR userName LIKE CONCAT('%', :str, '%') 
                 ORDER BY stars DESC 
                 LIMIT 10 OFFSET :offset",
                [
                    ':str' => $clean_string,
                    ':offset' => $offset
                ]
            );

            if (empty($users)) return "-1";

            $users_count = $this->db->fetch_column(
                "SELECT COUNT(*) FROM users WHERE userName LIKE CONCAT('%', :str, '%')",
                [':str' => $clean_string]
            );

            $userString = "";
            foreach ($users as $user) {
                $userString .= $this->buildUserString($user) . "|";
            }

            $userString = substr($userString, 0, -1);

            return $userString . "#" . $users_count . ":" . $page . ":10";

        } catch (Exception $e) {
            error_log("Error in getUsers: " . $e->getMessage());
            return "-1";
        }
    }

    public function update(int $accountID, int $privateMessage, int $privateFriend, int $privateHistory, string $youtube, string $twitch, string $twitter): string {
        if ($accountID <= 0) return '-1';
        
        $clean_youtube = $this->sanitize_url($youtube);
        $clean_twitch = $this->sanitize_url($twitch);
        $clean_twitter = $this->sanitize_url($twitter);

        if (!in_array($privateMessage, [0, 1]) || !in_array($privateFriend, [0, 1]) || !in_array($privateHistory, [0, 1])) return '-1';

        try {
            $result = $this->db->execute(
                "UPDATE accounts SET mS = :mS, frS = :frS, cS = :cS, youtubeurl = :youtubeurl, twitter = :twitter, twitch = :twitch 
                 WHERE accountID = :accountID",
                [
                    ':accountID' => $accountID,
                    ':mS' => $privateMessage,
                    ':frS' => $privateFriend,
                    ':cS' => $privateHistory,
                    ':youtubeurl' => $clean_youtube,
                    ':twitch' => $clean_twitch,
                    ':twitter' => $clean_twitter
                ]
            );

            return $result ? "1" : "0";

        } catch (Exception $e) {
            error_log("Error in update: " . $e->getMessage());
            return "0";
        }
    }

    private function buildResponseString(array $data): string {
        return "1:" . $data['userName'] . 
               ":2:" . $data['userID'] . 
               ":13:" . $data['coins'] . 
               ":17:" . $data['userCoins'] . 
               ":10:" . $data['color1'] . 
               ":11:" . $data['color2'] . 
               ":51:" . $data['color3'] . 
               ":3:" . $data['stars'] . 
               ":46:" . $data['diamonds'] . 
               ":52:" . $data['moons'] . 
               ":4:" . $data['demons'] . 
               ":8:" . $data['creatorPoints'] . 
               ":18:" . $data['privateMessages'] . 
               ":19:" . $data['privateFriends'] . 
               ":50:" . $data['privateComments'] . 
               ":20:" . $data['youtube'] . 
               ":21:" . $data['accIcon'] . 
               ":22:" . $data['accShip'] . 
               ":23:" . $data['accBall'] . 
               ":24:" . $data['accBird'] . 
               ":25:" . $data['accDart'] . 
               ":26:" . $data['accRobot'] . 
               ":28:" . $data['accGlow'] . 
               ":43:" . $data['accSpider'] . 
               ":47:" . $data['accExplosion'] . 
               ":53:" . $data['accSwing'] . 
               ":54:" . $data['accJetpack'] . 
               ":30:" . $data['globalRank'] . 
               ":16:" . $data['targetAccountID'] . 
               ":31:" . $data['friendState'] . 
               ":44:" . $data['twitter'] . 
               ":45:" . $data['twitch'] . 
               ":29:1" . 
               ":49:" . $data['badge'] . 
               $data['additionalString'];
    }

    private function buildUserString(array $user): string {
        return "1:" . $user["userName"] . 
               ":2:" . $user["userID"] . 
               ":13:" . $user["coins"] . 
               ":17:" . $user["userCoins"] . 
               ":9:" . $user["icon"] . 
               ":10:" . $user["color1"] . 
               ":11:" . $user["color2"] . 
               ":51:" . $user["color3"] . 
               ":14:" . $user["iconType"] . 
               ":15:" . $user["special"] . 
               ":16:" . (is_numeric($user["extID"]) ? $user["extID"] : 0) . 
               ":3:" . $user["stars"] . 
               ":8:" . round($user["creatorPoints"], 0, PHP_ROUND_HALF_DOWN) . 
               ":4:" . $user["demons"] . 
               ":46:" . $user["diamonds"] . 
               ":52:" . $user["moons"];
    }

    private function sanitize_search_string(string $string): string {
        $string = trim($string);
        $string = substr($string, 0, 50);
        $string = preg_replace('/[^\w\s\-]/', '', $string);
        
        return $string;
    }

    private function sanitize_url(string $url): string {
        if (empty(trim($url))) return "";
        

        $url = trim($url);
        $url = substr($url, 0, 100); 

        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $url)) return "";
        

        return $url;
    }
}