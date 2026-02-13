<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/XORCipher.php";
require_once __DIR__."/lib/generateHash.php";
require_once __DIR__."/../config/dailyChests.php";

interface ChallengesInterface {
    public function getData(int $accountId, string $udid, string $check): string;
}

class Challenges implements ChallengesInterface {
    private Database $db;
    private Main $main;

    public function __construct() {
        $this->db = new Database();
        $this->main = new Main();
    }

    public function getData(int $accountId, string $udid, string $check): string {
        $userId = ($accountId !== 0) ? $this->main->getUserId($accountId) : $this->main->getUserId($udid);
        $decodedCheck = XORCipher::cipher(base64_decode(substr($check, 5)), 19847);
        $difference = time() - strtotime('2000-12-17');
        $questId = floor($difference / 86400);

        $questId = $questId * 3;
        $quest1 = $questId;
        $quest2 = $questId + 1;
        $quest3 = $questId + 2;

        $timeLeft = strtotime("tomorrow 00:00:00") - time();

        $challenges = $this->db->fetchAll(
            "SELECT type, amount, reward, name FROM quests"
        );

        shuffle($challenges);

        if (empty($challenges[0]) || empty($challenges[1]) || empty($challenges[2])) {
            return "-1";
        }

        $quest1 = $quest1 . "," . $challenges[0]["type"] . "," . $challenges[0]["amount"] . "," . $challenges[0]["reward"] . "," . $challenges[0]["name"];
        $quest2 = $quest2 . "," . $challenges[1]["type"] . "," . $challenges[1]["amount"] . "," . $challenges[1]["reward"] . "," . $challenges[1]["name"];
        $quest3 = $quest3 . "," . $challenges[2]["type"] . "," . $challenges[2]["amount"] . "," . $challenges[2]["reward"] . "," . $challenges[2]["name"];

        $dataString = sprintf(
            "SaKuJ:%d:%s:%s:%d:%d:%s:%s:%s",
            $userId,
            $decodedCheck,
            $udid,
            $accountId,
            $timeLeft,
            $quest1,
            $quest2,
            $quest3
        );

        $ciphered = XORCipher::cipher($dataString, 19847);
        $encoded = base64_encode($ciphered);
        $hash = GenerateHash::genSolo3($encoded);

        return "SaKuJ" . $encoded . "|" . $hash;
    }
}