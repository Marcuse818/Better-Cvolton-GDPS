<?php
require_once __DIR__ . "/Main.php";
require_once __DIR__ . "/lib/Database.php";
require_once __DIR__ . "/lib/generateHash.php";

interface GauntletInterface {
    public function get_data(): string;
}

class GauntletPack implements GauntletInterface {
    private $database;
    
    public function __construct() { 
        $this->database = new Database();
    }

    public function get_data(): string {
        try {
            $gauntletString = "";
            $hashString = "";

            $gauntlets = $this->database->fetch_all(
                "SELECT ID, level1, level2, level3, level4, level5 
                 FROM gauntlets 
                 WHERE level5 != '0' 
                 ORDER BY ID ASC"
            );

            if (empty($gauntlets)) {
                return "#" . GenerateHash::genSolo2("");
            }

            foreach ($gauntlets as $gauntlet) {
                $levels = $this->buildLevelsString($gauntlet);
                $gauntletString .= "1:" . $gauntlet["ID"] . ":3:" . $levels . "|";
                $hashString .= $gauntlet['ID'] . $levels;
            }

            $gauntletString = rtrim($gauntletString, "|");

            $hash = GenerateHash::genSolo2($hashString);

            return $gauntletString . "#" . $hash;

        } catch (Exception $e) {
            error_log("GauntletPack get_data error: " . $e->getMessage());
            return "#" . GenerateHash::genSolo2("");
        }
    }

    private function buildLevelsString(array $gauntlet): string {
        return implode(",", [
            $gauntlet["level1"],
            $gauntlet["level2"], 
            $gauntlet["level3"],
            $gauntlet["level4"],
            $gauntlet["level5"]
        ]);
    }
}