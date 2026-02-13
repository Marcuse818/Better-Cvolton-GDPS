<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/Lib.php";
require_once __DIR__."/lib/XORCipher.php";
require_once __DIR__."/lib/exploitPatch.php";
require_once __DIR__."/lib/generateHash.php";

interface LevelInterface {
    public function existLevel(string $levelName, int $userId): int;
    public function delete(int $userId, int $levelId): string;
    public function download(int $accountId, int $levelId, bool $inc, bool $extras, string $hostname): string;
    public function getDaily(int $type): string;
    public function rateStar(int $accountId, int $levelId, int $starStars): string;
    public function rateDemon(int $accountId, int $levelId, int $rating): string;
    public function rateSuggest(int $accountId, int $levelId, int $starStars, int $feature, array $difficulty): string;
    public function report(int $levelId, string $hostname): string;
    public function updateDesc(int $accountId, int $levelId, string $levelDescription): string;
    public function upload(
        int $accountId, 
        int $levelId, 
        string $userName,
        string $hostname,
        int $userId,
        string $levelName,
        int $audioTrack,
        int $levelLength,
        string $secret,
        string $levelString,
        string $gjp,
        int $levelVersion,
        int $ts,
        string $songs,
        string $sfxs,
        int $auto,
        int $original,
        int $twoPlayer,
        int $songId,
        int $object,
        int $coins,
        int $requestedStars,
        string $extraString,
        string $levelInfo,
        int $unlisted,
        int $unlisted2,
        int $ldm,
        int $wt,
        int $wt2,
        string $settingsString,
        string $levelDescription,
        int $password
    ): int;
}

class Level implements LevelInterface {
    private Database $db;
    private Main $main;
    private Lib $lib;

    private int $uploadDate;
    private int $updateDate;
    
    public int $gameVersion = 0;
    public int $binaryVersion = 0;
    
    public function __construct() {
        $this->lib = new Lib();
        $this->main = new Main();
        $this->db = new Database();

        $this->uploadDate = time();
        $this->updateDate = time();
    }

    public function existLevel(string $levelName, int $userId): int {
        try {
            $count = $this->db->count(
                "levels",
                "levelName = ? AND userID = ?",
                [$levelName, $userId]
            );
            
            return $count > 0 ? 1 : 0;

        } catch (Exception $e) {
            error_log("Level existLevel error: " . $e->getMessage());
            return 0;
        }
    }

    public function delete(int $userId, int $levelId): string { 
        try {
            $deleted = $this->db->delete(
                'levels',
                'levelID = ? AND userID = ? AND starStars = 0',
                [$levelId, $userId]
            );

            if ($deleted === 0) {
                return "-1";
            }

            $this->db->insert('actions', [
                'type' => 8,
                'value' => $levelId,
                'timestamp' => time(),
                'value2' => $userId
            ]);

            $levelFile = __DIR__."/../database/data/levels/$levelId";
            $deletedDir = __DIR__."/../database/data/levels/deleted/$levelId";
            
            if (file_exists($levelFile)) {
                if (!is_dir(dirname($deletedDir))) {
                    mkdir(dirname($deletedDir), 0755, true);
                }
                rename($levelFile, $deletedDir);
                return "1";
            }

            return "-1";

        } catch (Exception $e) {
            error_log("Level delete error: " . $e->getMessage());
            return "-1";
        }
    }

    public function download(int $accountId, int $levelId, bool $inc, bool $extras, string $hostname): string {
        try {
            if (!is_numeric($levelId)) {
                return "-1";
            }

            $dailyData = $this->handleDailyLevel($levelId);
            $actualLevelId = $dailyData['levelId'] ?? $levelId;
            $feaId = $dailyData['feaId'] ?? 0;
            $daily = $dailyData['isDaily'] ?? 0;

            $levelData = $this->getLevelData($actualLevelId, $daily);
            if (!$levelData) {
                return "";
            }

            if ($inc) {
                $this->incrementDownloadCount($actualLevelId, $hostname);
            }

            return $this->buildDownloadResponse($levelData, $actualLevelId, $daily, $feaId, $extras);

        } catch (Exception $e) {
            error_log("Level download error: " . $e->getMessage());
            return "-1";
        }
    }

    private function handleDailyLevel(int $levelId): array {
        $dailyTypes = [
            -1 => ['type' => 0, 'offset' => 0],
            -2 => ['type' => 1, 'offset' => 100001],
            -3 => ['type' => 2, 'offset' => 200001]
        ];

        if (!isset($dailyTypes[$levelId])) {
            return ['isDaily' => 0, 'levelId' => $levelId];
        }

        $config = $dailyTypes[$levelId];
        $daily = $this->db->fetchOne(
            "SELECT feaID, levelID FROM dailyfeatures 
             WHERE timestamp < ? AND type = ? 
             ORDER BY timestamp DESC LIMIT 1",
            [time(), $config['type']]
        );

        if ($daily) {
            return [
                'isDaily' => 1,
                'levelId' => $daily['levelID'],
                'feaId' => $daily['feaID'] + $config['offset']
            ];
        }

        return ['isDaily' => 0, 'levelId' => $levelId];
    }

    private function getLevelData(int $levelId, bool $isDaily): ?array {
        if ($isDaily) {
            return $this->db->fetchOne(
                "SELECT levels.*, users.userName, users.extID 
                 FROM levels 
                 LEFT JOIN users ON levels.userID = users.userID 
                 WHERE levelID = ?",
                [$levelId]
            );
        }

        return $this->db->fetchOne(
            "SELECT * FROM levels WHERE levelID = ?",
            [$levelId]
        );
    }

    private function incrementDownloadCount(int $levelId, string $hostname): void {
        $downloadCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM actions_downloads WHERE levelID = ? AND ip = INET6_ATON(?)",
            [$levelId, $hostname]
        );

        if ($downloadCount < 2) {
            $this->db->execute(
                "UPDATE levels SET downloads = downloads + 1 WHERE levelID = ?",
                [$levelId]
            );

            $this->db->insert('actions_downloads', [
                'levelID' => $levelId,
                'ip' => $hostname
            ]);
        }
    }

    private function buildDownloadResponse(array $levelData, int $levelId, int $daily, int $feaId, bool $extras): string {
        $uploadDate = $this->lib->makeTime($levelData["uploadDate"]);
        $updateDate = $this->lib->makeTime($levelData["updateDate"]);

        $levelString = $this->getLevelString($levelId);
        $processedLevelString = $this->processLevelString($levelString);

        $levelDescription = $levelData["levelDesc"];
        $password = 1;
        $xor = $this->processPassword($password, $levelDescription);

        $response = $this->buildBaseResponse($levelData, $levelDescription, $processedLevelString, $uploadDate, $updateDate, $xor);
        
        if ($daily == 1) {
            $response .= ":41:" . $feaId;
        }
        if ($extras) {
            $response .= ":26:" . $levelData["levelInfo"];
        }

        $response .= "#" . GenerateHash::genSolo($levelString) . "#";
        
        $hashString = $this->buildHashString($levelData, $password, $feaId);
        $response .= GenerateHash::genSolo2($hashString);
        
        if ($daily == 1) {
            $response .= "#" . $this->main->getUserString($levelData["userID"]);
        }
        if ($this->binaryVersion == 30) {
            $response .= "#" . $hashString;
        }

        return $response;
    }

    private function getLevelString(int $levelId): string {
        $levelFile = __DIR__ . "/../database/data/levels/$levelId";
        return file_exists($levelFile) ? file_get_contents($levelFile) : "";
    }

    private function processLevelString(string $levelString): string {
        if ($this->gameVersion > 18 && substr($levelString, 0, 3) == "kS1") {
            $levelString = base64_encode(gzcompress($levelString));
            $levelString = str_replace(["/", "+"], ["_", "-"], $levelString);
        }
        return $levelString;
    }

    private function processPassword(int $password, string &$levelDescription): string {
        if ($this->gameVersion > 19) {
            return $password != 0 ? base64_encode(XORCipher::cipher($password, 26364)) : (string)$password;
        }
        $levelDescription = base64_decode($levelDescription);
        return (string)$password;
    }

    private function buildBaseResponse(array $levelData, string $description, string $levelString, string $uploadDate, string $updateDate, string $xor): string {
        $fields = [
            1 => $levelData["levelID"],
            2 => $levelData["levelName"],
            3 => $description,
            4 => $levelString,
            5 => $levelData["levelVersion"],
            6 => $levelData["userID"],
            8 => 10,
            9 => $levelData["starDifficulty"],
            10 => $levelData["downloads"],
            11 => 1,
            12 => $levelData["audioTrack"],
            13 => $levelData["gameVersion"],
            14 => $levelData["likes"],
            17 => $levelData["starDemon"],
            43 => $levelData["starDemonDiff"],
            25 => $levelData["starAuto"],
            18 => $levelData["starStars"],
            19 => $levelData["starFeatured"],
            42 => $levelData["starEpic"],
            45 => $levelData["objects"],
            15 => $levelData["levelLength"],
            30 => $levelData["original"],
            31 => $levelData["twoPlayer"],
            28 => $uploadDate,
            29 => $updateDate,
            35 => $levelData["songID"],
            36 => $levelData["extraString"],
            37 => $levelData["coins"],
            38 => $levelData["starCoins"],
            39 => $levelData["requestedStars"],
            46 => $levelData["wt"],
            47 => $levelData["wt2"],
            48 => $levelData["settingsString"],
            40 => $levelData["isLDM"],
            27 => $xor,
            52 => $levelData["songIDs"],
            53 => $levelData["sfxIDs"],
            57 => $levelData["ts"]
        ];

        $parts = [];
        foreach ($fields as $key => $value) {
            $parts[] = "$key:$value";
        }

        return implode(":", $parts);
    }

    private function buildHashString(array $levelData, int $password, int $feaId): string {
        return implode(",", [
            $levelData["userID"],
            $levelData["starStars"],
            $levelData["starDemon"],
            $levelData["levelID"],
            $levelData["starCoins"],
            $levelData["starFeatured"],
            $password,
            $feaId
        ]);
    }

    public function getDaily(int $type): string {
        try {
            $dailyLevelId = $this->db->fetchColumn(
                "SELECT feaID FROM dailyfeatures 
                 WHERE timestamp < ? AND type = ? 
                 ORDER BY timestamp DESC LIMIT 1",
                [time(), $type]
            );

            if (!$dailyLevelId) {
                return "-1";
            }
            
            $midnight = ($type == 1) ? strtotime('next monday') : strtotime('tomorrow 00:00:00');

            if ($type == 1) $dailyLevelId += 100001;
            if ($type == 2) $dailyLevelId += 200001;
            
            $timeLeft = $midnight - time();
            return $dailyLevelId . "|" . $timeLeft;

        } catch (Exception $e) {
            error_log("Level getDaily error: " . $e->getMessage());
            return "-1";
        }
    }

    public function rateStar(int $accountId, int $levelId, int $starStars): string {
        try {
            if (!is_numeric($accountId)) {
                return "-1";
            }
            
            $difficulty = $this->main->getDifficulty($starStars, "", "stars");
                    
            $this->db->insert('action_rate', [
                'accountID' => $accountId,
                'levelID' => $levelId,
                'difficulty' => $difficulty["difficulty"]
            ]);
                    
            $rateStats = $this->db->fetchOne(
                "SELECT difficulty, COUNT(*) as CNT 
                 FROM action_rate 
                 WHERE levelID = ? AND isRated = 0
                 GROUP BY difficulty 
                 HAVING COUNT(*) > 0 
                 ORDER BY CNT DESC 
                 LIMIT 1",
                [$levelId]
            );
            
            if (!$rateStats || $rateStats["CNT"] <= 5) {
                return "-1";
            }
            
            $avgDifficulty = $this->db->fetchColumn(
                "SELECT AVG(difficulty) FROM action_rate WHERE levelID = ?",
                [$levelId]
            );

            $diff = (int)round($avgDifficulty);
            $auto = ($difficulty["auto"] == 1 && $diff == 10) ? 1 : 0;
            $demon = ($difficulty["demon"] == 1 && $diff == 50) ? 1 : 0;
            
            $this->main->rateLevel($accountId, $levelId, 0, $diff, $auto, $demon);
            
            $this->db->execute(
                "UPDATE action_rate SET isRated = 1 WHERE levelID = ?",
                [$levelId]
            );
                
            return "1";

        } catch (Exception $e) {
            error_log("Level rateStar error: " . $e->getMessage());
            return "-1";
        }
    }

    public function rateDemon(int $accountId, int $levelId, int $rating): string {
        try {
            if (!$this->main->getRolePermission($accountId, "actionRateDemon")) {
                return "-1";
            }

            $data = $this->lib->demonFilter($rating);

            $this->db->update(
                'levels',
                ['starDemonDiff' => $data["demon"]],
                'levelID = ?',
                [$levelId]
            );

            $this->db->insert('modactions', [
                'type' => 10,
                'value' => $data["name"],
                'value3' => $levelId,
                'timestamp' => $this->uploadDate,
                'account' => $accountId
            ]);
                
            return (string)$levelId;

        } catch (Exception $e) {
            error_log("Level rateDemon error: " . $e->getMessage());
            return "-1";
        }
    }

    public function rateSuggest(int $accountId, int $levelId, int $starStars, int $feature, array $difficulty): string {
        try {
            if ($this->main->getRolePermission($accountId, "actionRateStars")) {
                $this->main->rateLevel($accountId, $levelId, $starStars, $difficulty["difficulty"], $difficulty["auto"], $difficulty["demon"]);
                $this->main->featureLevel($accountId, $levelId, $feature);
                $this->main->verifyCoins($accountId, $levelId, 1);
                return "1";
            }

            if ($this->main->getRolePermission($accountId, "actionSuggestRating")) {
                $this->main->suggestLevel($accountId, $levelId, $difficulty["difficulty"], $starStars, $feature, $difficulty["auto"], $difficulty["demon"]);
                return "1";
            }

            return "-2";

        } catch (Exception $e) {
            error_log("Level rateSuggest error: " . $e->getMessage());
            return "-1";
        }
    }

    public function report(int $levelId, string $hostname): string {
        try {
            $existingReport = $this->db->exists(
                "reports",
                "levelID = ? AND hostname = ?",
                [$levelId, $hostname]
            );

            if ($existingReport) {
                return "-1";
            }

            $newId = $this->db->insert('reports', [
                'levelID' => $levelId,
                'hostname' => $hostname
            ]);

            return (string)$newId;

        } catch (Exception $e) {
            error_log("Level report error: " . $e->getMessage());
            return "-1";
        }
    }

    public function updateDesc(int $accountId, int $levelId, string $levelDescription): string {
        try {
            $rawDescription = $this->decodeLevelDescription($levelDescription);
            $processedDescription = $this->fixColorTags($rawDescription);
            $finalDescription = $this->encodeLevelDescription($processedDescription);

            $this->db->update(
                'levels',
                ['levelDesc' => $finalDescription],
                'levelID = ? AND extID = ?',
                [$levelId, $accountId]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Level updateDesc error: " . $e->getMessage());
            return "-1";
        }
    }

    private function decodeLevelDescription(string $description): string {
        $decoded = str_replace(['-', '_'], ['+', '/'], $description);
        return base64_decode($decoded);
    }

    private function encodeLevelDescription(string $description): string {
        $encoded = base64_encode($description);
        return str_replace(['+', '/'], ['-', '_'], $encoded);
    }

    private function fixColorTags(string $description): string {
        $openTags = substr_count($description, "<c");
        $closeTags = substr_count($description, "</c>");

        if ($openTags > $closeTags) {
            $missingTags = $openTags - $closeTags;
            $description .= str_repeat('</c>', $missingTags);
        }

        return $description;
    }

    public function upload(
        int $accountId, 
        int $levelId, 
        string $userName,
        string $hostname,
        int $userId,
        string $levelName,
        int $audioTrack,
        int $levelLength,
        string $secret,
        string $levelString,
        string $gjp,
        int $levelVersion,
        int $ts,
        string $songs,
        string $sfxs,
        int $auto,
        int $original,
        int $twoPlayer,
        int $songId,
        int $object,
        int $coins,
        int $requestedStars,
        string $extraString,
        string $levelInfo,
        int $unlisted,
        int $unlisted2,
        int $ldm,
        int $wt,
        int $wt2,
        string $settingsString,
        string $levelDescription,
        int $password
    ): int {
        try {
            $recentUploads = $this->db->count(
                "levels",
                "uploadDate > ? AND (userID = ? OR hostname = ?)",
                [$this->uploadDate - 30, $userId, $hostname]
            );
            
            if ($recentUploads > 0) {
                return -1;
            }

            if (empty($levelString) || empty($levelName)) {
                return -1;
            }

            $levelData = [
                'levelName' => $levelName,
                'gameVersion' => $this->gameVersion,
                'binaryVersion' => $this->binaryVersion,
                'userName' => $userName,
                'levelDesc' => $levelDescription,
                'levelVersion' => $levelVersion,
                'levelLength' => $levelLength,
                'audioTrack' => $audioTrack,
                'auto' => $auto,
                'password' => $password,
                'original' => $original,
                'twoPlayer' => $twoPlayer,
                'songID' => $songId,
                'objects' => $object,
                'coins' => $coins,
                'requestedStars' => $requestedStars,
                'extraString' => $extraString,
                'levelString' => $levelString,
                'levelInfo' => $levelInfo,
                'secret' => $secret,
                'updateDate' => $this->updateDate,
                'unlisted' => $unlisted,
                'hostname' => $hostname,
                'isLDM' => $ldm,
                'wt' => $wt,
                'wt2' => $wt2,
                'unlisted2' => $unlisted2,
                'settingsString' => $settingsString,
                'songIDs' => $songs,
                'sfxIDs' => $sfxs,
                'ts' => $ts
            ];

            if ($this->existLevel($levelName, $userId) == 1) {
                $levelData['extID'] = $accountId;
                
                $this->db->update(
                    'levels',
                    $levelData,
                    'levelName = ? AND extID = ?',
                    [$levelName, $accountId]
                );
                
                $this->saveLevelFile($levelId, $levelString);
                return $levelId;
            }
            
            $levelData['uploadDate'] = $this->uploadDate;
            $levelData['userID'] = $userId;
            $levelData['extID'] = $accountId;

            $newLevelId = $this->db->insert('levels', $levelData);
            
            $this->saveLevelFile($newLevelId, $levelString);
            return $newLevelId;

        } catch (Exception $e) {
            error_log("Level upload error: " . $e->getMessage());
            return -1;
        }
    }

    private function saveLevelFile(int $levelId, string $levelString): void {
        $levelDir = __DIR__ . "/../database/data/levels";
        if (!is_dir($levelDir)) {
            mkdir($levelDir, 0755, true);
        }
        
        file_put_contents("$levelDir/$levelId", $levelString);
    }
}