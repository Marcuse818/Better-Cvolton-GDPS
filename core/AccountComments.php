<?php
    require_once __DIR__."/Main.php";
    
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/Lib.php";

    interface AccountCommentsInterface {
        public function get_data(int $user_id, int $page): string;
        public function upload_comment(int $user_id, string $user_name, string $comment): string;
        public function delete_comment(int $user_id, int $comment_id, int $permission): string;
    }

    class AccountComments implements AccountCommentsInterface { 
        protected $db, $main, $lib;
        public $upload_date;

        public function __construct() {
            $this->db = new Database();
            $this->lib = new Lib();
            $this->upload_date = time();
        }

        public function get_data(int $user_id, int $page): string {
            if ($page < 0) return "-1";
            if ($user_id <= 0) return "-1";

            $page *= 10;
            $comment_string = "";

            $comments_data = $this->db->fetch_all("SELECT userID, commentID, comment, likes, isSpam, timestamp FROM acccomments WHERE userID = :userID ORDER BY timeStamp DESC LIMIT 10 OFFSET :offset",[':userID' => $user_id, ":offset" => $page]);

            if (empty($comments_data)) return '#0:0:0';

            $comments_count = $this->db->count(
                "acccomments",
                "userID = :userID",
                [":userID" => $user_id]
            );
            
            foreach ($comments_data as $comment) {
                if ($comment['commentID'] <= 0) continue; 
                
                $this->upload_date = $this->lib->make_time($comment['timestamp']);
                $comment_string .= "2~".$comment["comment"]."~3~".$comment["userID"]."~4~".$comment["likes"]."~5~0~7~".$comment["isSpam"]."~9~".$this->upload_date."~6~".$comment["commentID"]."|";
            }

            $comment_string = substr($comment_string, 0, -1);
            
            return $comment_string . "#" . $comments_count . ":" . $page . ":10";
        }

        public function upload_comment(int $user_id, string $user_name, string $comment): string {
            if (empty(trim($comment))) return "-1";
            if (strlen($comment) > 1000) return "-1";

            $clean_comment = $this->lib->sanitize_input($comment);
            $clean_username = $this->lib->sanitize_input($user_name);

            $comment = $this->db->insert(
                "INSERT INTO acccomments (userName, comment, userID, timeStamp) VALUE (:userName, :comment, :userID, :uploadDate)", 
                [
                    ":userName" => $clean_username, 
                    ":userID" => $user_id, 
                    ":comment" => $clean_comment,
                    ":uploadDate" => $this->upload_date
                ]
            );

            return "1";
        }

        public function delete_comment(int $user_id, int $comment_id, int $permission): string {
            if ($comment_id <= 0) return "-1"; 
            
            $comment = "";
            
            if ($permission > 0) {
                $comment = $this->db->execute(
                "DELETE FROM acccomments WHERE commentID = :commentID AND userID = :userID LIMIT 1", 
                [
                        ':commentID' => $comment_id, 
                        ':userID' => $user_id
                    ]
                );
            }

            $comment = $this->db->execute(
                "DELETE FROM acccomments WHERE commentID = :commentID LIMIT 1", 
                [":commentID" => $comment_id]
            );

            return "1";
        }
    }
?>