<?php
require_once __DIR__ . "/Main.php";
require_once __DIR__ . "/lib/Database.php";
require_once __DIR__ . "/lib/generateHash.php";

interface MappackInterface {
    public function getData(int $page = 0): string;
}

class MapPacks implements MappackInterface {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getData(int $page = 0): string {
        $offset = $page * 10;
        $mappackString = "";
        $levels = "";
        $hashString = "";

        $mapPacks = $this->db->fetchAll(
            "SELECT colors2, rgbcolors, ID, name, levels, stars, coins, difficulty 
             FROM mappacks 
             ORDER BY ID ASC 
             LIMIT 10 OFFSET ?",
            [$offset]
        );

        foreach ($mapPacks as $mapPack) {
            $levels .= $mapPack["ID"] . ",";
            $color2 = $mapPack["colors2"];

            if ($color2 == "none" || $color2 == "") {
                $color2 = $mapPack['rgbcolors'];
            }

            $mappackString .= sprintf(
                "1:%d:2:%s:3:%s:4:%d:5:%d:6:%d:7:%s:8:%s|",
                $mapPack["ID"],
                $mapPack["name"],
                $mapPack["levels"],
                $mapPack["stars"],
                $mapPack["coins"],
                $mapPack["difficulty"],
                $mapPack["rgbcolors"],
                $color2
            );
            
            $hashString .= $mapPack["ID"] . $mapPack["levels"];
        }

        $totalMapPacks = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM mappacks"
        );

        $mappackString = rtrim($mappackString, "|");
        $levels = rtrim($levels, ",");

        return $mappackString . "#" . $totalMapPacks . ":" . $page . ":10#" . GenerateHash::genPack($levels);
    }
}