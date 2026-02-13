<?php
require_once __DIR__ . "/Main.php";
require_once __DIR__ . "/lib/Database.php";
require_once __DIR__ . "/lib/generateHash.php";

interface ListInterface {
    public function getData(int $accountId, int $page, int $type): string;
    public function deleteList(int $accountId, int $listId): string;
    public function uploadList(int $accountId, int $listId): string;
}

class Lists implements ListInterface {
    private Database $db;
    private Main $main;
    
    public $followed;
    public $difficulty;
    public $demonFilter;
    public $star;
    public $levelsList;
    public $secret;
    public $featured;
    public $string;
    public $listDescription;
    public $listVersion;
    public $listName;
    public $original;
    public $unlisted;

    public function __construct() {
        $this->db = new Database();
        $this->main = new Main();
    }

    public function getData(int $accountId, int $page, int $type): string {
        $userString = "";
        $levelString = "";
        $params = [];
        $whereConditions = [];
        $order = "";
        $joins = "";

        if (!empty($this->star) || (!empty($this->featured) && $this->featured == 1)) {
            $whereConditions[] = "NOT starStars = 0";
        }

        switch ($this->difficulty) {
            case -1: 
                $whereConditions[] = "starDifficulty = '-1'";
                break;
            case -2:
                $whereConditions[] = "starDifficulty = 5+" . $this->demonFilter;
                break;
            case -3:
                $whereConditions[] = "starDifficulty = '0'";
                break;
            case "-": 
                break;
            default:
                if ($this->difficulty) {
                    $whereConditions[] = "starDifficulty IN ($this->difficulty)";
                }
                break;
        }

        switch ($type) {
            case 0:
                $order = "likes";
                if (!empty($this->string)) {
                    $whereConditions[] = is_numeric($this->string) 
                        ? "listID = '$this->string'" 
                        : "listName LIKE '%$this->string%'";
                }
                break;
            case 1:
                $order = "downloads";
                break;
            case 2: 
                $order = "likes"; 
                break;
            case 3:
                $order = "downloads";
                $whereConditions[] = "lists.uploadDate > " . (time() - 604800);
                break;
            case 4: 
                $order = "uploadDate"; 
                break;
            case 5: 
                $whereConditions[] = "lists.accountID = '$this->string'"; 
                break;
            case 6:
                $whereConditions[] = "lists.starStars > 0";
                $whereConditions[] = "lists.starFeatured > 0";
                $order = "downloads";
                break;
            case 11:
                $whereConditions[] = "lists.starStars > 0";
                $order = "downloads";
                break;
            case 12:
                if (empty($this->followed)) $this->followed = 0;
                $whereConditions[] = "lists.accountID IN ($this->followed)";
                break;
            case 13:
                $friends = $this->main->getFriends($accountId);
                $friends = implode(",", $friends);
                $whereConditions[] = "lists.accountID IN ($friends)";
                break;
            case 7:
            case 27:
                $whereConditions[] = "suggest.suggestLevelID < 0";
                $order = "suggest.timestamp";
                $joins = "LEFT JOIN suggest ON lists.listID*-1 LIKE suggest.suggestLevelId";
                break;
        }

        $queryBase = "FROM lists LEFT JOIN users ON lists.accountID LIKE users.extID $joins";

        if (!empty($whereConditions)) {
            $queryBase .= " WHERE (" . implode(" ) AND ( ", $whereConditions) . ")";
        }

        $query = "SELECT lists.*, UNIX_TIMESTAMP(uploadDate) AS uploadDateUnix, 
                  UNIX_TIMESTAMP(updateDate) AS updateDateUnix, users.userID, users.userName, users.extID 
                  $queryBase";

        if ($order) {
            $query .= " ORDER BY $order DESC";
        }
        
        $query .= " LIMIT 10 OFFSET $page";
        
        $countQuery = "SELECT COUNT(*) $queryBase";

        $result = $this->db->fetchAll($query);
        $totalLvlCount = $this->db->fetchColumn($countQuery);

        $levelCount = count($result);
        
        foreach ($result as &$list) {
            if (!$list['uploadDateUnix']) $list['uploadDateUnix'] = 0;
            if (!$list['updateDateUnix']) $list['updateDateUnix'] = 0;
            
            $levelString .= sprintf(
                "1:%d:2:%s:3:%s:5:%d:49:%d:50:%s:10:%d:7:%d:14:%d:19:%d:51:%s:55:%d:56:%d:28:%d:29:%d|",
                $list['listID'],
                $list['listName'],
                $list['listDesc'],
                $list['listVersion'],
                $list['accountID'],
                $list['userName'],
                $list['downloads'],
                $list['starDifficulty'],
                $list['likes'],
                $list['starFeatured'],
                $list['listlevels'],
                $list['starStars'],
                $list['countForReward'],
                $list['uploadDateUnix'],
                $list['updateDateUnix']
            );
            
            $userId = $this->db->fetchColumn(
                "SELECT userID FROM users WHERE extID = ?",
                [$list["accountID"]]
            );

            $userString .= $this->main->getUserString($userId) . "|";
        }
        
        if (empty($levelString)) return "-1";
        
        if (!empty($this->string) && is_numeric($this->string) && $levelCount == 1) {
            $ip = $this->main->getIp();
            
            $downloadCount = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM actions_downloads WHERE levelID = ? AND ip = INET6_ATON(?)",
                ['-' . $this->string, $ip]
            );
            
            if ($downloadCount < 2) {
                $this->db->update(
                    'lists',
                    ['downloads' => 'downloads + 1'],
                    'listID = ?',
                    [$this->string]
                );
                
                $this->db->insert('actions_downloads', [
                    'levelID' => '-' . $this->string,
                    'ip' => $ip
                ]);
            }
        }
        
        $levelString = rtrim($levelString, "|");
        $userString = rtrim($userString, "|");
        
        return $levelString . "#" . $userString . "#" . $totalLvlCount . ":" . $page . ":10#Sa1ntSosetHuiHelloFromGreenCatsServerLOL";
    }

    public function deleteList(int $accountId, int $listId): string {
        if (is_numeric($listId) && $accountId == $this->main->getOwnerList($listId)) {
            $this->db->delete('lists', 'listID = ?', [$listId]);
            return "1";
        }

        return "-1";
    }

    public function uploadList(int $accountId, int $listId): string {
        if ($this->secret != "Wmfd2893gb7") return "-100";
        if (count(explode(",", $this->levelsList)) == 0) return "-6";
        if (!is_numeric($accountId)) return "-9";

        if ($listId != 0) {
            $existingList = $this->db->fetchOne(
                "SELECT * FROM lists WHERE listID = ? AND accountID = ?",
                [$listId, $accountId]
            );

            if (!empty($existingList)) {
                $this->db->update(
                    'lists',
                    [
                        'listDesc' => $this->listDescription,
                        'listVersion' => $this->listVersion,
                        'listlevels' => $this->levelsList,
                        'starDifficulty' => $this->difficulty,
                        'original' => $this->original,
                        'unlisted' => $this->unlisted,
                        'updateDate' => time()
                    ],
                    'listID = ?',
                    [$listId]
                );

                return (string)$listId;
            }
        }

        $newListId = $this->db->insert('lists', [
            'listName' => $this->listName,
            'listDesc' => $this->listDescription,
            'listVersion' => $this->listVersion,
            'accountID' => $accountId,
            'listlevels' => $this->levelsList,
            'starDifficulty' => $this->difficulty,
            'original' => $this->original,
            'unlisted' => $this->unlisted,
            'uploadDate' => date('Y-m-d H:i:s')
        ]);
        
        return (string)$newListId;
    }
}