<?php
    require_once __DIR__."/Main.php";
    
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/XORCipher.php";
    require_once __DIR__."/lib/generateHash.php";
    require_once __DIR__."/../config/dailyChests.php";

    interface Rewards {
        public function getData(int $accountID, $udid, $check, int $rewardType = 0): string;
    }

    class Chests extends ConfigChests implements Rewards {
        protected $connection; 
        protected $Main, $Database;

        public function __construct() {
            $this->Database = new Database();
            $this->Main = new Main();
 
            $this->connection = $this->Database->open_connection();
        }

        public function getData(int $accountID, $udid, $check, int $rewardType = 0): string {
            $extID = $this->Main->get_post_id();
            $check = XORCipher::cipher(base64_decode(substr($check, 5)), 59182);
            $userID = $this->Main->get_user_id($extID);

            $chests = $this->connection->prepare("SELECT chest1time, chest1count, chest2time, chest2count FROM users WHERE extID = :extID");
            $chests->execute([":extID" => $extID]);
            $chests = $chests->fetch();

            $currentTime = time() + 100;

            // chest 1
            $smallChestTime = $chests["chest1time"];
            $smallChestCount = $chests["chest1count"];

            // chest 2
            $bigChestTime = $chests["chest2time"];
            $bigChestCount = $chests["chest2count"];

            // chest time diff
            $smallChestTimeDiff = $currentTime - $smallChestTime;
            $bigChestTimeDiff = $currentTime - $bigChestTime;

            // chest items
            $smallChestItems = isset($this->smallChestItems) ? $this->smallChestItems : [1, 2, 3, 4, 5, 6];
            $bigChestItems = isset($this->bigChestItems) ? $this->bigChestItems : [1, 2, 3, 4, 5, 6];

            // chest stuff
            $smallChest = rand($this->smallChestMinOrbs, $this->smallChestMaxOrbs).",".rand($this->smallChestMinDiamonds, $this->smallChestMaxDiamonds).",".$smallChestItems[array_rand($smallChestItems)].",".rand($this->smallChestMinKeys, $this->smallChestMaxKeys)."";
            $bigChest = rand($this->bigChestMinOrbs, $this->bigChestMaxOrbs).",".rand($this->bigChestMinDiamonds, $this->bigChestMaxDiamonds).",".$bigChestItems[array_rand($bigChestItems)].",".rand($this->bigChestMinKeys, $this->bigChestMaxKeys)."";
            
            // chest left
            $smallChestLeft = max(0, $this->smallChestTime - $smallChestTimeDiff);
            $bigChestLeft = max(0, $this->bigChestTime - $bigChestTimeDiff);

            switch ($rewardType) {
                case 1:
                    if ($smallChestLeft != 0) return -1;
                    $smallChestCount++;
                    $update = $this->connection->prepare("UPDATE users SET chest1count = :chest1count, chest1time = :currenttime WHERE userID = :userID");
                    $update->execute([":userID" => $userID, ":chest1count" => $smallChestCount, ":currenttime" => $currentTime]);
                    $smallChestLeft = $this->smallChestTime;
                    break;
                
                case 2:
                    if ($bigChestLeft != 0) return -1;
                    $bigChestCount++;
                    $update = $this->connection->prepare("UPDATE users SET chest2count = :chest2count, chest2time = :currenttime WHERE userID = :userID");
                    $update->execute([":userID" => $userID, ":chest2count" => $bigChestCount, ":currenttime" => $currentTime]);
                    $bigChestLeft = $this->bigChestTime;
                    break;
            }

            $string = base64_encode(XORCipher::cipher("1:".$userID.":".$check.":".$udid.":".$accountID.":".$smallChestLeft.":".$smallChest.":".$smallChestCount.":".$bigChestLeft.":".$bigChest.":".$bigChestCount.":".$rewardType."", 59182));
            $string = str_replace("/", "_", $string);
            $string = str_replace("+", "-", $string);
            $hash = GenerateHash::genSolo4($string);

            return "SaKuJ".$string."|".$hash;
        }
    }

    class Challenges implements Rewards {
        protected $connection; 
        protected $Main, $Database;

        public function __construct() {
            $this->Database = new Database();
            $this->Main = new Main();
 
            $this->connection = $this->Database->open_connection();
        }

        public function getData(int $accountID, $udid, $check, int $rewardType = 0): string {
            $userID = ($accountID !== 0) ? $this->Main->get_user_id($accountID) : $this->Main->get_user_id($udid);
            $check = XORCipher::cipher(base64_decode(substr($check, 5)), 19847);
            $difference = time() - strtotime('2000-12-17');
            $questID = floor($difference / 86400);

            $questID = $questID * 3;
            $quest_1 = $questID;
            $quest_2 = $questID + 1;
            $quest_3 = $questID + 2;

            $timeleft = strtotime("tomorrow 00:00:00") - time();

            $challenge = $this->connection->prepare("SELECT type, amount, reward, name FROM quests");
            $challenge->execute();
            $challenges = $challenge->fetchAll();

            shuffle($challenges);

            if (empty($challenges[0]) || empty($challenges[1]) || empty($challenges[2])) return -1;

            $quest_1 = $quest_1.",".$challenges[0]["type"].",".$challenges[0]["amount"].",".$challenges[0]["reward"].",".$challenges[0]["name"]."";
            $quest_2 = $quest_2.",".$challenges[1]["type"].",".$challenges[1]["amount"].",".$challenges[1]["reward"].",".$challenges[1]["name"]."";
            $quest_3 = $quest_3.",".$challenges[2]["type"].",".$challenges[2]["amount"].",".$challenges[2]["reward"].",".$challenges[2]["name"]."";

            $string = base64_encode(XORCipher::cipher("SaKuJ:".$userID.":".$check.":".$udid.":".$accountID.":".$timeleft.":".$quest_1.":".$quest_2.":".$quest_3."", 19847));
            $hash = GenerateHash::genSolo3($string);

            return "SaKuJ".$string."|".$hash;
        }
    }