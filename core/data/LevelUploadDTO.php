<?php
    require_once __DIR__ . "/../Main.php";
    require_once __DIR__ . "/../lib/ExploitPatch.php";

    class LevelUploadDTO {
        public int $accountID = 0;
        public int $levelID = 0;
        public string $userName = '';
        public string $hostname = '';
        public int $userID = 0;
        public string $levelName = '';
        public int $audioTrack = 0;
        public int $levelLength = 0;
        public int $secret = 0;
        public string $levelString = '';
        public string $gjp = '';
        public int $levelVersion = 1;
        public int $ts = 0;
        public string $songs = '';
        public string $sfxs = '';
        public int $auto = 0;
        public int $original = 0;
        public int $twoPlayer = 0;
        public int $songID = 0;
        public int $object = 0;
        public int $coins = 0;
        public int $requestedStars = 0;
        public string $extraString = '29_29_29_40_29_29_29_29_29_29_29_29_29_29_29_29';
        public string $levelInfo = '';
        public int $unlisted = 0;
        public int $unlisted2 = 0;
        public int $ldm = 0;
        public int $wt = 0;
        public int $wt2 = 0;
        public string $settingsString = '';
        public string $levelDescription = '';
        public int $password = 0;
        public int $gameVersion = 22;
        public int $binaryVersion = 42;

        public static function request(array $post, array $server, Main $main): self {
            $dto = new self();
        
            $dto->userName = ExploitPatch::charclean($post['userName'] ?? '');
            $dto->levelName = ExploitPatch::charclean($post['levelName'] ?? '');
            $dto->levelID = (int) ExploitPatch::remove($post['levelID'] ?? 0);
            
            $dto->gameVersion = (int) ($post['gameVersion'] ?? 22);
            $dto->binaryVersion = (int) ($post['binaryVersion'] ?? 42);
            
            $dto->audioTrack = (int) ExploitPatch::remove($post['audioTrack'] ?? 0);
            $dto->levelLength = (int) ExploitPatch::remove($post['levelLength'] ?? 0);
            $dto->secret = (int) ExploitPatch::remove($post['secret'] ?? 0);
            $dto->levelString = ExploitPatch::remove($post['levelString'] ?? '');
            $dto->gjp = ExploitPatch::remove($post['gjp2'] ?? $post['gjp'] ?? '');
            $dto->levelVersion = (int) ExploitPatch::remove($post['levelVersion'] ?? 1);
            $dto->ts = !empty($post['ts']) ? (int) ExploitPatch::number($post['ts']) : 0;
            $dto->songs = !empty($post['songIDs']) ? ExploitPatch::numbercolon($post['songIDs']) : '';
            $dto->sfxs = !empty($post['sfxIDs']) ? ExploitPatch::numbercolon($post['sfxIDs']) : '';
            
            $dto->auto = (int) ($post['auto'] ?? 0);
            $dto->original = (int) ($post['original'] ?? 0);
            $dto->twoPlayer = (int) ($post['twoPlayer'] ?? 0);
            $dto->songID = (int) ($post['songID'] ?? 0);
            $dto->object = (int) ($post['objects'] ?? 0);
            $dto->coins = (int) ($post['coins'] ?? 0);
            $dto->requestedStars = (int) ($post['requestedStars'] ?? 0);
            $dto->extraString = $post['extraString'] ?? '29_29_29_40_29_29_29_29_29_29_29_29_29_29_29_29';
            $dto->levelInfo = $post['levelInfo'] ?? '';
            
            $dto->unlisted = (int) ($post['unlisted1'] ?? $post['unlisted'] ?? 0);
            $dto->unlisted2 = (int) ($post['unlisted2'] ?? $dto->unlisted);
            
            $dto->ldm = (int) ($post['ldm'] ?? 0);
            $dto->wt = (int) ($post['wt'] ?? 0);
            $dto->wt2 = (int) ($post['wt2'] ?? 0);
            $dto->settingsString = $post['settingsString'] ?? '';
            
            $rawDesc = ExploitPatch::remove($post['levelDesc'] ?? '');
            $dto->levelDescription = str_replace(['+', '/'], ['-', '_'], $rawDesc);
            
            $dto->password = (int) ($post['password'] ?? 0);
            
            $dto->hostname = $main->get_ip();
            $dto->accountID = $main->get_post_id();
            $dto->userID = $main->get_user_id($dto->accountID, $dto->userName);
            
            return $dto;
        }
    }
?>