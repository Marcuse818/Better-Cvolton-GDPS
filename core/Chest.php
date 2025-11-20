<?php
    require_once __DIR__."/Main.php";
    
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/XORCipher.php";
    require_once __DIR__."/lib/generateHash.php";
    require_once __DIR__."/../config/dailyChests.php";

    interface ChestInterface {
        public function get_data(int $accountID, $udid, $check, int $rewardType = 0): string;
    }

    class Chest extends ConfigChests implements ChestInterface {
        protected $db, $main;

        public function __construct() {
            $this->db = new Database();
            $this->main = new Main();
        }

        public function get_data(int $accountID, $udid, $check, int $rewardType = 0): string {
            $ext_id = $this->main->get_post_id();
            $check = XORCipher::cipher(base64_decode(substr($check, 5)), 59182);
            $user_id = $this->main->get_user_id($ext_id);

            $chests = $this->db->fetch_one(
                "SELECT chest1time, chest1count, chest2time, chest2count FROM users WHERE extID = :extID",
                [ ":extID" => $ext_id ]
            );
            $current_time = time() + 100;

            $smallChestTime = $chests["chest1time"];
            $smallChestCount = $chests["chest1count"];

            $bigChestTime = $chests["chest2time"];
            $bigChestCount = $chests["chest2count"];

            $smallChestTimeDiff = $current_time - $smallChestTime;
            $bigChestTimeDiff = $current_time - $bigChestTime;

            $smallChestItems = isset($this->smallChestItems) ? $this->smallChestItems : [1, 2, 3, 4, 5, 6];
            $bigChestItems = isset($this->bigChestItems) ? $this->bigChestItems : [1, 2, 3, 4, 5, 6];

            $smallChest = rand($this->smallChestMinOrbs, $this->smallChestMaxOrbs).",".rand($this->smallChestMinDiamonds, $this->smallChestMaxDiamonds).",".$smallChestItems[array_rand($smallChestItems)].",".rand($this->smallChestMinKeys, $this->smallChestMaxKeys)."";
            $bigChest = rand($this->bigChestMinOrbs, $this->bigChestMaxOrbs).",".rand($this->bigChestMinDiamonds, $this->bigChestMaxDiamonds).",".$bigChestItems[array_rand($bigChestItems)].",".rand($this->bigChestMinKeys, $this->bigChestMaxKeys)."";
            
            $smallChestLeft = max(0, $this->smallChestTime - $smallChestTimeDiff);
            $bigChestLeft = max(0, $this->bigChestTime - $bigChestTimeDiff);

            switch($rewardType) {
                case 1:
                    if ($smallChestLeft != 0) return -1;
                    $smallChestCount++;
                    $this->db->execute(
                        "UPDATE users SET chest1count = :chest1count, chest1time = :currenttime WHERE userID = :userID",
                        [ ':userID' => $user_id, ':currenttime' => $current_time ]
                    );
                    $smallChestLeft = $this->smallChestTime;
                    break;
                
                case 2:
                    if ($bigChestLeft != 0) return -1;
                    $bigChestCount++;
                    $this->db->execute(
                        "UPDATE users SET chest2count = :chest2count, chest2time = :currenttime WHERE userID = :userID",
                        [ ":userID" => $user_id, ':chest2count' => $bigChestCount, ':currenttime' => $current_time ]
                    );
                    $bigChestLeft = $this->bigChestTime;
                    break;
            }

            $string = base64_encode(XORCipher::cipher("1:".$user_id.":".$check.":".$udid.":".$accountID.":".$smallChestLeft.":".$smallChest.":".$smallChestCount.":".$bigChestLeft.":".$bigChest.":".$bigChestCount.":".$rewardType."", 59182));
            $string = str_replace("/", "_", $string);
            $string = str_replace("+", "-", $string);
            $hash = GenerateHash::genSolo4($string);

            return "SaKuJ".$string."|".$hash;
        }
    }
?>