<?php
    require_once __DIR__ ."/Main.php";

    require_once __DIR__ ."/lib/Database.php";
    require_once __DIR__ ."/lib/generateHash.php";

    interface GauntletInterface {
        public function get_data(): string;
    }

    class GauntletPack implements GauntletInterface {
        private $connection;
        
        public function __construct() { 
            $database = new Database();
            $this->connection = $database->open_connection();
        }

        public function get_data(): string {
            $gauntlet_string = "";
            $string = "";

            $gauntlets = $this->connection->prepare("SELECT ID, level1, level2, level3, level4, level5 FROM gauntlets WHERE level5 != '0' ORDER BY ID ASC");
            $gauntlets->execute();
            $gauntlets = $gauntlets->fetchAll();

            foreach ($gauntlets as $gauntlet) {
                $levels = $gauntlet["level1"].",".$gauntlet["level2"].",".$gauntlet["level3"].",".$gauntlet["level4"].",".$gauntlet["level5"];
                $gauntlet_string .= "1:".$gauntlet["ID"].":3:".$levels."|";
                $string .= $gauntlet['ID'].$levels;
            }

            $gauntlet_string = substr($gauntlet_string, 0, -1);

            return $gauntlet_string."#".GenerateHash::genSolo2($string);
        }
    }
?>