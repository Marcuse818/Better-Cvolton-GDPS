<?php
    require_once '../data/LevelDTO.php';
    require_once '../data/LevelDownloadDTO.php';

    class LevelRepository {
        private PDO $db;

        public function __construct(PDO $connection) {
            $this->db = $connection;
        }

        public function get_exists(string $levelName, int $userID): bool {
            $stmt = $this->db->prepare('
                SELECT 1 FROM levels 
                WHERE levelName = :name AND userID = :uid 
                LIMIT 1
            ');
            $stmt->execute([':name' => $levelName, ':uid' => $userID]);
            return $stmt->fetch() !== false;
        }

        public function get_by_name(string $levelName, int $userID): ?int {
            $stmt = $this->db->prepare(
                'SELECT levelID FROM levels WHERE levelName = :name AND userID = :uid LIMIT 1'
            );
            $stmt->execute([':name' => $levelName, ':uid' => $userID]);
            $result = $stmt->fetchColumn();
            return $result !== false ? (int) $result : null;
        }

        public function get_donwload_level(int $levelID): ?LevelDownloadDTO {
            $stmt = $this->db->prepare('SELECT 
                    l.*, 
                    u.userName, 
                    u.extID,
                    s.ID, s.name, s.authorID, s.authorName, s.size, s.isDisabled, s.download
                FROM levels l
                LEFT JOIN users u ON l.userID = u.userID
                LEFT JOIN songs s ON l.songID = s.ID
                WHERE l.levelID = :id AND l.isDeleted = 0
                LIMIT 1
            ');
            $stmt->execute([':id' => $levelID]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? LevelDownloadDTO::download($result) : null;
        }

        public function get_daily(int $type): ?array {
            $stmt = $this->db->prepare('
                SELECT feaID, levelID 
                FROM dailyfeatures 
                WHERE timestamp < :time AND type = :type 
                ORDER BY timestamp DESC 
                LIMIT 1
            ');
            $stmt->execute([':time' => time(), ':type' => $type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        }

        public function get_rate_count(int $levelID): int {
            $stmt = $this->db->prepare('
                SELECT COUNT(*) FROM action_rate 
                WHERE levelID = :id
            ');
            $stmt->execute([':id' => $levelID]);
            $result = $stmt->fetchColumn();
            return (int) $result;
        }

        public function get_rate_average(int $levelID): float {
            $stmt = $this->db->prepare('
                SELECT AVG(difficulty) as avg 
                FROM action_rate 
                HERE levelID = :id
            ');
            $stmt->execute([':id' => $levelID]);
            $result = $stmt->fetchColumn();
            return (float) $result ?? 0;
        }

        public function get_is_rated(int $levelID): bool {
            $stmt = $this->db->prepare('
                SELECT isRated FROM action_rate 
                WHERE levelID = :id 
                LIMIT 1
            ');
            $stmt->execute([':id' => $levelID]);
            $result = $stmt->fetchColumn();
            return $result !== false && $result !== 0;
        }

        public function get_recent_upload(int $userID, string $ip): int {
            $stmt = $this->db->prepare('
                SELECT COUNT(*) FROM levels 
                WHERE uploadDate > :time AND (userID = :uid OR hostname = :ip)
            ');
            $stmt->execute([':time' => time() - 30, ':uid' => $userID, ':ip' => $ip]);
            $result = $stmt->fetchColumn();
            return (int) $result;
        }

        public function increment_downloads(int $levelID): bool {
            $stmt = $this->db->prepare("
                UPDATE levels SET downloads = downloads + 1 
                WHERE levelID = :id
            ");
            return $stmt->execute([':id' => $levelID]);
        }
        
        public function download_action(int $levelID, string $ip): bool {
            $stmt = $this->db->prepare('
                INSERT INTO actions_downloads (levelID, ip) 
                VALUES (:id, INET6_ATON(:ip))
            ');
            return $stmt->execute([':id' => $levelID, ':ip' => $ip]);
        }

        public function get_donwload_action(int $levelID, string $ip): int {
            $stmt = $this->db->prepare('
                SELECT COUNT(*) FROM actions_downloads 
                WHERE levelID = :id AND ip = INET6_ATON(:ip)
            ');
            $stmt->execute([':id' => $levelID, ':ip' => $ip]);
            $result = $stmt->fetchColumn();
            return (int) $result;
        }

        public function create(LevelUploadDTO $data): int {
            $stmt = $this->db->prepare('
                INSERT INTO levels (
                    levelName, gameVersion, binaryVersion, userName, levelDesc,
                    levelVersion, levelLength, audioTrack, auto, password,
                    original, twoPlayer, songID, objects, coins,
                    requestedStars, extraString, levelString, levelInfo, secret,
                    uploadDate, userID, extID, updateDate, unlisted,
                    hostname, isLDM, wt, wt2, unlisted2,
                    settingsString, songIDs, sfxIDs, ts
                ) VALUES (
                    :name, :gv, :bv, :uname, :desc,
                    :ver, :len, :track, :auto, :pass,
                    :orig, :2p, :song, :obj, :coins,
                    :req, :extra, :str, :info, :secret,
                    :up, :uid, :ext, :upd, :unlisted,
                    :host, :ldm, :wt, :wt2, :unl2,
                    :set, :songs, :sfxs, :ts
                )
            ');

            $now = time();
            $stmt->execute([
                ':name' => $data->levelName,
                ':gv' => $data->gameVersion,
                ':bv' => $data->binaryVersion,
                ':uname' => $data->userName,
                ':desc' => $data->levelDescription,
                ':ver' => $data->levelVersion,
                ':len' => $data->levelLength,
                ':track' => $data->audioTrack,
                ':auto' => $data->auto,
                ':pass' => $data->password,
                ':orig' => $data->original,
                ':2p' => $data->twoPlayer,
                ':song' => $data->songID,
                ':obj' => $data->object,
                ':coins' => $data->coins,
                ':req' => $data->requestedStars,
                ':extra' => $data->extraString,
                ':str' => $data->levelString,
                ':info' => $data->levelInfo,
                ':secret' => $data->secret,
                ':up' => $now,
                ':uid' => $data->userID,
                ':ext' => $data->accountID,
                ':upd' => $now,
                ':unlisted' => $data->unlisted,
                ':host' => $data->hostname,
                ':ldm' => $data->ldm,
                ':wt' => $data->wt,
                ':wt2' => $data->wt2,
                ':unl2' => $data->unlisted2,
                ':set' => $data->settingsString,
                ':songs' => $data->songs,
                ':sfxs' => $data->sfxs,
                ':ts' => $data->ts
            ]);
            
            return (int) $this->db->lastInsertId();
        }
        
        public function update(LevelUploadDTO $data, int $extID): bool {
            $stmt = $this->db->prepare("
                UPDATE levels SET
                    levelName = :name,
                    gameVersion = :gv,
                    binaryVersion = :bv,
                    userName = :uname,
                    levelDesc = :desc,
                    levelVersion = :ver,
                    levelLength = :len,
                    audioTrack = :track,
                    auto = :auto,
                    password = :pass,
                    original = :orig,
                    twoPlayer = :2p,
                    songID = :song,
                    objects = :obj,
                    coins = :coins,
                    requestedStars = :req,
                    extraString = :extra,
                    levelString = :str,
                    levelInfo = :info,
                    secret = :secret,
                    updateDate = :upd,
                    unlisted = :unlisted,
                    hostname = :host,
                    isLDM = :ldm,
                    wt = :wt,
                    wt2 = :wt2,
                    unlisted2 = :unl2,
                    settingsString = :set,
                    songIDs = :songs,
                    sfxIDs = :sfxs,
                    ts = :ts
                WHERE levelID = :id AND extID = :ext
            ");
            
            return $stmt->execute([
                ':id' => $extID,
                ':ext' => $data->accountID,
                ':name' => $data->levelName,
                ':gv' => $data->gameVersion,
                ':bv' => $data->binaryVersion,
                ':uname' => $data->userName,
                ':desc' => $data->levelDescription,
                ':ver' => $data->levelVersion,
                ':len' => $data->levelLength,
                ':track' => $data->audioTrack,
                ':auto' => $data->auto,
                ':pass' => $data->password,
                ':orig' => $data->original,
                ':2p' => $data->twoPlayer,
                ':song' => $data->songID,
                ':obj' => $data->object,
                ':coins' => $data->coins,
                ':req' => $data->requestedStars,
                ':extra' => $data->extraString,
                ':str' => $data->levelString,
                ':info' => $data->levelInfo,
                ':secret' => $data->secret,
                ':upd' => time(),
                ':unlisted' => $data->unlisted,
                ':host' => $data->hostname,
                ':ldm' => $data->ldm,
                ':wt' => $data->wt,
                ':wt2' => $data->wt2,
                ':unl2' => $data->unlisted2,
                ':set' => $data->settingsString,
                ':songs' => $data->songs,
                ':sfxs' => $data->sfxs,
                ':ts' => $data->ts
            ]);
        }

        public function delete(int $levelID, int $userID): bool {
            $stmt = $this->db->prepare("
                UPDATE levels SET isDeleted = 1 
                WHERE levelID = :id AND userID = :uid AND starStars = 0
            ");
            return $stmt->execute([':id' => $levelID, ':uid' => $userID]);
        }

        public function action_delete(int $levelID, int $userID): bool {
            $stmt = $this->db->prepare("
                INSERT INTO actions (type, value, timestamp, value2) 
                VALUES (8, :id, :time, :uid)
            ");
            return $stmt->execute([
                ':id' => $levelID,
                ':time' => time(),
                ':uid' => $userID
            ]);
        }

        public function action_rate(int $accountID, int $levelID, int $difficulty): bool {
            $stmt = $this->db->prepare("
                INSERT INTO action_rate (accountID, levelID, difficulty) 
                VALUES (:acc, :lid, :diff)
            ");
            return $stmt->execute([
                ':acc' => $accountID,
                ':lid' => $levelID,
                ':diff' => $difficulty
            ]);
        }

        public function demon_diff(int $levelID, int $demonDiff) {
            $stmt = $this->db->prepare("
                UPDATE levels SET starDemonDiff = :diff 
                WHERE levelID = :id
            ");
            return $stmt->execute([':id' => $levelID, ':diff' => $demonDiff]);
        }

        public function description(int $levelID, int $accountID, string $description): bool {
            $stmt = $this->db->prepare("
                UPDATE levels SET levelDesc = :desc 
                WHERE levelID = :id AND extID = :acc
            ");
            return $stmt->execute([
                ':id' => $levelID,
                ':acc' => $accountID,
                ':desc' => $description
            ]);
        }

        public function report(int $levelID, string $hostname): ?int {
            $check = $this->db->prepare("
                SELECT 1 FROM reports 
                WHERE levelID = :id AND hostname = :host 
                LIMIT 1
            ");
            $check->execute([':id' => $levelID, ':host' => $hostname]);
            if ($check->fetch()) return null;
            
            $stmt = $this->db->prepare("
                INSERT INTO reports (levelID, hostname) 
                VALUES (:id, :host)
            ");
            $stmt->execute([':id' => $levelID, ':host' => $hostname]);
            
            return (int) $this->db->lastInsertId();
        }
    }
?>