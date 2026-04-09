<?php
    class LevelUploadDTO {
        public int $accountID;
        public int $levelID;
        public string $userName;
        public string $hostname;
        public int $userID;
        public string $levelName;
        public int $audioTrack;
        public int $levelLength;
        public int $secret;
        public string $levelString;
        public string $gjp;
        public int $levelVersion;
        public int $ts;
        public string $songs;
        public string $sfxs;
        public int $auto;
        public int $original;
        public int $twoPlayer;
        public int $songID;
        public int $object;
        public int $coins;
        public int $requestedStars;
        public string $extraString;
        public string $levelInfo;
        public int $unlisted;
        public int $unlisted2;
        public int $ldm;
        public int $wt;
        public int $wt2;
        public string $settingsString;
        public string $levelDescription;
        public int $password;
        public int $gameVersion;
        public int $binaryVersion;

        public static function request(array $post, array $server): self {
            $dto = new self();

            $dto->accountID = (int) ($post['accountID'] ?? 0);
            $dto->levelID = (int) ($post['levelID'] ?? 0);
            $dto->userName = $post['userName'] ?? '';
            $dto->hostname = $server['REMOTE_ADDR'] ?? '127.0.0.1';
            $dto->userID = (int) ($post['userID'] ?? 0);
            $dto->levelName = $post['levelName'] ?? '';
            $dto->audioTrack = (int) ($post['audioTrack'] ?? 0);
            $dto->levelLength = (int) ($post['levelLength'] ?? 0);
            $dto->secret = (int) ($post['secret'] ?? 0);
            $dto->levelString = $post['levelString'] ?? '';
            $dto->gjp = $post['gjp'] ?? '';
            $dto->levelVersion = (int) ($post['levelVersion'] ?? 1);
            $dto->ts = (int) ($post['ts'] ?? 0);
            $dto->songs = $post['songs'] ?? '';
            $dto->sfxs = $post['sfxs'] ?? '';
            $dto->auto = (int) ($post['auto'] ?? 0);
            $dto->original = (int) ($post['original'] ?? 0);
            $dto->twoPlayer = (int) ($post['twoPlayer'] ?? 0);
            $dto->songID = (int) ($post['songID'] ?? 0);
            $dto->object = (int) ($post['object'] ?? 0);
            $dto->coins = (int) ($post['coins'] ?? 0);
            $dto->requestedStars = (int) ($post['requestedStars'] ?? 0);
            $dto->extraString = $post['extraString'] ?? '';
            $dto->levelInfo = $post['levelInfo'] ?? '';
            $dto->unlisted = (int) ($post['unlisted'] ?? 0);
            $dto->unlisted2 = (int) ($post['unlisted2'] ?? 0);
            $dto->ldm = (int) ($post['ldm'] ?? 0);
            $dto->wt = (int) ($post['wt'] ?? 0);
            $dto->wt2 = (int) ($post['wt2'] ?? 0);
            $dto->settingsString = $post['settingsString'] ?? '';
            $dto->levelDescription = $post['levelDescription'] ?? '';
            $dto->password = (int) ($post['password'] ?? 0);
            $dto->gameVersion = (int) ($post['gameVersion'] ?? 22);
            $dto->binaryVersion = (int) ($post['binaryVersion'] ?? 42);
        
            return $dto;
        }
    }
?>