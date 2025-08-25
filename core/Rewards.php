<?php
    require_once __DIR__."/Main.php";
    
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/XORCipher.php";
    require_once __DIR__."/lib/generateHash.php";
    require_once __DIR__."/../config/dailyChests.php";

    interface ChallengesInterface {
        public function getData(int $accountID, $udid, $check): string;
    }

    class Challenges implements ChallengesInterface {
        protected $connection; 
        protected $Main, $Database;

        public function __construct() {
            $this->Database = new Database();
            $this->Main = new Main();
 
            $this->connection = $this->Database->open_connection();
        }

        public function getData(int $accountID, $udid, $check): string {
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