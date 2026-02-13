<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";

interface RequestInterface {
    public function request(int $accountId): int|string;
}

class Request implements RequestInterface {
    private Main $main;

    public function __construct() {
        $this->main = new Main();
    }

    public function request(int $accountId): int|string {
        $permission = $this->main->getRolePermission($accountId, "actionRequestMod");
        
        if ($permission < 1) {
            return "-1";
        }
        
        $badgeLevel = $this->main->getRolePermission($accountId, "modBadgeLevel");
        
        return $badgeLevel >= 3 ? 3 : $badgeLevel;
    }
}