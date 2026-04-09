<?php
    require_once __DIR__."/Main.php";

    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/Lib.php";
    require_once __DIR__."/lib/XORCipher.php";
    require_once __DIR__."/lib/exploitPatch.php";
    require_once __DIR__."/lib/generateHash.php";
    require_once __DIR__."/rep/LevelRepository.php";
    require_once __DIR__."/data/LevelUploadDTO.php";
    
    class Level {
        private LevelRepository $repo;
        private Lib $Lib;
        private Main $Main;
        
        public int $gameVersion = 0;
        public int $binaryVersion = 0;
        
        public function __construct() {
            $this->Lib = new Lib();
            $this->Main = new Main();
            
            $db = new Database();
            $this->repo = new LevelRepository($db->open_connection());
        }

        public function existLevel(string $levelName, int $userID): int
        {
            return $this->repo->get_exists($levelName, $userID) ? 1 : 0;
        }

        public function delete(int $userID, int $levelID): string { 
            if (!$this->repo->delete($levelID, $userID)) {
                return "-1";
            }

            $this->repo->action_delete($levelID, $userID);

            $path = __DIR__."/../database/data/levels/$levelID";
            $deletedPath = __DIR__."/../database/data/levels/deleted/$levelID";
            
            if (file_exists($path)) {
                rename($path, $deletedPath);
                return "1";
            }

            return "-1";
        }

        public function download(int $accountID, int $levelID, $inc, $extras, $hostname): string {
            if (!is_numeric($levelID)) return "-1";

            $daily = false;
            $feaID = 0;
            
            switch($levelID) {
                case -1:
                    $daily_data = $this->repo->get_daily(0);
                    $levelID = (int) $daily_data['levelID'];
                    
                    if (!$daily_data) return "-1";

                    $feaID = (int) $daily_data['feaID'];
                    $daily = true;
                    break;

                case -2:
                    $daily_data = $this->repo->get_daily(1);
                    $levelID = (int) $daily_data['levelID'];
                    
                    if (!$daily_data) return "-1";

                    $feaID = (int) $daily_data['feaID'] + 100001;
                    $daily = true;
                    break;

                case -3:
                    $daily_data = $this->repo->get_daily(2);
                    $levelID = (int) $daily_data['levelID'];
                    
                    if (!$daily_data) return "-1";

                    $feaID = (int) $daily_data['feaID'] + 200001;
                    $daily = true;
                    break;
            }

            $level = $this->repo->get_donwload_level($levelID);
            if (!$level) return "-1";

            if ($inc && $this->repo->get_donwload_action($levelID, $hostname) < 2) {
                $this->repo->increment_downloads($levelID);
                $this->repo->download_action($levelID,  $hostname);
            }

            return $level->to_response($this->gameVersion, $this->binaryVersion, $daily, $feaID, (bool) $extras);
        }

        public function getDaily(int $type): string {
            $data = $this->repo->get_daily($type);
            if (!$data) return "-1";
            
            $daily_id = (int) $data['feaID'];
	        if($type == 1) $daily_id += 100001;
            if ($type == 2) $daily_id += 200001;
            
            $midnight = ($type == 1) ? strtotime('next monday') : strtotime('tomorrow 00:00:00');
	        $timeleft = $midnight - time();
            return $daily_id."|".$timeleft;
        }

        public function rateStar(int $accountID, int $levelID, int $starStars): string {
           if (!is_numeric($accountID)) return "-1";
            
            $difficulty = $this->Main->get_difficulty($starStars, "stars");
            $this->repo->action_rate($accountID, $levelID, $difficulty['difficulty']);
            $count = $this->repo->get_rate_count($levelID);

            if ($this->repo->get_is_rated($levelID)) return "-1";

            if ($count > 5) {
                $avg = round($this->repo->get_rate_average($levelID));
                $auto = ($difficulty["auto"] == 1 && $avg == 10) ? 1 : 0;
                $demon = ($difficulty["demon"] == 1 && $avg == 50) ? 1 : 0;

                $this->Main->rate_level($accountID, $levelID, 0, $avg, $auto, $demon);

                return "1";
            }

            return "-1";
        }

        public function rateDemon(int $accountID, int $levelID, int $rating): string 
        {
            if (!$this->Main->getRolePermission($accountID, "actionRateDemon")) return "-1";
            
            $data = $this->Lib->demon_filter($rating);
            $this->repo->demon_diff($levelID, $data['demon']);

            return (string) $levelID;
        }

        public function rateSuggest(int $accountID, int $levelID, int $starStars, int $feature, array $difficulty): string {
            if ($this->Main->getRolePermission($accountID, "actionRateStars"))
            {
                $this->Main->rate_level($accountID, $levelID, $starStars, $difficulty["difficulty"], $difficulty["auto"], $difficulty["demon"]);
                $this->Main->feature_level($accountID, $levelID, $feature);
                $this->Main->verify_coins($accountID, $levelID, 1);

                return "1";
            }

            if ($this->Main->getRolePermission($accountID, "actionSuggestRating"))
            {
                $this->Main->suggest_level($accountID, $levelID, $difficulty["difficulty"], $starStars, $feature, $difficulty["auto"], $difficulty["demon"]);

                return "1";
            }

            return "-2";
        }

        public function report(int $levelID, string $hostname): string {
            $result = $this->repo->report($levelID, $hostname);
            return $result !== null ? (string) $result : "-1";
        }

        public function updateDesc($accountID, int $levelID, string $levelDescription): string {
            $raw_desc = str_replace(['-', '_'], ['+', '/'], $levelDescription);
            $raw_desc = base64_decode($raw_desc);

            if (strpos($raw_desc, '<c') !== false) {
                $tags = substr_count($raw_desc, '<c');
                $close_tags = substr_count($raw_desc, '</c>');

                if ($tags > $close_tags) {
                    $raw_desc .= str_repeat('</c>', $tags - $close_tags);
                    $levelDescription = str_replace(['+', '/'], ['-', '_'], base64_encode($raw_desc));
                }
            }

            if ($this->repo->description($levelDescription, $accountID, $levelDescription)) {
                return "1";
            }

            return "-1";
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
            $data = new LevelUploadDTO();
            $data->accountID = $accountID;
            $data->levelID = $levelID;
            $data->userName = $userName;
            $data->hostname = $hostname;
            $data->userID = $userID;
            $data->levelName = $levelName;
            $data->audioTrack = (int) $audioTrack;
            $data->levelLength = (int) $levelLength;
            $data->secret = (int) $secret;
            $data->levelString = $levelString;
            $data->gjp = $gjp;
            $data->levelVersion = (int) $levelVersion;
            $data->ts = (int) $ts;
            $data->songs = $songs;
            $data->sfxs = $sfxs;
            $data->auto = (int) $auto;
            $data->original = (int) $original;
            $data->twoPlayer = (int) $twoPlayer;
            $data->songID = (int) $songID;
            $data->object = (int) $object;
            $data->coins = (int) $coins;
            $data->requestedStars = (int) $requestedStars;
            $data->extraString = $extraString;
            $data->levelInfo = $levelInfo;
            $data->unlisted = (int) $unlisted;
            $data->unlisted2 = (int) $unlisted2;
            $data->ldm = (int) $ldm;
            $data->wt = (int) $wt;
            $data->wt2 = (int) $wt2;
            $data->settingsString = $settingsString;
            $data->levelDescription = $levelDescription;
            $data->password = (int) $password;
            $data->gameVersion = $this->gameVersion;
            $data->binaryVersion = $this->binaryVersion;

            if ($this->repo->get_recent_upload($userID, $hostname) > 0) return "-1";
            if (empty($levelString) || empty($levelName)) return "-1";

            $extID = $this->repo->get_by_name($levelName, $userID);

            if ($extID !== null) {
                if ($this->repo->update($data, $extID)) {
                    file_put_contents(__DIR__."/../database/data/levels/$extID", $levelString);
                    return $extID;
                }
            } 
            else {
                $newID = $this->repo->create($data);
                if ($newID > 0) {
                    file_put_contents(__DIR__."/../database/data/levels/$newID", $levelString);
                    return $newID;
                }
            }

            return "-1";
        }
    }
