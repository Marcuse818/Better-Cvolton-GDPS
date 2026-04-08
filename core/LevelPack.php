<?php
    require_once __DIR__ ."/Main.php";


    require_once __DIR__ ."/lib/Database.php";
    require_once __DIR__ ."/lib/generateHash.php";

    interface MappackInterface {
        public function get_data(int $page = 0): string;
    }

    class MapPacks implements MappackInterface {
        private $connection;

        public function __construct() {
            $database = new Database();
            $this->connection = $database->open_connection();
        }

        public function get_data(int $page = 0): string {
            $page *= 10;

            $map_packs = $this->connection->prepare("SELECT colors2, rgbcolors, ID, name, levels, stars, coins, difficulty FROM `mappacks` ORDER BY `ID` ASC LIMIT 10 OFFSET $page");
            $map_packs->execute();
            $map_packs_result = $map_packs->fetchAll();

            foreach ($map_packs_result as $map_pack) {
                $levels .= $map_pack["ID"].",";
                $color_2 = $map_pack["colors2"];

                if ($color_2 == "none" || $color_2 == "") $color_2 = $map_pack['rgbcolors'];

                $mappackString .= "1:".$map_pack["ID"].":2:".$map_pack["name"].":3:".$map_pack["levels"].":4:".$map_pack["stars"].":5:".$map_pack["coins"].":6:".$map_pack["difficulty"].":7:".$map_pack["rgbcolors"].":8:".$color_2."|";
            }

            $map_packs = $this->connection->prepare("SELECT count(*) FROM mappacks");
            $map_packs->execute();
            $total_map_packs = $map_packs->fetchColumn();

            $mappackString = substr($mappackString, 0, -1);
            $levels = substr($levels, 0, -1);

            return $mappackString."#".$total_map_packs.":".$page.":10#".GenerateHash::genPack($levels);
        }

    }