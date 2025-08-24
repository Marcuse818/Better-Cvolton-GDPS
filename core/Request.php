<?php
    interface RequestInterface {
        public function request(int $accountID): string;
    }

    class Request implements RequestInterface {
        protected $connection;
        protected $main, $lib;

        public function __construct() {
            $Database = new Database();
            $this->main = new Main();
            $this->lib = new Lib();

            $this->connection = $Database->open_connection();
        }

        public function request(int $accountID): string {
            if ($this->main->getRolePermission($accountID, "actionRequestMod") >= 1)
            {
                if ($this->main->getRolePermission($accountID, "modBadgeLevel") >= 3) return 3;
                return $this->main->getRolePermission($accountID, "modBadgeLevel"); 
            }

            return "-1";
        }
    }
?>