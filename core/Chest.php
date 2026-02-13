<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/XORCipher.php";
require_once __DIR__."/lib/generateHash.php";
require_once __DIR__."/../config/dailyChests.php";

interface ChestInterface {
    public function getData(int $accountId, string $udid, string $check, int $rewardType = 0): string;
}

class Chest extends ConfigChests implements ChestInterface {
    protected Database $db;
    protected Main $main;

    public function __construct() {
        $this->db = new Database();
        $this->main = new Main();
    }

    public function getData(int $accountId, string $udid, string $check, int $rewardType = 0): string {
        $extId = $this->main->getPostId();
        $decodedCheck = XORCipher::cipher(base64_decode(substr($check, 5)), 59182);
        $userId = $this->main->getUserId($extId);

        $chests = $this->db->fetchOne(
            "SELECT chest1time, chest1count, chest2time, chest2count FROM users WHERE extID = ?",
            [$extId]
        );

        if (!$chests) {
            return "-1";
        }

        $currentTime = time() + 100;

        $smallChestTime = $chests["chest1time"];
        $smallChestCount = $chests["chest1count"];
        $bigChestTime = $chests["chest2time"];
        $bigChestCount = $chests["chest2count"];

        $smallChestTimeDiff = $currentTime - $smallChestTime;
        $bigChestTimeDiff = $currentTime - $bigChestTime;

        $smallChestItems = $this->smallChestItems ?? [1, 2, 3, 4, 5, 6];
        $bigChestItems = $this->bigChestItems ?? [1, 2, 3, 4, 5, 6];

        $smallChest = sprintf(
            "%d,%d,%d,%d",
            rand($this->smallChestMinOrbs, $this->smallChestMaxOrbs),
            rand($this->smallChestMinDiamonds, $this->smallChestMaxDiamonds),
            $smallChestItems[array_rand($smallChestItems)],
            rand($this->smallChestMinKeys, $this->smallChestMaxKeys)
        );

        $bigChest = sprintf(
            "%d,%d,%d,%d",
            rand($this->bigChestMinOrbs, $this->bigChestMaxOrbs),
            rand($this->bigChestMinDiamonds, $this->bigChestMaxDiamonds),
            $bigChestItems[array_rand($bigChestItems)],
            rand($this->bigChestMinKeys, $this->bigChestMaxKeys)
        );
        
        $smallChestLeft = max(0, $this->smallChestTime - $smallChestTimeDiff);
        $bigChestLeft = max(0, $this->bigChestTime - $bigChestTimeDiff);

        switch ($rewardType) {
            case 1:
                if ($smallChestLeft != 0) {
                    return "-1";
                }
                $smallChestCount++;
                $this->db->update(
                    'users',
                    ['chest1count' => $smallChestCount, 'chest1time' => $currentTime],
                    'userID = ?',
                    [$userId]
                );
                $smallChestLeft = $this->smallChestTime;
                break;
            
            case 2:
                if ($bigChestLeft != 0) {
                    return "-1";
                }
                $bigChestCount++;
                $this->db->update(
                    'users',
                    ['chest2count' => $bigChestCount, 'chest2time' => $currentTime],
                    'userID = ?',
                    [$userId]
                );
                $bigChestLeft = $this->bigChestTime;
                break;
        }

        $dataString = sprintf(
            "1:%d:%s:%s:%d:%d:%s:%d:%d:%s:%d:%d",
            $userId,
            $decodedCheck,
            $udid,
            $accountId,
            $smallChestLeft,
            $smallChest,
            $smallChestCount,
            $bigChestLeft,
            $bigChest,
            $bigChestCount,
            $rewardType
        );

        $ciphered = XORCipher::cipher($dataString, 59182);
        $encoded = base64_encode($ciphered);
        $encoded = str_replace(["/", "+"], ["_", "-"], $encoded);
        
        $hash = GenerateHash::genSolo4($encoded);

        return "SaKuJ" . $encoded . "|" . $hash;
    }
}