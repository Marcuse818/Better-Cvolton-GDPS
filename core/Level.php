<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/Lib.php";
require_once __DIR__."/lib/XORCipher.php";
require_once __DIR__."/lib/exploitPatch.php";
require_once __DIR__."/lib/generateHash.php";

interface LevelInterface {
    public function existLevel(string $levelName, int $userID): int;
    public function delete(int $userID, int $levelID): string;
    public function download(int $accountID, int $levelID, $inc, $extras, $hostname): string;
    public function getDaily(int $type): string;
    public function rateStar(int $accountID, int $levelID, int $starStars): string;
    public function rateDemon(int $accountID, int $levelID, int $rating): string;
    public function rateSuggest(int $accountID, int $levelID, int $starStars, int $feature, array $difficulty): string;
    public function report(int $levelID, $hostname): string;
    public function updateDesc($accountID, int $levelID, string $levelDescription): string;
    public function upload(
        int $accountID, 
        int $levelID, 
        $userName,
        $hostname,
        $userID,
        $levelName,
        $audioTrack,
        $levelLength,
        $secret,
        $levelString,
        $gjp,
        $levelVersion,
        $ts,
        $songs,
        $sfxs,
        $auto,
        $original,
        $twoPlayer,
        $songID,
        $object,
        $coins,
        $requestedStars,
        $extraString,
        $levelInfo,
        $unlisted,
        $unlisted2,
        $ldm,
        $wt,
        $wt2,
        $settingsString,
        $levelDescription,
        $password
    ): int;
}

class Level implements LevelInterface {
    protected $database;
    protected $main, $lib;

    private $uploadDate, $updateDate;
    public $gameVersion, $binaryVersion;
    
    public function __construct() {
        $this->lib = new Lib();
        $this->main = new Main();
        $this->database = new Database();

        $this->uploadDate = time();
        $this->updateDate = time();
    }

    public function existLevel(string $levelName, int $userID): int {
        try {
            $count = $this->database->count(
                "levels",
                "levelName = :levelName AND userID = :userID",
                [
                    ":levelName" => $levelName,
                    ":userID" => $userID
                ]
            );
            
            return $count > 0 ? 1 : 0;

        } catch (Exception $e) {
            error_log("Level existLevel error: " . $e->getMessage());
            return 0;
        }
    }

    public function delete(int $userID, int $levelID): string {
        try {
            // Удаляем уровень из базы данных
            $deleted = $this->database->execute(
                "DELETE FROM levels WHERE levelID = :levelID AND userID = :userID AND starStars = 0 LIMIT 1",
                [
                    ":levelID" => $levelID,
                    ":userID" => $userID
                ]
            );

            if (!$deleted) {
                return "-1";
            }

            // Логируем действие
            $this->database->insert(
                "INSERT INTO actions (type, value, timestamp, value2) VALUES (:type, :itemID, :time, :ip)",
                [
                    ":type" => 8,
                    ":itemID" => $levelID,
                    ":time" => time(),
                    ":ip" => $userID
                ]
            );

            // Перемещаем файл уровня
            $levelFile = __DIR__."/../database/data/levels/$levelID";
            $deletedDir = __DIR__."/../database/data/levels/deleted/$levelID";
            
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

    public function download(int $accountID, int $levelID, $inc, $extras, $hostname): string {
        try {
            if (!is_numeric($levelID)) return "-1";

            $dailyData = $this->handleDailyLevel($levelID);
            $actualLevelID = $dailyData['levelID'] ?? $levelID;
            $feaID = $dailyData['feaID'] ?? 0;
            $daily = $dailyData['isDaily'] ?? 0;

            // Получаем данные уровня
            $levelData = $this->getLevelData($actualLevelID, $daily);
            if (!$levelData) {
                return "";
            }

            // Обновляем счетчик загрузок
            if ($inc) {
                $this->incrementDownloadCount($actualLevelID, $hostname);
            }

            // Формируем ответ
            return $this->buildDownloadResponse($levelData, $actualLevelID, $daily, $feaID, $extras);

        } catch (Exception $e) {
            error_log("Level download error: " . $e->getMessage());
            return "-1";
        }
    }

    private function handleDailyLevel(int $levelID): array {
        $dailyTypes = [
            -1 => ['type' => 0, 'offset' => 0],
            -2 => ['type' => 1, 'offset' => 100001],
            -3 => ['type' => 2, 'offset' => 200001]
        ];

        if (!isset($dailyTypes[$levelID])) {
            return ['isDaily' => 0, 'levelID' => $levelID];
        }

        $config = $dailyTypes[$levelID];
        $daily = $this->database->fetch_one(
            "SELECT feaID, levelID FROM dailyfeatures WHERE timestamp < :time AND type = :type ORDER BY timestamp DESC LIMIT 1",
            [
                ":time" => time(),
                ":type" => $config['type']
            ]
        );

        if ($daily) {
            return [
                'isDaily' => 1,
                'levelID' => $daily['levelID'],
                'feaID' => $daily['feaID'] + $config['offset']
            ];
        }

        return ['isDaily' => 0, 'levelID' => $levelID];
    }

    private function getLevelData(int $levelID, bool $isDaily = false): ?array {
        if ($isDaily) {
            return $this->database->fetch_one(
                "SELECT levels.*, users.userName, users.extID 
                 FROM levels 
                 LEFT JOIN users ON levels.userID = users.userID 
                 WHERE levelID = :levelID",
                [":levelID" => $levelID]
            );
        }

        return $this->database->fetch_one(
            "SELECT * FROM levels WHERE levelID = :levelID",
            [":levelID" => $levelID]
        );
    }

    private function incrementDownloadCount(int $levelID, string $hostname): void {
        $downloadCount = $this->database->fetch_column(
            "SELECT COUNT(*) FROM actions_downloads WHERE levelID = :levelID AND ip = INET6_ATON(:ip)",
            [
                ":levelID" => $levelID,
                ":ip" => $hostname
            ]
        );

        if ($downloadCount < 2) {
            $this->database->execute(
                "UPDATE levels SET downloads = downloads + 1 WHERE levelID = :levelID",
                [":levelID" => $levelID]
            );

            $this->database->insert(
                "INSERT INTO actions_downloads (levelID, ip) VALUES (:levelID, INET6_ATON(:ip))",
                [
                    ":levelID" => $levelID,
                    ":ip" => $hostname
                ]
            );
        }
    }

    private function buildDownloadResponse(array $levelData, int $levelID, int $daily, int $feaID, bool $extras): string {
        $uploadDate = $this->lib->make_time($levelData["uploadDate"]);
        $updateDate = $this->lib->make_time($levelData["updateDate"]);

        // Обработка уровня
        $levelString = $this->getLevelString($levelID);
        $processedLevelString = $this->processLevelString($levelString);

        // Обработка описания и пароля
        $levelDescription = $levelData["levelDesc"];
        $xor = $this->processLevelDescription($levelDescription, $levelData["password"]);

        // Формируем основной ответ
        $response = $this->buildBaseResponse($levelData, $levelDescription, $processedLevelString, $uploadDate, $updateDate, $xor);
        
        // Добавляем дополнительные поля
        if ($daily == 1) $response .= ":41:" . $feaID;
        if ($extras) $response .= ":26:" . $levelData["levelInfo"];

        // Добавляем хэши
        $response .= "#" . GenerateHash::genSolo($levelString) . "#";
        
        $hashString = $this->buildHashString($levelData, $levelData["password"], $feaID);
        $response .= GenerateHash::genSolo2($hashString);
        
        // Добавляем дополнительную информацию
        if ($daily == 1) $response .= "#" . $this->main->get_user_string($levelData);
        if ($this->binaryVersion == 30) $response .= "#" . $hashString;

        return $response;
    }

    private function getLevelString(int $levelID): string {
        $levelFile = __DIR__ . "/../database/data/levels/$levelID";
        return file_exists($levelFile) ? file_get_contents($levelFile) : "";
    }

    private function processLevelString(string $levelString): string {
        if ($this->gameVersion > 18 && substr($levelString, 0, 3) == "kS1") {
            $levelString = base64_encode(gzcompress($levelString));
            $levelString = str_replace(["/", "+"], ["_", "-"], $levelString);
        }
        return $levelString;
    }

    private function processLevelDescription(string $levelDescription, int $password): string {
        if ($this->gameVersion > 19) {
            return $password != 0 ? base64_encode(XORCipher::cipher($password, 26364)) : $password;
        } else {
            return base64_decode($levelDescription);
        }
    }

    private function buildBaseResponse(array $levelData, string $description, string $levelString, string $uploadDate, string $updateDate, $xor): string {
        $base = [
            "1:" . $levelData["levelID"],
            "2:" . $levelData["levelName"],
            "3:" . $description,
            "4:" . $levelString,
            "5:" . $levelData["levelVersion"],
            "6:" . $levelData["userID"],
            "8:10",
            "9:" . $levelData["starDifficulty"],
            "10:" . $levelData["downloads"],
            "11:1",
            "12:" . $levelData["audioTrack"],
            "13:" . $levelData["gameVersion"],
            "14:" . $levelData["likes"],
            "17:" . $levelData["starDemon"],
            "43:" . $levelData["starDemonDiff"],
            "25:" . $levelData["starAuto"],
            "18:" . $levelData["starStars"],
            "19:" . $levelData["starFeatured"],
            "42:" . $levelData["starEpic"],
            "45:" . $levelData["objects"],
            "15:" . $levelData["levelLength"],
            "30:" . $levelData["original"],
            "31:" . $levelData["twoPlayer"],
            "28:" . $uploadDate,
            "29:" . $updateDate,
            "35:" . $levelData["songID"],
            "36:" . $levelData["extraString"],
            "37:" . $levelData["coins"],
            "38:" . $levelData["starCoins"],
            "39:" . $levelData["requestedStars"],
            "46:" . $levelData["wt"],
            "47:" . $levelData["wt2"],
            "48:" . $levelData["settingsString"],
            "40:" . $levelData["isLDM"],
            "27:" . $xor,
            "52:" . $levelData["songIDs"],
            "53:" . $levelData["sfxIDs"],
            "57:" . $levelData["ts"]
        ];

        return implode(":", $base);
    }

    private function buildHashString(array $levelData, int $password, int $feaID): string {
        return implode(",", [
            $levelData["userID"],
            $levelData["starStars"],
            $levelData["starDemon"],
            $levelData["levelID"],
            $levelData["starCoins"],
            $levelData["starFeatured"],
            $password,
            $feaID
        ]);
    }

    public function getDaily(int $type): string {
        try {
            $daily = $this->database->fetch_column(
                "SELECT feaID FROM dailyfeatures WHERE timestamp < :current AND type = :type ORDER BY timestamp DESC LIMIT 1",
                [
                    ":current" => time(),
                    ":type" => $type
                ]
            );

            if (!$daily) return "-1";

            $midnight = ($type == 1) ? strtotime('next monday') : strtotime('tomorrow 00:00:00');
            
            if ($type == 1) $daily += 100001;
            if ($type == 2) $daily += 200001;
            
            $timeleft = $midnight - time();
            return $daily . "|" . $timeleft;

        } catch (Exception $e) {
            error_log("Level getDaily error: " . $e->getMessage());
            return "-1";
        }
    }

    public function rateStar(int $accountID, int $levelID, int $starStars): string {
        try {
            if (!is_numeric($accountID)) return "-1";

            $difficulty = $this->main->get_difficulty($starStars, "", "stars");
            
            // Записываем оценку
            $this->database->insert(
                "INSERT INTO action_rate (accountID, levelID, difficulty) VALUES (:accountID, :levelID, :difficulty)",
                [
                    ":accountID" => $accountID,
                    ":levelID" => $levelID,
                    ":difficulty" => $difficulty["difficulty"]
                ]
            );

            // Проверяем существующие оценки
            $rateStats = $this->database->fetch_one(
                "SELECT difficulty, COUNT(*) as CNT 
                 FROM action_rate 
                 WHERE levelID = :levelID 
                 GROUP BY difficulty 
                 HAVING COUNT(*) > 0 
                 ORDER BY CNT DESC 
                 LIMIT 1",
                [":levelID" => $levelID]
            );

            if (!$rateStats || $rateStats["CNT"] <= 5) {
                return "-1";
            }

            // Получаем среднюю сложность
            $avgDifficulty = $this->database->fetch_column(
                "SELECT AVG(difficulty) FROM action_rate WHERE levelID = :levelID",
                [":levelID" => $levelID]
            );

            $diff = round($avgDifficulty);
            $auto = ($difficulty["auto"] == 1 && $diff == 10) ? 1 : 0;
            $demon = ($difficulty["demon"] == 1 && $diff == 50) ? 1 : 0;

            // Рейтим уровень
            $this->main->rate_level($accountID, $levelID, 0, $diff, $auto, $demon);
            
            // Помечаем оценки как обработанные
            $this->database->execute(
                "UPDATE action_rate SET isRated = 1 WHERE levelID = :levelID",
                [":levelID" => $levelID]
            );

            return "1";

        } catch (Exception $e) {
            error_log("Level rateStar error: " . $e->getMessage());
            return "-1";
        }
    }

    public function rateDemon(int $accountID, int $levelID, int $rating): string {
        try {
            if (!$this->main->getRolePermission($accountID, "actionRateDemon")) {
                return "-1";
            }

            $data = $this->lib->demon_filter($rating);

            $this->database->execute(
                "UPDATE levels SET starDemonDiff = :demon WHERE levelID = :levelID",
                [
                    ":demon" => $data["demon"],
                    ":levelID" => $levelID
                ]
            );

            $this->database->insert(
                "INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('10', :value, :levelID, :timestamp, :id)",
                [
                    ":value" => $data["name"],
                    ":levelID" => $levelID,
                    ":timestamp" => $this->uploadDate,
                    ":id" => $accountID
                ]
            );

            return (string)$levelID;

        } catch (Exception $e) {
            error_log("Level rateDemon error: " . $e->getMessage());
            return "-1";
        }
    }

    public function rateSuggest(int $accountID, int $levelID, int $starStars, int $feature, array $difficulty): string {
        try {
            if ($this->main->getRolePermission($accountID, "actionRateStars")) {
                $this->main->rate_level($accountID, $levelID, $starStars, $difficulty["difficulty"], $difficulty["auto"], $difficulty["demon"]);
                $this->main->feature_level($accountID, $levelID, $feature);
                $this->main->verify_coins($accountID, $levelID, 1);
                return "1";
            }

            if ($this->main->getRolePermission($accountID, "actionSuggestRating")) {
                $this->main->suggest_level($accountID, $levelID, $difficulty["difficulty"], $starStars, $feature, $difficulty["auto"], $difficulty["demon"]);
                return "1";
            }

            return "-2";

        } catch (Exception $e) {
            error_log("Level rateSuggest error: " . $e->getMessage());
            return "-1";
        }
    }

    public function report(int $levelID, $hostname): string {
        try {
            $existingReport = $this->database->count(
                "reports",
                "levelID = :levelID AND hostname = :hostname",
                [
                    ":levelID" => $levelID,
                    ":hostname" => $hostname
                ]
            );

            if ($existingReport > 0) {
                return "-1";
            }

            return (string)$this->database->insert(
                "INSERT INTO reports (levelID, hostname) VALUES (:levelID, :hostname)",
                [
                    ":levelID" => $levelID,
                    ":hostname" => $hostname
                ]
            );

        } catch (Exception $e) {
            error_log("Level report error: " . $e->getMessage());
            return "-1";
        }
    }

    public function updateDesc($accountID, int $levelID, string $levelDescription): string {
        try {
            $rawDescription = $this->decodeLevelDescription($levelDescription);
            $processedDescription = $this->fixColorTags($rawDescription);
            $finalDescription = $this->encodeLevelDescription($processedDescription);

            $this->database->execute(
                "UPDATE levels SET levelDesc = :levelDesc WHERE levelID = :levelID AND extID = :extID",
                [
                    ":levelDesc" => $finalDescription,
                    ":levelID" => $levelID,
                    ":extID" => $accountID
                ]
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
            for ($i = 0; $i < $missingTags; $i++) {
                $description .= '</c>';
            }
        }

        return $description;
    }

    public function upload(
        int $accountID, 
        int $levelID, 
        $userName,
        $hostname,
        $userID,
        $levelName,
        $audioTrack,
        $levelLength,
        $secret,
        $levelString,
        $gjp,
        $levelVersion,
        $ts,
        $songs,
        $sfxs,
        $auto,
        $original,
        $twoPlayer,
        $songID,
        $object,
        $coins,
        $requestedStars,
        $extraString,
        $levelInfo,
        $unlisted,
        $unlisted2,
        $ldm,
        $wt,
        $wt2,
        $settingsString,
        $levelDescription,
        $password
    ): int {
        try {
            // Проверка ограничений по времени
            $recentUploads = $this->database->count(
                "levels",
                "uploadDate > :time AND (userID = :userID OR hostname = :ip)",
                [
                    ":time" => $this->uploadDate - 30,
                    ":userID" => $userID,
                    ":ip" => $hostname
                ]
            );

            if ($recentUploads > 0) {
                return -1;
            }

            if (empty($levelString) || empty($levelName)) {
                return -1;
            }

            // Данные для вставки/обновления
            $levelData = [
                ":levelName" => $levelName,
                ":gameVersion" => $this->gameVersion,
                ":binaryVersion" => $this->binaryVersion,
                ":userName" => $userName,
                ":levelDesc" => $levelDescription,
                ":levelVersion" => $levelVersion,
                ":levelLength" => $levelLength,
                ":audioTrack" => $audioTrack,
                ":auto" => $auto,
                ":password" => $password,
                ":original" => $original,
                ":twoPlayer" => $twoPlayer,
                ":songID" => $songID,
                ":objects" => $object,
                ":coins" => $coins,
                ":requestedStars" => $requestedStars,
                ":extraString" => $extraString,
                ":levelString" => $levelString,
                ":levelInfo" => $levelInfo,
                ":secret" => $secret,
                ":updateDate" => $this->updateDate,
                ":unlisted" => $unlisted,
                ":hostname" => $hostname,
                ":ldm" => $ldm,
                ":wt" => $wt,
                ":wt2" => $wt2,
                ":unlisted2" => $unlisted2,
                ":settingsString" => $settingsString,
                ":songs" => $songs,
                ":sfxs" => $sfxs,
                ":ts" => $ts
            ];

            if ($this->existLevel($levelName, $userID) == 1) {
                // Обновление существующего уровня
                $levelData[":id"] = $accountID;
                $this->database->execute(
                    "UPDATE levels SET 
                     levelName = :levelName, gameVersion = :gameVersion, binaryVersion = :binaryVersion, 
                     userName = :userName, levelDesc = :levelDesc, levelVersion = :levelVersion, 
                     levelLength = :levelLength, audioTrack = :audioTrack, auto = :auto, 
                     password = :password, original = :original, twoPlayer = :twoPlayer, 
                     songID = :songID, objects = :objects, coins = :coins, 
                     requestedStars = :requestedStars, extraString = :extraString, 
                     levelString = :levelString, levelInfo = :levelInfo, secret = :secret, 
                     updateDate = :updateDate, unlisted = :unlisted, hostname = :hostname, 
                     isLDM = :ldm, wt = :wt, wt2 = :wt2, unlisted2 = :unlisted2, 
                     settingsString = :settingsString, songIDs = :songs, sfxIDs = :sfxs, ts = :ts 
                     WHERE levelName = :levelName AND extID = :id",
                    $levelData
                );
                
                $this->saveLevelFile($levelID, $levelString);
                return $levelID;
            } else {
                // Создание нового уровня
                $levelData[":uploadDate"] = $this->uploadDate;
                $levelData[":userID"] = $userID;
                $levelData[":id"] = $accountID;

                $newLevelID = $this->database->insert(
                    "INSERT INTO levels (
                     levelName, gameVersion, binaryVersion, userName, levelDesc, levelVersion, 
                     levelLength, audioTrack, auto, password, original, twoPlayer, songID, 
                     objects, coins, requestedStars, extraString, levelString, levelInfo, 
                     secret, uploadDate, userID, extID, updateDate, unlisted, hostname, 
                     isLDM, wt, wt2, unlisted2, settingsString, songIDs, sfxIDs, ts
                     ) VALUES (
                     :levelName, :gameVersion, :binaryVersion, :userName, :levelDesc, :levelVersion,
                     :levelLength, :audioTrack, :auto, :password, :original, :twoPlayer, :songID,
                     :objects, :coins, :requestedStars, :extraString, :levelString, :levelInfo,
                     :secret, :uploadDate, :userID, :id, :updateDate, :unlisted, :hostname,
                     :ldm, :wt, :wt2, :unlisted2, :settingsString, :songs, :sfxs, :ts
                     )",
                    $levelData
                );
                
                $this->saveLevelFile($newLevelID, $levelString);
                return $newLevelID;
            }

        } catch (Exception $e) {
            error_log("Level upload error: " . $e->getMessage());
            return -1;
        }
    }

    private function saveLevelFile(int $levelID, string $levelString): void {
        $levelDir = __DIR__ . "/../database/data/levels";
        if (!is_dir($levelDir)) {
            mkdir($levelDir, 0755, true);
        }
        
        file_put_contents("$levelDir/$levelID", $levelString);
    }
}