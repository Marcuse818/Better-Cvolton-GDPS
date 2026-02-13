<?php 
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/exploitPatch.php";
require_once __DIR__."/lib/GJPCheck.php";
include_once __DIR__."/lib/ip_in_range.php";

require_once __DIR__."/../config/security.php";

class Main extends SecurityConfig {
    private Database $db;
    private ?array $friends = null;
    private int $maxPermission = 0;
    private ?string $id = null;
    private ?int $userId = null;

    public function __construct() {
        $this->db = new Database();
        $this->maxPermission = 0;
    }

    public function getRolePermission(int $accountId, string $permission): mixed {
        if (!is_numeric($accountId)) {
            return false;
        }
        
        $roleId = $this->db->fetchColumn(
            "SELECT roleID FROM roleassign WHERE accountID = ?",
            [$accountId]
        );

        if (!$roleId) {
            return false;
        }

        return $this->db->fetchColumn(
            "SELECT {$permission} FROM roles WHERE roleID = ?",
            [$roleId]
        );
    }

    public function featureLevel(int $accountId, int $levelId, int $state): void {
        if (!is_numeric($accountId)) {
            return;
        }

        $stateData = $this->getState($state);
        
        $this->db->update(
            'levels',
            [
                'starFeatured' => $stateData["featured"],
                'starEpic' => $stateData["epic"]
            ],
            'levelID = ?',
            [$levelId]
        );

        $this->db->insert('modactions', [
            'type' => 2,
            'value' => $state,
            'value3' => $levelId,
            'timestamp' => time(),
            'account' => $accountId
        ]);
    }

    public function getState(int $state): array {
        $states = [
            0 => ['featured' => 0, 'epic' => 0],
            1 => ['featured' => 1, 'epic' => 0],
            2 => ['featured' => 1, 'epic' => 1],
            3 => ['featured' => 1, 'epic' => 2],
            4 => ['featured' => 1, 'epic' => 3]
        ];

        return $states[$state] ?? $states[0];
    }
    
    public function getOwnerList(int $listId): ?int {
        if (!is_numeric($listId)) {
            return null;
        }
        
        return $this->db->fetchColumn(
            "SELECT accountID FROM lists WHERE listID = ?",
            [$listId]
        );
    }

    public function getListLevels(int $listId): ?string {
        if (!is_numeric($listId)) {
            return null;
        }
        
        return $this->db->fetchColumn(
            "SELECT listlevels FROM lists WHERE listID = ?",
            [$listId]
        );
    }
    
    public function getListDifficultyName(int $difficulty): string {
        if ($difficulty == -1) {
            return "N/A";
        }
        
        $diffs = ['Auto', 'Easy', 'Normal', 'Hard', 'Harder', 'Insane', 
                  'Easy Demon', 'Medium Demon', 'Hard Demon', 'Insane Demon', 'Extreme Demon'];
        
        return $diffs[$difficulty] ?? "N/A";
    }

    public function getDifficulty(int $stars = 0, string $name = "N/A", string $type = "name"): array {
        switch ($type) {
            case "name":
                $auto = ($name == "auto") ? 1 : 0;
                $demon = ($name == "demon") ? 1 : 0;
                
                $difficulty = [
                    "N/A" => 0,
                    "auto" => 50,
                    "easy" => 10,
                    "normal" => 20,
                    "hard" => 30,
                    "harder" => 40,
                    "insane" => 50,
                    "demon" => 50
                ];
            
                return [
                    'difficulty' => $difficulty[$name] ?? 0,
                    'demon' => $demon,
                    'auto' => $auto
                ];
            
            case "stars":
                $auto = ($stars == 1) ? 1 : 0;
                $demon = ($stars == 10) ? 1 : 0;

                $difficultyName = ["N/A", "Auto", "Easy", "Normal", "Hard", "Hard", 
                                   "Harder", "Harder", "Insane", "Insane", "Demon"];
                $difficulty = [0, 50, 10, 20, 30, 30, 40, 40, 50, 50, 50];

                return [
                    'difficulty' => $difficulty[$stars] ?? 0,
                    'auto' => $auto,
                    'demon' => $demon,
                    'name' => $difficultyName[$stars] ?? "N/A"
                ];
        }

        return ['difficulty' => 0, 'auto' => 0, 'demon' => 0, 'name' => "N/A"];
    }
    
    public function getListLevelsName(int $listId): ?string {
        if (!is_numeric($listId)) {
            return null;
        }
        
        return $this->db->fetchColumn(
            "SELECT listName FROM lists WHERE listID = ?",
            [$listId]
        );
    }
    
    public function getPostId(): string {
        if (!empty($_POST["udid"]) && $_POST['gameVersion'] < 20 && self::$unregisteredSubmissions) {
            $this->id = ExploitPatch::remove($_POST["udid"]);
            if (is_numeric($this->id)) {
                exit("-1");
            }
        } elseif (!empty($_POST["accountID"]) && $_POST["accountID"] != "0") {
            $this->id = GJPCheck::getAccountIDOrDie();
        } else {
            exit("-1");
        }

        return $this->id;
    }

    public function getIp(): string {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && $this->isCloudflareIp($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && ipInRange::ipv4_in_range($_SERVER['REMOTE_ADDR'], '127.0.0.0/8')) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'];
    }

    public function getIdFromName(string $userName): int {
        $accountId = $this->db->fetchColumn(
            "SELECT accountID FROM accounts WHERE userName LIKE ?",
            [$userName]
        );

        return $accountId ?: 0;
    }

    public function getUserId($extId, string $userName = "Undefined"): int {
        $register = is_numeric($extId) ? 1 : 0;

        $userId = $this->db->fetchColumn(
            "SELECT userID FROM users WHERE extID LIKE BINARY ?",
            [$extId]
        );

        if ($userId) {
            $this->userId = $userId;
        } else {
            $this->userId = $this->db->insert('users', [
                'isRegistered' => $register,
                'extID' => $extId,
                'userName' => $userName,
                'lastPlayed' => time()
            ]);
        }

        return $this->userId;
    }

    public function getFriends(int $accountId): array {
        if (!is_numeric($accountId)) {
            return [];
        }

        $friendships = $this->db->fetchAll(
            "SELECT person1, person2 FROM friendships WHERE person1 = ? OR person2 = ?",
            [$accountId, $accountId]
        );

        if (empty($friendships)) {
            return [];
        }

        $friends = [];
        foreach ($friendships as $friendship) {
            $person = ($friendship["person1"] == $accountId) 
                ? $friendship["person2"] 
                : $friendship["person1"];
            
            $friends[] = $person;
        }

        return $friends;
    }

    public function getSongString(array $song): ?string {
        if (empty($song['ID'])) {
            return null;
        }
       
        if (!empty($song["isDisabled"]) && $song["isDisabled"] == 1) {
            return null;
        }

        $download = $song["download"];
        if (strpos($download, ':') !== false) {
            $download = urlencode($download);
        }
        
        return sprintf(
            "1~|~%d~|~2~|~%s~|~3~|~%d~|~4~|~%s~|~5~|~%s~|~6~|~~|~10~|~%s~|~7~|~~|~8~|~1",
            $song["ID"],
            str_replace("#", "", $song["name"]),
            $song["authorID"],
            $song["authorName"],
            $song["size"],
            $download
        );
    }
    
    public function getUserString(array $userData): string {
        $extId = is_numeric($userData['extID']) ? $userData['extID'] : 0;
        return $userData['userID'] . ":" . $userData['userName'] . ":" . $extId;
    }

    public function isCloudflareIp(string $ip): bool {
        $cfIps = [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22'
        ];

        foreach ($cfIps as $cfIp) {
            if (ipInRange::ipv4_in_range($ip, $cfIp)) {
                return true;
            }
        }

        return false;
    }

    public function isFriends(int $accountId, int $targetAccountId): bool {
        if (!is_numeric($accountId) || !is_numeric($targetAccountId)) {
            return false;
        }

        return $this->db->exists(
            "friendships",
            "(person1 = ? AND person2 = ?) OR (person1 = ? AND person2 = ?)",
            [$accountId, $targetAccountId, $targetAccountId, $accountId]
        );
    }

    public function rateLevel(int $accountId, int $levelId, int $stars, int $difficulty, int $auto, int $demon): void {
        if (!is_numeric($accountId)) {
            return;
        }

        $this->db->update(
            'levels',
            [
                'starDemon' => $demon,
                'starAuto' => $auto,
                'starDifficulty' => $difficulty,
                'starStars' => $stars,
                'rateDate' => time()
            ],
            'levelID = ?',
            [$levelId]
        );
        
        $diff = $this->getDifficulty($stars, "", "stars");
        
        $this->db->insert('modactions', [
            'type' => 1,
            'value' => $diff["name"],
            'value2' => $stars,
            'value3' => $levelId,
            'timestamp' => time(),
            'account' => $accountId
        ]);
    }

    public function suggestLevel(int $accountId, int $levelId, int $difficulty, int $stars, int $feat, int $auto, int $demon): void {
        if (!is_numeric($accountId)) {
            return;
        }
        
        $state = $this->getState($feat);
        
        $this->db->insert('sendLevel', [
            'accountID' => $accountId,
            'levelID' => $levelId,
            'difficulty' => $difficulty,
            'stars' => $stars,
            'featured' => $state["featured"],
            'state' => $state["epic"],
            'auto' => $auto,
            'demon' => $demon,
            'timestamp' => time()
        ]);
    }

    public function verifyCoins(int $accountId, int $levelId, int $coins): void {
        if (!is_numeric($accountId)) {
            return;
        }

        $this->db->update(
            'levels',
            ['starCoins' => $coins],
            'levelID = ?',
            [$levelId]
        );
        
        $this->db->insert('modactions', [
            'type' => 3,
            'value' => $coins,
            'value3' => $levelId,
            'timestamp' => time(),
            'account' => $accountId
        ]);
    }

    public function addGauntletLevel(int $gauntlet): bool {
        $levelsGauntlet = $this->db->fetchAll(
            "SELECT * FROM gauntlets WHERE ID = ?",
            [$gauntlet]
        );

        foreach ($levelsGauntlet as $level) {
            for ($x = 1; $x <= 5; $x++) {
                $gauntletId = $this->db->fetchColumn(
                    "SELECT ID FROM gauntlets WHERE level{$x} = ?",
                    [$level['level' . $x]]
                );
            
                $this->updateGauntletLevel($gauntletId, $x, $level['level' . $x]);
            }
        }
        
        return true;
    }

    public function updateGauntletLevel(int $gauntletId, int $levelPos, int $level): void {
        $this->db->update(
            'levels',
            [
                'gauntletID' => $gauntletId,
                'gauntletLevel' => $levelPos
            ],
            'levelID = ?',
            [$level]
        );
    }

    public static function formatBytes(float $bytes, int $precision = 2): float { 
        return round(pow(1024, log($bytes, 1024) - floor(log($bytes, 1024))), $precision); 
    }
}