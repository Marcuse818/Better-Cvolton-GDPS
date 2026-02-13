<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/Lib.php";

interface AccountCommentsInterface {
    public function getData(int $userId, int $page): string;
    public function uploadComment(int $userId, string $userName, string $comment): string;
    public function deleteComment(int $userId, int $commentId, int $permission): string;
}

class AccountComments implements AccountCommentsInterface { 
    protected Database $db;
    protected Lib $lib;
    protected int $uploadDate;

    public function __construct() {
        $this->db = new Database();
        $this->lib = new Lib();
        $this->uploadDate = time();
    }

    public function getData(int $userId, int $page): string {
        if ($page < 0 || $userId <= 0) {
            return "-1";
        }

        $offset = $page * 10;

        $commentsData = $this->db->fetchAll(
            "SELECT userID, commentID, comment, likes, isSpam, timestamp 
             FROM acccomments 
             WHERE userID = ? 
             ORDER BY timeStamp DESC 
             LIMIT 10 OFFSET ?",
            [$userId, $offset]
        );

        if (empty($commentsData)) {
            return '#0:0:0';
        }

        $commentsCount = $this->db->count(
            "acccomments",
            "userID = ?",
            [$userId]
        );
        
        $commentString = "";
        
        foreach ($commentsData as $comment) {
            if ($comment['commentID'] <= 0) {
                continue;
            } 
            
            $uploadDate = $this->lib->makeTime($comment['timestamp']);
            $commentString .= sprintf(
                "2~%s~3~%d~4~%d~5~0~7~%d~9~%s~6~%d|",
                $comment["comment"],
                $comment["userID"],
                $comment["likes"],
                $comment["isSpam"],
                $uploadDate,
                $comment["commentID"]
            );
        }

        $commentString = rtrim($commentString, "|");
        
        return $commentString . "#" . $commentsCount . ":" . $page . ":10";
    }

    public function uploadComment(int $userId, string $userName, string $comment): string {
        $comment = trim($comment);
        
        if (empty($comment) || strlen($comment) > 1000) {
            return "-1";
        }

        $cleanComment = $this->lib->sanitizeInput($comment);
        $cleanUsername = $this->lib->sanitizeInput($userName);

        $this->db->insert('acccomments', [
            'userName' => $cleanUsername,
            'comment' => $cleanComment,
            'userID' => $userId,
            'timeStamp' => $this->uploadDate
        ]);

        return "1";
    }

    public function deleteComment(int $userId, int $commentId, int $permission): string {
        if ($commentId <= 0) {
            return "-1";
        } 
        
        if ($permission > 0) {
            $deleted = $this->db->delete(
                'acccomments',
                'commentID = ? AND userID = ?',
                [$commentId, $userId]
            );
            
            if ($deleted > 0) {
                return "1";
            }
        }

        $this->db->delete(
            'acccomments',
            'commentID = ?',
            [$commentId]
        );

        return "1";
    }
}