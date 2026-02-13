<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/Lib.php";

interface LevelCommentsInterface {
    public function getData(int $userId, int $levelId, int $page): string;
    public function delete(int $accountId, int $userId, int $commentId, int $permission): string;
    public function upload(int $accountId, int $userId, string $userName, string $comment, int $levelId): string;
}

class LevelComments implements LevelCommentsInterface {
    private Database $db;
    private Lib $lib;
    private Main $main;

    public int $uploadDate;
    public int $count = 10;
    public int $mode = 0;
    public int $percent = 0;
    public int $gameVersion = 0;
    public int $binaryVersion = 0;
    
    private string $modeColumn = "";
    private string $filterColumn = "";
    private string $filterToFilter = "";
    private bool $displayLevelId = false;
    private int $filterId = 0;
    private string $userListJoin = "";
    private string $userListWhere = "";

    public function __construct() {
        $this->db = new Database();
        $this->lib = new Lib();
        $this->main = new Main();
        $this->uploadDate = time();
    }

    public function getData(int $userId, int $levelId, int $page): string {
        $offset = $page * $this->count;
        $users = [];
        $commentString = "";
        $userString = "";

        $this->modeColumn = ($this->mode == 0) ? "timestamp" : "likes";

        if ($levelId) {
            $this->filterId = $levelId;
            $this->filterColumn = 'levelID';
            $this->filterToFilter = '';
            $this->displayLevelId = false;
            $this->userListJoin = '';
            $this->userListWhere = '';
        }
        
        if ($userId) {
            $this->filterId = $userId;
            $this->filterColumn = "userID";
            $this->filterToFilter = "comments.";
            $this->displayLevelId = true;
            $this->userListJoin = 'INNER JOIN levels ON comments.levelID = levels.levelID';
            $this->userListWhere = 'AND levels.unlisted = 0';
        }

        $commentCount = $this->db->count(
            "comments $this->userListJoin",
            "$this->filterToFilter$this->filterColumn = ? $this->userListWhere",
            [$this->filterId]
        );
        
        if ($commentCount == 0) return "-2";

        $comments = $this->db->fetchAll(
            "SELECT comments.levelID, comments.commentID, comments.timestamp, comments.comment, 
                    comments.userID, comments.likes, comments.isSpam, comments.percent, 
                    users.userName, users.icon, users.color1, users.color2, users.iconType, 
                    users.special, users.extID 
             FROM comments 
             LEFT JOIN users ON comments.userID = users.userID 
             $this->userListJoin 
             WHERE comments.{$this->filterColumn} = ? $this->userListWhere 
             ORDER BY comments.{$this->modeColumn} DESC 
             LIMIT $this->count OFFSET $offset",
            [$this->filterId]
        );
        
        foreach ($comments as $comment) {
            if ($comment['commentID'] == 0) continue;

            $uploadDate = $this->lib->makeTime($comment['timestamp']);
            $commentText = ($this->gameVersion < 20) ? base64_decode($comment['comment']) : $comment['comment'];  

            if ($this->displayLevelId) {
                $commentString .= "1~" . $comment["levelID"] . "~";
            }

            $commentString .= "2~" . $commentText . "~3~" . $comment["userID"] . "~4~" . $comment["likes"] . "~5~0~7~" . $comment["isSpam"] . "~9~" . $uploadDate . "~6~" . $comment["commentID"] . "~10~" . $comment["percent"];
                
            if ($comment["extID"]) {
                $extId = is_numeric($comment['extID']) ? $comment['extID'] : 0;

                if ($this->binaryVersion > 31) {
                    $badge = $this->main->getRolePermission($extId, 'modBadgeLevel');
                    $colorString = $badge > 0 ? "~12~" . $this->main->getRolePermission($extId, "commentColor") : "";
                    $commentString .= "~11~" . $badge . $colorString . ":1~" . $comment["userName"] . "~7~1~9~" . $comment["icon"] . "~10~" . $comment["color1"] . "~11~" . $comment["color2"] . "~14~" . $comment["iconType"] . "~15~" . $comment["special"] . "~16~" . $extId;
                }

                if (!in_array($comment['userID'], $users)) {
                    $users[] = $comment['userID'];
                    $userString .= $comment["userID"] . ":" . $comment["userName"] . ":" . $extId . "|";
                }

                $commentString .= "|";
            }
        }

        $commentString = rtrim($commentString, "|");
                
        if ($this->binaryVersion < 32) {
            $userString = rtrim($userString, "|");
            return $commentString . "#" . $userString . "#" . $commentCount . ":" . $page . ":" . $commentCount;
        }

        return $commentString . "#" . $commentCount . ":" . $page . ":" . $commentCount;
    }

    public function delete(int $accountId, int $userId, int $commentId, int $permission): string {
        $deleted = $this->db->delete(
            'comments',
            'commentID = ? AND userID = ?',
            [$commentId, $userId]
        );

        if ($deleted == 0) return "-1";

        $levelOwner = $this->db->fetchColumn(
            "SELECT users.extID FROM comments 
             INNER JOIN levels ON levels.levelID = comments.levelID 
             INNER JOIN users ON levels.userID = users.userID 
             WHERE commentID = ?",
            [$commentId]
        );

        if ($levelOwner == $accountId || $permission) {
            $this->db->delete(
                'comments',
                'commentID = ?',
                [$commentId]
            );
        }
            
        return "1";
    }

    public function upload(int $accountId, int $userId, string $userName, string $comment, int $levelId): string {
        if (empty($accountId) || empty($comment)) return "-1";

        $this->db->insert('comments', [
            'userName' => $userName,
            'comment' => $comment,
            'levelID' => $levelId,
            'userID' => $userId,
            'timeStamp' => $this->uploadDate,
            'percent' => $this->percent
        ]);

        if (!is_numeric($accountId)) return "1";

        if ($this->percent != 0) {
            $currentPercent = $this->db->fetchColumn(
                "SELECT percent FROM levelscores WHERE accountID = ? AND levelID = ?",
                [$accountId, $levelId]
            );

            if ($currentPercent < $this->percent) {
                $this->db->update(
                    'levelscores',
                    ['percent' => $this->percent, 'uploadDate' => $this->uploadDate],
                    'accountID = ? AND levelID = ?',
                    [$accountId, $levelId]
                );
            }
        } 

        return '1';
    }
}