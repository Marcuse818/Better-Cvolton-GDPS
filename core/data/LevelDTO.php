<?php
    class LevelDTO {
        public int $levelID;
        public string $levelName;
        public int $userID;
        public string $userName;
        public ?int $extID = null;
        public int $levelVersion = 1;
        public int $gameVersion = 0;
        public int $binaryVersion = 0;
        public int $downloads = 0;
        public int $likes = 0;
        public int $starDifficulty = 0;
        public int $starDemon = 0;
        public int $starDemonDiff = 0;
        public int $starAuto = 0;
        public int $starStars = 0;
        public int $starFeatured = 0;
        public int $starEpic = 0;
        public int $objects = 0;
        public int $levelLength = 0;
        public int $audioTrack = 0;
        public int $songID = 0;
        public int $original = 0;
        public int $twoPlayer = 0;
        public int $coins = 0;
        public int $starCoins = 0;
        public int $requestedStars = 0;
        public int $isLDM = 0;
        public int $uploadDate = 0;
        public int $updateDate = 0;
        public int $unlisted = 0;

        public static function get_levels(array $row): self {
            $dto = new self();
            $fields = [
                'levelID', 'levelName', 'userID', 'userName', 'extID',
                'levelVersion', 'gameVersion', 'binaryVersion', 'downloads', 'likes',
                'starDifficulty', 'starDemon', 'starDemonDiff', 'starAuto', 'starStars',
                'starFeatured', 'starEpic', 'objects', 'levelLength', 'audioTrack',
                'songID', 'original', 'twoPlayer', 'coins', 'starCoins',
                'requestedStars', 'isLDM', 'uploadDate', 'updateDate', 'unlisted'
            ];

            foreach ($fields as $field) {
                if (isset($row[$field])) {
                    $dto->$field = is_numeric($row[$field]) ? (int) $row[$field] : $row[$field];
                }
            }

            return $dto;
        }

        public function to_string(int $gauntlet = 0): string {
            $result = "";
            if (!empty($gauntlet)) $result .= "44:$gauntlet:";
        
            return "1:{$this->levelID}" .
                ":2:{$this->levelName}" .
                ":5:{$this->levelVersion}" .
                ":6:{$this->userID}" .
                ":8:10" .
                ":9:{$this->starDifficulty}" .
                ":10:{$this->downloads}" .
                ":12:{$this->audioTrack}" .
                ":13:{$this->gameVersion}" .
                ":14:{$this->likes}" .
                ":17:{$this->starDemon}" .
                ":43:{$this->starDemonDiff}" .
                ":25:{$this->starAuto}" .
                ":18:{$this->starStars}" .
                ":19:{$this->starFeatured}" .
                ":42:{$this->starEpic}" .
                ":45:{$this->objects}" .
                ":15:{$this->levelLength}" .
                ":30:{$this->original}" .
                ":31:{$this->twoPlayer}" .
                ":37:{$this->coins}" .
                ":38:{$this->starCoins}" .
                ":39:{$this->requestedStars}" .
                ":46:1:47:2" .
                ":40:{$this->isLDM}" .
                ":35:{$this->songID}" . $result;
        }

        
    }
?>