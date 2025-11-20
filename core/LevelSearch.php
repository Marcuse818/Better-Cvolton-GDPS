<?php
require_once __DIR__."/Level.php";
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/generateHash.php";

interface SearchInterface {
    public function search(
        int $accountID, 
        int $page, 
        int $type,
        $gameVersion = 0,
        $binaryVersion = 0,
        $difficulty = 0,
        $demonFilter = 0,
        $starFeatured = 0,
        $original = 0,
        $coins = 0,
        $starEpic = 0,
        $uncompleted = 0,
        $onlyCompleted = 0,
        $completedLevels = 0,
        $song = 0,
        $customSong = 0,
        $twoPlayer = 0,
        $star = 0,
        $noStar = 0,
        $gauntlet = 0,
        $len = 0,
        $legendary = 0,
        $mythic = 0,
        $followed = 0,
        $string = ""
    ): string;
}

class LevelSearch implements SearchInterface {
    protected $database;
    protected $main;
    
    private $conditions = [];
    private $epicConditions = [];
    private $joins = "";
    private $orderBy = "uploadDate";
    private $isGauntlet = false;
    private $isSearchID = false;

    public function __construct() {
        $this->database = new Database();
        $this->main = new Main();
    }

    public function search(
        int $accountID, 
        int $page, 
        int $type,
        $gameVersion = 0,
        $binaryVersion = 0,
        $difficulty = 0,
        $demonFilter = 0,
        $starFeatured = 0,
        $original = 0,
        $coins = 0,
        $starEpic = 0,
        $uncompleted = 0,
        $onlyCompleted = 0,
        $completedLevels = 0,
        $song = 0,
        $customSong = 0,
        $twoPlayer = 0,
        $star = 0,
        $noStar = 0,
        $gauntlet = 0,
        $len = 0,
        $legendary = 0,
        $mythic = 0,
        $followed = 0,
        $string = ""
    ): string {
        try {
            $levelsMultiString = [];
            $levelString = "";
            $songString = "";
            $userString = "";
            $params = [];

            $gameVersion = $this->validateGameVersion($gameVersion, $binaryVersion);
            $type = $type ?: 0;
            $page = is_numeric($page) ? (int)$page : 0;
            $offset = $page * 10;

            $this->conditions[] = "levels.gameVersion <= :gameVersion";
            $params[':gameVersion'] = $gameVersion;

            $this->applyFilters(
                $original, $coins, $uncompleted, $onlyCompleted, $completedLevels,
                $song, $customSong, $twoPlayer, $star, $noStar, $len,
                $starFeatured, $starEpic, $mythic, $legendary
            );

            if (!empty($gauntlet)) {
                $this->handleGauntlet($gauntlet, $params);
                $type = -1;
            }

            $this->handleDifficulty($difficulty, $demonFilter, $params);
            $this->handleSearchType($type, $accountID, $string, $followed, $params);
            $this->handleUnlistedLevels($string);
            $queryData = $this->buildMainQuery($offset, $params);
            
            if (empty($queryData['levels'])) {
                return $this->buildEmptyResponse();
            }

            foreach ($queryData['levels'] as $level) {
                if (!$this->shouldIncludeLevel($level, $accountID)) {
                    continue;
                }

                $levelsMultiString[] = [
                    "levelID" => $level["levelID"], 
                    "stars" => $level["starStars"], 
                    'coins' => $level["starCoins"]
                ];

                $levelString .= $this->buildLevelString($level, $gauntlet);
                
                if ($level["songID"] != 0) {
                    $song = $this->main->get_song_string($level);
                    if ($song) {
                        $songString .= $song . "~:~";
                    }
                }
                
                $userString .= $this->main->get_user_string($level) . "|";
            }

            return $this->formatResponse(
                $levelString, 
                $userString, 
                $songString, 
                $queryData['totalCount'], 
                $page, 
                $levelsMultiString, 
                $gameVersion
            );

        } catch (Exception $e) {
            error_log("LevelSearch error: " . $e->getMessage());
            return "-1";
        }
    }

    private function validateGameVersion($gameVersion, $binaryVersion): int {
        if (empty($gameVersion)) {
            return 30;
        }
        
        if (!is_numeric($gameVersion)) {
            throw new InvalidArgumentException("Invalid game version");
        }
        
        if ($gameVersion == 20 && $binaryVersion > 27) {
            $gameVersion++;
        }
        
        return (int)$gameVersion;
    }

    private function applyFilters(
        $original, $coins, $uncompleted, $onlyCompleted, $completedLevels,
        $song, $customSong, $twoPlayer, $star, $noStar, $len,
        $starFeatured, $starEpic, $mythic, $legendary
    ): void {
        if (!empty($original)) {
            $this->conditions[] = "original = 0";
        }
        
        if (!empty($coins)) {
            $this->conditions[] = "starCoins = 1 AND levels.coins != 0";
        }
        
        if (!empty($uncompleted)) {
            $this->conditions[] = "levelID NOT IN ($completedLevels)";
        }
        
        if (!empty($onlyCompleted)) {
            $this->conditions[] = "levelID IN ($completedLevels)";
        }

        if (!empty($song)) {
            if (empty($customSong)) {
                $song = $song - 1;
                $this->conditions[] = "audioTrack = :audioTrack AND songID = 0";
            } else {
                $this->conditions[] = "songID = :songID";
            }
        }

        if (!empty($twoPlayer) && $twoPlayer == 1) {
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

    private function handleGauntlet($gauntlet, array &$params): void {
        $this->isGauntlet = true;
        $this->orderBy = "starStars";
        
        $gauntletData = $this->database->fetch_one(
            "SELECT level1, level2, level3, level4, level5 FROM gauntlets WHERE ID = :gauntlet",
            [":gauntlet" => $gauntlet]
        );
        
        if ($gauntletData) {
            $levelIDs = implode(",", [
                $gauntletData["level1"], $gauntletData["level2"], 
                $gauntletData["level3"], $gauntletData["level4"], 
                $gauntletData["level5"]
            ]);
            
            $this->conditions[] = "levelID IN ($levelIDs)";
            $this->main->add_gauntlet_level($gauntlet);
        }
    }

    private function handleDifficulty($difficulty, $demonFilter, array &$params): void {
        if ($difficulty === "-") {
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
                if ($difficulty) {
                    $difficulty = str_replace(",", "0,", $difficulty) . "0";
                    $this->conditions[] = "starDifficulty IN ($difficulty) AND starAuto = '0' AND starDemon = '0'";
                }
                break;
        }
    }

    private function applyDemonFilter($demonFilter): void {
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

    private function handleSearchType($type, $accountID, $string, $followed, array &$params): void {
        switch ($type) {
            case 0:
            case 15:
                $this->orderBy = "likes";
                if (!empty($string)) {
                    if (is_numeric($string)) {
                        $this->conditions[] = "levelID = :searchID";
                        $params[':searchID'] = $string;
                        $this->isSearchID = true;
                    } else {
                        $this->conditions[] = "levelName LIKE :searchName";
                        $params[':searchName'] = "%$string%";
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
                $uploadDate = time() - (7 * 24 * 60 * 60);
                $this->conditions[] = "uploadDate > :recentDate";
                $params[':recentDate'] = $uploadDate;
                $this->orderBy = "likes";
                break;

            case 5:
                $targetUserID = empty($string) ? $this->main->get_user_id($accountID) : $string;
                $this->conditions[] = "levels.userID = :userID";
                $params[':userID'] = $targetUserID;
                break;

            case 6:
            case 17:
                $this->conditions[] = $params[':gameVersion'] > 21 
                    ? "(starFeatured != 0 OR starEpic != 0)" 
                    : "starFeatured != 0";
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
                $this->conditions[] = "levelID IN ($string)";
                break;

            case 11:
                $this->conditions[] = "starStars != 0";
                $this->orderBy = "rateDate DESC, uploadDate";
                break;

            case 12:
                $this->conditions[] = "users.extID IN ($followed)";
                break;

            case 13:
                $friends = $this->main->get_friends($accountID);
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
                $listLevels = $this->main->get_list_levels($string);
                $this->conditions = ["levelID IN ($listLevels)"];
                break;

            case 27:
                $this->joins = "INNER JOIN sendLevel ON levels.levelID = sendLevel.levelID";
                $this->conditions[] = "sendLevel.isRated = 0";
                $this->orderBy = 'sendLevel.timestamp';
                break;
        }
    }

    private function handleUnlistedLevels($string): void {
        if (is_numeric($string) && $this->isSearchID) {
            $unlistedData = $this->database->fetch_one(
                "SELECT unlisted FROM levels WHERE levelID = :levelID",
                [":levelID" => (int)$string]
            );

            if ($unlistedData) {
                $unlistedValue = $unlistedData["unlisted"];
                $this->conditions[] = "unlisted = :unlisted";
            }
        }
    }

    private function buildMainQuery($offset, $params): array {
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
        $levels = $this->database->fetch_all($levelsQuery, $params);

        $countQuery = "SELECT COUNT(*) {$fromClause} {$whereClause}";
        $totalCount = $this->database->fetch_column($countQuery, $params);

        return [
            'levels' => $levels,
            'totalCount' => $totalCount
        ];
    }

    private function shouldIncludeLevel($level, $accountID): bool {
        if (isset($level['unlisted']) && $level['unlisted'] == 1) {
            if (!isset($accountID)) {
                $accountID = GJPCheck::getAccountIDOrDie();
            }
            
            if (!$this->main->is_friends($accountID, $level["extID"]) && $accountID != $level["extID"]) {
                return false;
            }
        }
        
        return true;
    }

    private function buildLevelString($level, $gauntlet): string {
        $levelString = "";
        
        if (!empty($gauntlet)) {
            $levelString .= "44:{$gauntlet}:";
        }
        
        $levelString .= "1:{$level['levelID']}:2:{$level['levelName']}:5:{$level['levelVersion']}:6:{$level['userID']}:8:10:9:{$level['starDifficulty']}:10:{$level['downloads']}:12:{$level['audioTrack']}:13:{$level['gameVersion']}:14:{$level['likes']}:17:{$level['starDemon']}:43:{$level['starDemonDiff']}:25:{$level['starAuto']}:18:{$level['starStars']}:19:{$level['starFeatured']}:42:{$level['starEpic']}:45:{$level['objects']}:3:{$level['levelDesc']}:15:{$level['levelLength']}:30:{$level['original']}:31:{$level['twoPlayer']}:37:{$level['coins']}:38:{$level['starCoins']}:39:{$level['requestedStars']}:46:1:47:2:40:{$level['isLDM']}:35:{$level['songID']}|";
        
        return $levelString;
    }

    private function formatResponse($levelString, $userString, $songString, $totalCount, $page, $levelsMultiString, $gameVersion): string {
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