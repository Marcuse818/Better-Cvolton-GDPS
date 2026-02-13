<?php  
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/Lib.php";

interface ScoresInterface {
    public function getData(
        int $accountId, 
        int $levelId, 
        ?int $type = null, 
        string $mode = "points", 
        int $time = 0, 
        int $points = 0,
        int $count = 0
    ): string;
    
    public function update(int $accountId, int $userId, string $hostname): string;
}

abstract class BaseScores implements ScoresInterface {
    protected Database $db;
    protected Main $main;
    protected Lib $lib;
    protected int $uploadDate;

    public function __construct() {
        $this->main = new Main();
        $this->db = new Database();
        $this->lib = new Lib();
        $this->uploadDate = time();
    }

    abstract public function getData(int $accountId, int $levelId, ?int $type = null, string $mode = "points", int $time = 0, int $points = 0, int $count = 0): string;
    
    abstract public function update(int $accountId, int $userId, string $hostname): string;

    protected function buildUserString(array $user, int $rank, array $extra = []): string {
        $extId = is_numeric($user["extID"]) ? $user["extID"] : 0;
        
        $fields = [
            1 => $user["userName"],
            2 => $user["userID"],
            13 => $user["coins"] ?? 0,
            17 => $user["userCoins"] ?? 0,
            6 => $rank,
            9 => $user["icon"],
            10 => $user["color1"],
            11 => $user["color2"],
            51 => $user["color3"] ?? 0,
            14 => $user["iconType"],
            15 => $user["special"],
            16 => $extId,
            3 => $user["stars"] ?? 0,
            8 => round($user["creatorPoints"] ?? 0, 0, PHP_ROUND_HALF_DOWN),
            4 => $user["demons"] ?? 0,
            7 => $extId,
            46 => $user["diamonds"] ?? 0,
            52 => $user["moons"] ?? 0
        ];

        foreach ($extra as $key => $value) {
            $fields[$key] = $value;
        }

        $parts = [];
        foreach ($fields as $key => $value) {
            $parts[] = "$key:$value";
        }

        return implode(":", $parts) . "|";
    }
}

class Creators extends BaseScores {
    public function getData(int $accountId, int $levelId, ?int $type = null, string $mode = "points", int $time = 0, int $points = 0, int $count = 0): string {
        $creators = $this->db->fetchAll(
            "SELECT * FROM users WHERE isCreatorBanned = '0' ORDER BY creatorPoints DESC LIMIT 100"
        );

        $rank = 0;
        $creatorsString = "";

        foreach ($creators as $creator) {
            $rank++;
            $creatorsString .= $this->buildUserString($creator, $rank);
        }

        return rtrim($creatorsString, "|");
    }

    public function update(int $accountId, int $userId, string $hostname): string {
        return "lol";
    }
}

class Score extends BaseScores {
    public int $percent = 0;
    public int $attempts = 0;
    public int $clicks = 0;
    public int $progresses = 0;
    public int $dailyId = 0;
    public int $time = 0;
    public int $coins = 0;
    public int $stars = 0;
    public int $demons = 0;
    public int $icon = 0;
    public int $color1 = 0;
    public int $color2 = 0;
    public int $gameVersion = 0;
    public int $binaryVersion = 0;
    public int $iconType = 0;
    public int $userCoins = 0;
    public int $special = 0;
    public int $accIcon = 0;
    public int $accShip = 0;
    public int $accBall = 0;
    public int $accBird = 0;
    public int $accDart = 0;
    public int $accRobot = 0;
    public int $accGlow = 0;
    public int $accSpider = 0;
    public int $accExplosion = 0;
    public int $diamonds = 0;
    public int $moons = 0;
    public int $color3 = 0;
    public int $accSwing = 0;
    public int $accJetpack = 0;
    public string $userName = "";
    public string $secret = "";

    public function getData(int $accountId, int $levelId, ?int $type = null, string $mode = "points", int $time = 0, int $points = 0, int $count = 0): string {
        $condition = ($this->dailyId > 0) ? ">" : "=";

        $oldPercent = $this->db->fetchColumn(
            "SELECT percent FROM levelscores WHERE accountID = ? AND levelID = ? AND dailyID $condition 0",
            [$accountId, $levelId]
        );
        
        $data = [
            'accountID' => $accountId,
            'levelID' => $levelId,
            'percent' => $this->percent,
            'uploadDate' => $this->uploadDate,
            'coins' => $this->coins,
            'attempts' => $this->attempts,
            'clicks' => $this->clicks,
            'time' => $this->time,
            'progresses' => $this->progresses,
            'dailyID' => $this->dailyId
        ];

        if ($oldPercent === null) {
            $this->db->insert('levelscores', $data);
        } elseif ($oldPercent <= $this->percent) {
            $this->db->update(
                'levelscores',
                $data,
                'accountID = ? AND levelID = ? AND dailyID ' . $condition . ' 0',
                [$accountId, $levelId]
            );
        }

        if ($this->percent > 100) {
            $this->db->update(
                'users',
                ['isBanned' => 1],
                'extID = ?',
                [$accountId]
            );
        }

        $scores = $this->getScoresByType($type, $accountId, $levelId, $condition);
        
        return $this->buildScoreString($scores);
    }

    private function getScoresByType(?int $type, int $accountId, int $levelId, string $condition): array {
        switch ($type) {
            case 0:
                $friends = $this->main->getFriends($accountId);
                $friends[] = $accountId;
                $friendsList = implode(",", $friends);
                
                return $this->db->fetchAll(
                    "SELECT accountID, uploadDate, percent, coins FROM levelscores 
                     WHERE dailyID $condition 0 AND levelID = ? AND accountID IN ($friendsList) 
                     ORDER BY percent DESC",
                    [$levelId]
                );
            
            case 1:
                return $this->db->fetchAll(
                    "SELECT accountID, uploadDate, percent, coins FROM levelscores 
                     WHERE dailyID $condition 0 AND levelID = ? 
                     ORDER BY percent DESC",
                    [$levelId]
                );

            case 2:
                return $this->db->fetchAll(
                    "SELECT accountID, uploadDate, percent, coins FROM levelscores 
                     WHERE dailyID $condition 0 AND levelID = ? AND uploadDate > ? 
                     ORDER BY percent DESC",
                    [$levelId, time() - 604800]
                );

            default:
                return [];
        }
    }

    private function buildScoreString(array $scores): string {
        $scoreString = "";

        foreach ($scores as $score) {
            $user = $this->db->fetchOne(
                "SELECT userName, userID, icon, color1, color2, color3, iconType, special, extID, isBanned 
                 FROM users WHERE extID = ?",
                [$score["accountID"]]
            );
            
            if (!$user || $user["isBanned"] != 0) {
                continue;
            }

            $place = ($score["percent"] == 100) ? 1 : (($score["percent"] > 75) ? 2 : 3);
            $time = $this->lib->makeTime($score["uploadDate"]);
            
            $extra = [
                3 => $score["percent"],
                6 => $place,
                13 => $score["coins"],
                42 => $this->time
            ];
            
            $scoreString .= $this->buildUserString($user, $place, $extra);
        }

        return rtrim($scoreString, "|") ?: "-1";
    }

    public function update(int $accountId, int $userId, string $hostname): string {
        $old = $this->db->fetchOne(
            "SELECT stars, coins, demons, userCoins, diamonds, moons FROM users WHERE userID = ? LIMIT 1",
            [$userId]
        );

        $userData = [
            'gameVersion' => $this->gameVersion,
            'userName' => $this->userName,
            'coins' => $this->coins,
            'secret' => $this->secret,
            'stars' => $this->stars,
            'demons' => $this->demons,
            'icon' => $this->icon,
            'color1' => $this->color1,
            'color2' => $this->color2,
            'iconType' => $this->iconType,
            'userCoins' => $this->userCoins,
            'special' => $this->special,
            'accIcon' => $this->accIcon,
            'accShip' => $this->accShip,
            'accBall' => $this->accBall,
            'accBird' => $this->accBird,
            'accDart' => $this->accDart,
            'accRobot' => $this->accRobot,
            'accGlow' => $this->accGlow,
            'IP' => $hostname,
            'lastPlayed' => $this->uploadDate,
            'accSpider' => $this->accSpider,
            'accExplosion' => $this->accExplosion,
            'diamonds' => $this->diamonds,
            'moons' => $this->moons,
            'color3' => $this->color3,
            'accSwing' => $this->accSwing,
            'accJetpack' => $this->accJetpack
        ];

        $this->db->update('users', $userData, 'userID = ?', [$userId]);

        $diffs = [
            'stars' => $this->stars - ($old['stars'] ?? 0),
            'coins' => $this->coins - ($old['coins'] ?? 0),
            'demons' => $this->demons - ($old['demons'] ?? 0),
            'userCoins' => $this->userCoins - ($old['userCoins'] ?? 0),
            'diamonds' => $this->diamonds - ($old['diamonds'] ?? 0),
            'moons' => $this->moons - ($old['moons'] ?? 0)
        ];

        $this->db->insert('actions', [
            'type' => 9,
            'value' => $diffs['stars'],
            'timestamp' => time(),
            'account' => $userId,
            'value2' => $diffs['coins'],
            'value3' => $diffs['demons'],
            'value4' => $diffs['userCoins'],
            'value5' => $diffs['diamonds'],
            'value6' => $diffs['moons']
        ]);

        return (string)$userId;
    }
}

class Platformer extends BaseScores {
    public int $time = 0;
    public int $points = 0;

    public function getData(int $accountId, int $levelId, ?int $type = null, string $mode = "points", int $time = 0, int $points = 0, int $count = 0): string {
        $oldValue = $this->db->fetchColumn(
            "SELECT {$mode} FROM platscores WHERE accountID = ? AND levelID = ?",
            [$accountId, $levelId]
        );
        
        $scoreData = [
            'accountID' => $accountId,
            'levelID' => $levelId,
            $mode => ($mode == "time" ? $this->time : $this->points),
            'timestamp' => $this->uploadDate
        ];

        if ($oldValue === null) {
            $this->db->insert('platscores', $scoreData);
        } else {
            $shouldUpdate = ($mode == "time" && $oldValue > $this->time && $this->time > 0) 
                         || ($mode == "points" && $oldValue < $this->points && $this->points > 0);
            
            if ($shouldUpdate) {
                $this->db->update(
                    'platscores',
                    [$mode => $scoreData[$mode], 'timestamp' => $this->uploadDate],
                    'accountID = ? AND levelID = ?',
                    [$accountId, $levelId]
                );
            }
        }

        $scores = $this->getPlatformerScoresByType($type, $accountId, $levelId, $mode);
        
        return $this->buildPlatformerString($scores, $mode);
    }

    private function getPlatformerScoresByType(?int $type, int $accountId, int $levelId, string $mode): array {
        switch ($type) {
            case 0:
                $friends = $this->main->getFriends($accountId);
                $friends[] = $accountId;
                $friendsList = implode(",", $friends);

                return $this->db->fetchAll(
                    "SELECT * FROM platscores WHERE levelID = ? AND accountID IN ($friendsList) ORDER BY {$mode} DESC",
                    [$levelId]
                );
            
            case 1:
                return $this->db->fetchAll(
                    "SELECT * FROM platscores WHERE levelID = ? ORDER BY {$mode} DESC",
                    [$levelId]
                );

            case 2:
                return $this->db->fetchAll(
                    "SELECT * FROM platscores WHERE levelID = ? AND timestamp > ? ORDER BY {$mode} DESC",
                    [$levelId, $this->uploadDate - 604800]
                );

            default:
                return [];
        }
    }

    private function buildPlatformerString(array $scores, string $mode): string {
        $rank = 0;
        $levelString = "";

        foreach ($scores as $score) {
            $user = $this->db->fetchOne(
                "SELECT userName, userID, icon, color1, color2, color3, iconType, special, extID, isBanned 
                 FROM users WHERE extID = ?",
                [$score["accountID"]]
            );

            if (!$user || $user["isBanned"] != 0) {
                continue;
            }

            $rank++;
            $time = $this->lib->makeTime($score["timestamp"]);
            $scoreValue = $score[$mode];

            $fields = [
                1 => $user['userName'],
                2 => $user['userID'],
                9 => $user['icon'],
                10 => $user['color1'],
                11 => $user['color2'],
                14 => $user['iconType'],
                15 => $user['color3'],
                16 => $score["accountID"],
                3 => $scoreValue,
                6 => $rank,
                42 => $time
            ];

            $parts = [];
            foreach ($fields as $key => $value) {
                $parts[] = "$key:$value";
            }

            $levelString .= implode(":", $parts) . "|";
        }

        return rtrim($levelString, "|") ?: "-1";
    }

    public function update(int $accountId, int $userId, string $hostname): string {
        return "lol";
    }
}

class Leaderboard extends BaseScores {
    public int $gameVersion = 0;

    public function getData(int $accountId, int $levelId, ?int $type = null, string $mode = "points", int $time = 0, int $points = 0, int $count = 0): string {
        $sign = empty($this->gameVersion) ? "< 20 AND gameVersion <> 0" : "> 19";
        $rank = 0;
        $leaderboardString = "";
        
        $users = match($type) {
            "top" => $this->getTopUsers($sign),
            "creators" => $this->getCreators(),
            "relative" => $this->getRelativeUsers($accountId, $sign, $count, $rank),
            "friends" => $this->getFriendsUsers($accountId),
            default => []
        };

        if (empty($users)) {
            return "-1";
        }

        foreach ($users as $user) {
            $rank++;
            $leaderboardString .= $this->buildUserString($user, $rank);
        }

        return rtrim($leaderboardString, "|");
    }

    private function getTopUsers(string $sign): array {
        return $this->db->fetchAll(
            "SELECT * FROM users WHERE isBanned = '0' AND gameVersion $sign AND stars > 0 ORDER BY stars DESC LIMIT 100"
        );
    }

    private function getCreators(): array {
        return $this->db->fetchAll(
            "SELECT * FROM users WHERE isCreatorBanned = '0' AND creatorPoints > 0 ORDER BY creatorPoints DESC LIMIT 100"
        );
    }

    private function getRelativeUsers(int $accountId, string $sign, int $count, int &$rank): array {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE extID = ?",
            [$accountId]
        );

        if (!$user) {
            return [];
        }

        $stars = $user["stars"];
        $halfCount = max(1, floor($count / 2));

        $users = $this->db->fetchAll(
            "SELECT A.* FROM (
                (SELECT * FROM users WHERE stars <= ? AND isBanned = 0 AND gameVersion $sign ORDER BY stars DESC LIMIT $halfCount)
                UNION
                (SELECT * FROM users WHERE stars >= ? AND isBanned = 0 AND gameVersion $sign ORDER BY stars ASC LIMIT $halfCount)
            ) as A ORDER BY A.stars DESC",
            [$stars, $stars]
        );

        $userRank = $this->db->fetchColumn(
            "SELECT rank FROM (
                SELECT @rownum := @rownum + 1 AS rank, extID
                FROM users WHERE isBanned = '0' AND gameVersion $sign ORDER BY stars DESC
            ) as result WHERE extID = ?",
            [$accountId]
        );

        $rank = ($userRank ?: 1) - 1;

        return $users;
    }

    private function getFriendsUsers(int $accountId): array {
        $friendships = $this->db->fetchAll(
            "SELECT person1, person2 FROM friendships WHERE person1 = ? OR person2 = ?",
            [$accountId, $accountId]
        );

        if (empty($friendships)) {
            return [];
        }

        $friendIds = [$accountId];
        foreach ($friendships as $friendship) {
            $friendIds[] = ($friendship["person1"] == $accountId) ? $friendship["person2"] : $friendship["person1"];
        }

        $placeholders = implode(",", array_fill(0, count($friendIds), "?"));
        
        return $this->db->fetchAll(
            "SELECT * FROM users WHERE extID IN ($placeholders) ORDER BY stars DESC",
            $friendIds
        );
    }

    public function update(int $accountId, int $userId, string $hostname): string {
        return "lol";
    }
}