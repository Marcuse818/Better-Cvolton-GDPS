<?php
require_once __DIR__."/Level.php";
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/generateHash.php";

interface SearchInterface {
    public function search(
        int $accountId, 
        int $page, 
        int $type,
        int $gameVersion = 0,
        int $binaryVersion = 0,
        int $difficulty = 0,
        int $demonFilter = 0,
        int $starFeatured = 0,
        int $original = 0,
        int $coins = 0,
        int $starEpic = 0,
        int $uncompleted = 0,
        int $onlyCompleted = 0,
        string $completedLevels = "",
        int $song = 0,
        int $customSong = 0,
        int $twoPlayer = 0,
        int $star = 0,
        int $noStar = 0,
        int $gauntlet = 0,
        string $len = "",
        int $legendary = 0,
        int $mythic = 0,
        string $followed = "",
        string $string = ""
    ): string;
}

class LevelSearch implements SearchInterface {
    private Database $db;
    private Main $main;
    
    private array $conditions = [];
    private array $params = [];
    private string $joins = "";
    private string $orderBy = "uploadDate";
    private bool $isGauntlet = false;
    private bool $isSearchId = false;

    public function __construct() {
        $this->db = new Database();
        $this->main = new Main();
    }

    public function search(
        int $accountId, 
        int $page, 
        int $type,
        int $gameVersion = 0,
        int $binaryVersion = 0,
        int $difficulty = 0,
        int $demonFilter = 0,
        int $starFeatured = 0,
        int $original = 0,
        int $coins = 0,
        int $starEpic = 0,
        int $uncompleted = 0,
        int $onlyCompleted = 0,
        string $completedLevels = "",
        int $song = 0,
        int $customSong = 0,
        int $twoPlayer = 0,
        int $star = 0,
        int $noStar = 0,
        int $gauntlet = 0,
        string $len = "",
        int $legendary = 0,
        int $mythic = 0,
        string $followed = "",
        string $string = ""
    ): string {
        try {
            $this->resetState();
            
            $gameVersion = $this->validateGameVersion($gameVersion, $binaryVersion);
            $page = max(0, $page);
            $offset = $page * 10;

            $this->conditions[] = "levels.gameVersion <= ?";
            $this->params[] = $gameVersion;

            $this->applyFilters(
                $original, $coins, $uncompleted, $onlyCompleted, $completedLevels,
                $song, $customSong, $twoPlayer, $star, $noStar, $len,
                $starFeatured, $starEpic, $mythic, $legendary
            );

            if (!empty($gauntlet)) {
                $this->handleGauntlet($gauntlet);
                $type = -1;
            }

            $this->handleDifficulty($difficulty, $demonFilter);
            $this->handleSearchType($type, $accountId, $string, $followed);
            $this->handleUnlistedLevels($string);
            
            $queryData = $this->buildMainQuery($offset);
            
            if (empty($queryData['levels'])) {
                return $this->buildEmptyResponse();
            }

            $result = $this->processLevels($queryData['levels'], $accountId, $gauntlet, $gameVersion);
            
            return $this->formatResponse(
                $result['levelString'], 
                $result['userString'], 
                $result['songString'], 
                $queryData['totalCount'], 
                $page, 
                $result['levelsMultiString'], 
                $gameVersion
            );

        } catch (Exception $e) {
            error_log("LevelSearch error: " . $e->getMessage());
            return "-1";
        }
    }

    private function resetState(): void {
        $this->conditions = [];
        $this->params = [];
        $this->joins = "";
        $this->orderBy = "uploadDate";
        $this->isGauntlet = false;
        $this->isSearchId = false;
    }

    private function validateGameVersion(int $gameVersion, int $binaryVersion): int {
        if (empty($gameVersion)) {
            return 30;
        }
        
        if ($gameVersion == 20 && $binaryVersion > 27) {
            return 21;
        }
        
        return $gameVersion;
    }

    private function applyFilters(
        int $original, int $coins, int $uncompleted, int $onlyCompleted, string $completedLevels,
        int $song, int $customSong, int $twoPlayer, int $star, int $noStar, string $len,
        int $starFeatured, int $starEpic, int $mythic, int $legendary
    ): void {
        if (!empty($original)) {
            $this->conditions[] = "original = 0";
        }
        
        if (!empty($coins)) {
            $this->conditions[] = "starCoins = 1 AND levels.coins != 0";
        }
        
        if (!empty($uncompleted) && !empty($completedLevels)) {
            $this->conditions[] = "levelID NOT IN ($completedLevels)";
        }
        
        if (!empty($onlyCompleted) && !empty($completedLevels)) {
            $this->conditions[] = "levelID IN ($completedLevels)";
        }

        if (!empty($song)) {
            if (empty($customSong)) {
                $this->conditions[] = "audioTrack = ? AND songID = 0";
                $this->params[] = $song - 1;
            } else {
                $this->conditions[] = "songID = ?";
                $this->params[] = $song;
            }
        }

        if (!empty($twoPlayer)) {
            $this->conditions[] = "twoPlayer = 1";
        }
        
        if (!empty($star)) {
            $this->conditions[] = "starStars != 0";
        }
        
        if (!empty($noStar)) {
            $this->conditions[] = "starStars = 0";
        }

        if (!empty($len) && $len != "-") {
            $this->conditions[] = "levelLength IN ($len)";
        }

        $epicFilters = [];
        if (!empty($starFeatured)) $epicFilters[] = "starFeatured = 1";
        if (!empty($starEpic)) $epicFilters[] = "starEpic = 1";
        if (!empty($mythic)) $epicFilters[] = "starEpic = 2";
        if (!empty($legendary)) $epicFilters[] = "starEpic = 3";
        
        if (!empty($epicFilters)) {
            $this->conditions[] = "(" . implode(" OR ", $epicFilters) . ")";
        }
    }

    private function handleGauntlet(int $gauntlet): void {
        $this->isGauntlet = true;
        $this->orderBy = "starStars";
        
        $gauntletData = $this->db->fetchOne(
            "SELECT level1, level2, level3, level4, level5 FROM gauntlets WHERE ID = ?",
            [$gauntlet]
        );
        
        if ($gauntletData) {
            $levelIds = implode(",", array_filter([
                $gauntletData["level1"], $gauntletData["level2"], 
                $gauntletData["level3"], $gauntletData["level4"], 
                $gauntletData["level5"]
            ]));
            
            if (!empty($levelIds)) {
                $this->conditions[] = "levelID IN ($levelIds)";
                $this->main->addGauntletLevel($gauntlet);
            }
        }
    }

    private function handleDifficulty(int $difficulty, int $demonFilter): void {
        if ($difficulty === 0 || $difficulty === "-") {
            return;
        }

        switch ($difficulty) {
            case -1:
                $this->conditions[] = "starDifficulty = '0'";
                break;

            case -3:
                $this->conditions[] = "starAuto = '1'";
                break;

            case -2:
                $this->conditions[] = "starDemon = 1";
                $this->applyDemonFilter($demonFilter);
                break;

            default:
                $difficultyStr = str_replace(",", "0,", (string)$difficulty) . "0";
                $this->conditions[] = "starDifficulty IN ($difficultyStr) AND starAuto = '0' AND starDemon = '0'";
                break;
        }
    }

    private function applyDemonFilter(int $demonFilter): void {
        $demonDiffMap = [
            1 => '3',
            2 => '4',
            3 => '0',
            4 => '5',
            5 => '6'
        ];

        if (isset($demonDiffMap[$demonFilter])) {
            $this->conditions[] = "starDemonDiff = '{$demonDiffMap[$demonFilter]}'";
        }
    }

    private function handleSearchType(int $type, int $accountId, string $string, string $followed): void {
        switch ($type) {
            case 0:
            case 15:
                $this->orderBy = "likes";
                if (!empty($string)) {
                    if (is_numeric($string)) {
                        $this->conditions[] = "levelID = ?";
                        $this->params[] = (int)$string;
                        $this->isSearchId = true;
                    } else {
                        $this->conditions[] = "levelName LIKE ?";
                        $this->params[] = "%$string%";
                    }
                }
                break;

            case 1:
                $this->orderBy = "downloads";
                break;

            case 2:
                $this->orderBy = 'likes';
                break;

            case 3:
                $recentDate = time() - (7 * 24 * 60 * 60);
                $this->conditions[] = "uploadDate > ?";
                $this->params[] = $recentDate;
                $this->orderBy = "likes";
                break;

            case 5:
                $targetUserId = empty($string) ? $this->main->getUserId($accountId) : (int)$string;
                $this->conditions[] = "levels.userID = ?";
                $this->params[] = $targetUserId;
                break;

            case 6:
            case 17:
                $this->conditions[] = "starFeatured != 0";
                $this->orderBy = "rateDate DESC, uploadDate";
                break;

            case 16:
                $this->conditions[] = "starEpic != 0";
                $this->orderBy = "rateDate DESC, uploadDate";
                break;

            case 7:
                $this->conditions[] = "objects > 9999";
                break;

            case 10:
            case 19:
                $this->orderBy = "";
                if (!empty($string)) {
                    $this->conditions[] = "levelID IN ($string)";
                }
                break;

            case 11:
                $this->conditions[] = "starStars != 0";
                $this->orderBy = "rateDate DESC, uploadDate";
                break;

            case 12:
                if (!empty($followed)) {
                    $this->conditions[] = "users.extID IN ($followed)";
                }
                break;

            case 13:
                $friends = $this->main->getFriends($accountId);
                if (!empty($friends)) {
                    $friendsList = implode(",", $friends);
                    $this->conditions[] = "users.extID IN ($friendsList)";
                }
                break;

            case 21:
                $this->joins = "INNER JOIN dailyfeatures ON levels.levelID = dailyfeatures.levelID";
                $this->conditions[] = "dailyfeatures.type = 0";
                $this->orderBy = "dailyfeatures.feaID";
                break;

            case 22:
                $this->joins = "INNER JOIN dailyfeatures ON levels.levelID = dailyfeatures.levelID";
                $this->conditions[] = "dailyfeatures.type = 1";
                $this->orderBy = "dailyfeatures.feaID";
                break;

            case 23:
                $this->joins = "INNER JOIN dailyfeatures ON levels.levelID = dailyfeatures.levelID";
                $this->conditions[] = "dailyfeatures.type = 2";
                $this->orderBy = "dailyfeatures.feaID";
                break;

            case 25:
                $listLevels = $this->main->getListLevels($string);
                if (!empty($listLevels)) {
                    $this->conditions = ["levelID IN ($listLevels)"];
                }
                break;

            case 27:
                $this->joins = "INNER JOIN sendLevel ON levels.levelID = sendLevel.levelID";
                $this->conditions[] = "sendLevel.isRated = 0";
                $this->orderBy = 'sendLevel.timestamp';
                break;
        }
    }

    private function handleUnlistedLevels(string $string): void {
        if (!is_numeric($string) || !$this->isSearchId) {
            return;
        }

        $unlistedData = $this->db->fetchOne(
            "SELECT unlisted FROM levels WHERE levelID = ?",
            [(int)$string]
        );

        if ($unlistedData && isset($unlistedData["unlisted"])) {
            $this->conditions[] = "unlisted = ?";
            $this->params[] = $unlistedData["unlisted"];
        }
    }

    private function buildMainQuery(int $offset): array {
        $selectFields = "levels.*, songs.ID as song_id, songs.name as song_name, 
                        songs.authorID, songs.authorName, songs.size, songs.isDisabled, 
                        songs.download, users.userName, users.extID";
        
        $fromClause = "FROM levels 
                      LEFT JOIN songs ON levels.songID = songs.ID 
                      LEFT JOIN users ON levels.userID = users.userID 
                      {$this->joins}";

        $whereClause = "";
        if (!empty($this->conditions)) {
            $whereClause = "WHERE " . implode(" AND ", $this->conditions);
        }

        $orderClause = "";
        if (!empty($this->orderBy)) {
            $orderDirection = $this->isGauntlet ? "ASC" : "DESC";
            $orderClause = "ORDER BY {$this->orderBy} {$orderDirection}";
        }

        $levelsQuery = "SELECT {$selectFields} {$fromClause} {$whereClause} {$orderClause} LIMIT 10 OFFSET {$offset}";
        $levels = $this->db->fetchAll($levelsQuery, $this->params);

        $countQuery = "SELECT COUNT(*) {$fromClause} {$whereClause}";
        $totalCount = $this->db->fetchColumn($countQuery, $this->params);

        return [
            'levels' => $levels,
            'totalCount' => $totalCount
        ];
    }

    private function processLevels(array $levels, int $accountId, int $gauntlet, int $gameVersion): array {
        $levelsMultiString = [];
        $levelString = "";
        $songString = "";
        $userString = "";

        foreach ($levels as $level) {
            if (!$this->shouldIncludeLevel($level, $accountId)) {
                continue;
            }

            $levelsMultiString[] = [
                "levelID" => $level["levelID"], 
                "stars" => $level["starStars"], 
                'coins' => $level["starCoins"]
            ];

            $levelString .= $this->buildLevelString($level, $gauntlet);
            
            if ($level["songID"] != 0) {
                $song = $this->main->getSongString($level);
                if ($song) {
                    $songString .= $song . "~:~";
                }
            }
            
            $userString .= $this->main->getUserString($level) . "|";
        }

        return [
            'levelsMultiString' => $levelsMultiString,
            'levelString' => $levelString,
            'songString' => $songString,
            'userString' => $userString
        ];
    }

    private function shouldIncludeLevel(array $level, int $accountId): bool {
        if (empty($level['unlisted']) || $level['unlisted'] != 1) {
            return true;
        }
        
        if (!$this->main->isFriends($accountId, $level["extID"]) && $accountId != $level["extID"]) {
            return false;
        }
        
        return true;
    }

    private function buildLevelString(array $level, int $gauntlet): string {
        $parts = [];
        
        if (!empty($gauntlet)) {
            $parts[] = "44:{$gauntlet}";
        }
        
        $fields = [
            1 => $level['levelID'],
            2 => $level['levelName'],
            5 => $level['levelVersion'],
            6 => $level['userID'],
            8 => 10,
            9 => $level['starDifficulty'],
            10 => $level['downloads'],
            12 => $level['audioTrack'],
            13 => $level['gameVersion'],
            14 => $level['likes'],
            17 => $level['starDemon'],
            43 => $level['starDemonDiff'],
            25 => $level['starAuto'],
            18 => $level['starStars'],
            19 => $level['starFeatured'],
            42 => $level['starEpic'],
            45 => $level['objects'],
            3 => $level['levelDesc'],
            15 => $level['levelLength'],
            30 => $level['original'],
            31 => $level['twoPlayer'],
            37 => $level['coins'],
            38 => $level['starCoins'],
            39 => $level['requestedStars'],
            46 => 1,
            47 => 2,
            40 => $level['isLDM'],
            35 => $level['songID']
        ];

        foreach ($fields as $key => $value) {
            $parts[] = "$key:$value";
        }

        return implode(":", $parts) . "|";
    }

    private function formatResponse(
        string $levelString, 
        string $userString, 
        string $songString, 
        int $totalCount, 
        int $page, 
        array $levelsMultiString, 
        int $gameVersion
    ): string {
        $levelString = rtrim($levelString, "|");
        $userString = rtrim($userString, "|");
        $songString = rtrim($songString, "~:~");
        
        $songPart = ($gameVersion > 18) ? "#" . $songString : "";
        $hash = GenerateHash::genMulti($levelsMultiString);

        return "{$levelString}#{$userString}{$songPart}#{$totalCount}:{$page}:10#{$hash}";
    }

    private function buildEmptyResponse(): string {
        return "#0:0:10#";
    }
}