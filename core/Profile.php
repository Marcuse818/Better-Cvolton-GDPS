<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/GJPCheck.php";
require_once __DIR__."/lib/Lib.php";

interface AccountInterface {
    public function getData(int $accountId, int $targetAccountId): string;
    public function getUsers(string $string, int $page): string;
    public function update(
        int $accountId,
        int $privateMessage,
        int $privateFriend,
        int $privateHistory,
        string $youtube,
        string $twitch,
        string $twitter
    ): string;
}

class Account implements AccountInterface {
    private Database $db;
    private Main $main;
    private Lib $lib;

    public function __construct() {
        $this->db = new Database();
        $this->main = new Main();
        $this->lib = new Lib();
    }

    public function getData(int $accountId, int $targetAccountId): string {
        if ($accountId <= 0 || $targetAccountId <= 0) return "-1";

        $isBlocked = $this->db->exists(
            "blocks",
            "(person1 = ? AND person2 = ?) OR (person2 = ? AND person1 = ?)",
            [$targetAccountId, $accountId, $targetAccountId, $accountId]
        );

        if ($isBlocked) return "-1";

        $userStats = $this->db->fetchOne(
            "SELECT * FROM users WHERE extID = ?",
            [$targetAccountId]
        );

        if (!$userStats) return "-1";

        $creatorPoint = round($userStats['creatorPoints'], PHP_ROUND_HALF_DOWN);
        
        $globalRank = $userStats['isBanned'] 
            ? 0 
            : $this->db->fetchColumn(
                "SELECT COUNT(*) + 1 FROM users WHERE stars > ? AND isBanned = 0",
                [$userStats["stars"]]
            );

        $accountInfo = $this->db->fetchOne(
            "SELECT youtubeurl, twitter, twitch, frS, mS, cS FROM accounts WHERE accountID = ?",
            [$targetAccountId]
        );

        if (!$accountInfo) return "-1";

        $privateFriends = $accountInfo["frS"] ?? 0;
        $privateMessages = $accountInfo["mS"] ?? 0;
        $privateComments = $accountInfo["cS"] ?? 0;
        $badge = $this->main->getRolePermission($targetAccountId, "modBadgeLevel");

        $friendState = 0;
        $additionalString = "";

        if ($accountId == $targetAccountId) {
            $friendsReqCount = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM friendreqs WHERE toAccountID = ?",
                [$accountId]
            );
            $messagesCount = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM messages WHERE toAccountID = ? AND isNew = 0",
                [$accountId]
            );
            $friendsCount = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM friendships WHERE (person1 = ? AND isNew2 = '1') OR (person2 = ? AND isNew1 = '1')",
                [$accountId, $accountId]
            );

            $additionalString = ":38:" . $messagesCount . ":39:" . $friendsReqCount . ":40:" . $friendsCount;
        } else {
            $friendRequestInfo = $this->db->fetchOne(
                "SELECT ID, comment, uploadDate FROM friendreqs WHERE accountID = ? AND toAccountID = ?",
                [$targetAccountId, $accountId]
            );

            if ($friendRequestInfo) {
                $uploadDate = $this->lib->makeTime($friendRequestInfo["uploadDate"]);
                $friendState = 3;
                $additionalString = ":32:" . $friendRequestInfo["ID"] . ":35:" . $friendRequestInfo["comment"] . ":37:" . $uploadDate;
            }

            $friendRequestOut = $this->db->exists(
                "friendreqs",
                "toAccountID = ? AND accountID = ?",
                [$targetAccountId, $accountId]
            );

            if ($friendRequestOut) $friendState = 4;

            $isFriend = $this->db->exists(
                "friendships",
                "(person1 = ? AND person2 = ?) OR (person2 = ? AND person1 = ?)",
                [$accountId, $targetAccountId, $accountId, $targetAccountId]
            );

            if ($isFriend) $friendState = 1;
        }

        return $this->buildResponseString([
            'userName' => $userStats["userName"],
            'userID' => $userStats["userID"],
            'coins' => $userStats["coins"],
            'userCoins' => $userStats["userCoins"],
            'color1' => $userStats["color1"],
            'color2' => $userStats["color2"],
            'color3' => $userStats["color3"],
            'stars' => $userStats["stars"],
            'diamonds' => $userStats["diamonds"],
            'moons' => $userStats["moons"],
            'demons' => $userStats["demons"],
            'creatorPoints' => $creatorPoint,
            'privateMessages' => $privateMessages,
            'privateFriends' => $privateFriends,
            'privateComments' => $privateComments,
            'youtube' => $accountInfo["youtubeurl"] ?? "",
            'accIcon' => $userStats["accIcon"],
            'accShip' => $userStats["accShip"],
            'accBall' => $userStats["accBall"],
            'accBird' => $userStats["accBird"],
            'accDart' => $userStats["accDart"],
            'accRobot' => $userStats["accRobot"],
            'accGlow' => $userStats["accGlow"],
            'accSpider' => $userStats["accSpider"],
            'accExplosion' => $userStats["accExplosion"],
            'accSwing' => $userStats["accSwing"],
            'accJetpack' => $userStats["accJetpack"],
            'globalRank' => $globalRank,
            'targetAccountID' => $targetAccountId,
            'friendState' => $friendState,
            'twitter' => $accountInfo["twitter"] ?? "",
            'twitch' => $accountInfo["twitch"] ?? "",
            'badge' => $badge,
            'additionalString' => $additionalString
        ]);
    }

    public function getUsers(string $string, int $page): string {
        if ($page < 0) return "-1";

        $cleanString = $this->sanitizeSearchString($string);
        $offset = $page * 10;

        try {
            $users = $this->db->fetchAll(
                "SELECT userName, userID, coins, userCoins, icon, color1, color2, color3, iconType, special, extID, stars, creatorPoints, demons, diamonds, moons 
                 FROM users 
                 WHERE userID = ? OR userName LIKE CONCAT('%', ?, '%') 
                 ORDER BY stars DESC 
                 LIMIT 10 OFFSET ?",
                [$cleanString, $cleanString, $offset]
            );

            if (empty($users)) return "-1";

            $usersCount = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM users WHERE userName LIKE CONCAT('%', ?, '%')",
                [$cleanString]
            );

            $userString = "";
            foreach ($users as $user) {
                $userString .= $this->buildUserString($user) . "|";
            }

            $userString = rtrim($userString, "|");

            return $userString . "#" . $usersCount . ":" . $page . ":10";

        } catch (Exception $e) {
            error_log("Error in getUsers: " . $e->getMessage());
            return "-1";
        }
    }

    public function update(int $accountId, int $privateMessage, int $privateFriend, int $privateHistory, string $youtube, string $twitch, string $twitter): string {
        if ($accountId <= 0) return '-1';
        
        $cleanYoutube = $this->sanitizeUrl($youtube);
        $cleanTwitch = $this->sanitizeUrl($twitch);
        $cleanTwitter = $this->sanitizeUrl($twitter);

        if (!in_array($privateMessage, [0, 1]) || !in_array($privateFriend, [0, 1]) || !in_array($privateHistory, [0, 1])) {
            return '-1';
        }

        try {
            $this->db->update(
                'accounts',
                [
                    'mS' => $privateMessage,
                    'frS' => $privateFriend,
                    'cS' => $privateHistory,
                    'youtubeurl' => $cleanYoutube,
                    'twitter' => $cleanTwitter,
                    'twitch' => $cleanTwitch
                ],
                'accountID = ?',
                [$accountId]
            );

            return "1";

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

    private function sanitizeSearchString(string $string): string {
        $string = trim($string);
        $string = substr($string, 0, 50);
        $string = preg_replace('/[^\w\s\-]/', '', $string);
        
        return $string;
    }

    private function sanitizeUrl(string $url): string {
        if (empty(trim($url))) return "";
        
        $url = trim($url);
        $url = substr($url, 0, 100); 

        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $url)) return "";
        
        return $url;
    }
}