<?php
    require_once __DIR__."/Main.php";
    
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/Lib.php";

    interface CommentsInterface {
        public function get_data(int $account_id, int $user_id, int $page): string;
        public function upload_comment(int $account_id, int $user_id, string $user_name, string $comment): string;
        public function delete_comment(int $user_id, int $comment_id, int $permission): string;
    }

    class AccountComments implements CommentsInterface { 
        protected $connection, $main, $lib;
        public $upload_date;

        public function __construct() {
            $database = new Database();
            $this->connection = $database->open_connection();
            $this->lib = new Lib();
            $this->upload_date = time();
        }

        public function get_data(int $account_id, int $user_id, int $page): string {
            $page *= 10;
            $comment_string = "";

            $comments = $this->connection->prepare("SELECT userID, commentID, comment, likes, isSpam, timestamp FROM acccomments WHERE userID = :userID ORDER BY timeStamp DESC LIMIT 10 OFFSET $page");
            $comments->execute([':userID' => $user_id]);
            $comments = $comments->fetchAll(PDO::FETCH_ASSOC);

            if ($comments == 0) return '#0:0:0';

            $comments_count = $this->connection->prepare("SELECT count(*) FROM acccomments WHERE userID = :userID");
            $comments_count->execute([":userID"=> $user_id]);
            $comments_count = $comments_count->fetchColumn();

            foreach ($comments as $comment) {
                if ($comment['commentID'] != 0) 
                {
                    $this->upload_date = $this->lib->make_time($comment['timestamp']);
                    $comment_string .= "2~".$comment["comment"]."~3~".$comment["userID"]."~4~".$comment["likes"]."~5~0~7~".$comment["isSpam"]."~9~".$this->upload_date."~6~".$comment["commentID"]."|";
                }
            }

            $comment_string = substr($comment_string, 0, -1);
            return $comment_string . "#" . $comments_count . ":" . $page . ":10";
        }

        public function upload_comment(int $account_id, int $user_id, string $user_name, string $comment): string {
            $comment = $this->connection->prepare("INSERT INTO acccomments (userName, comment, userID, timeStamp) VALUE (:userName, :comment, :userID, :uploadDate)");
            $comment->execute([":userName" => $user_name, ":userID" => $user_id, ":accountID" => $account_id, ":uploadDate" => $this->upload_date]);

            return "1";
        }

        public function delete_Comment(int $user_id, int $comment_id, int $permission): string {
            if ($permission) {
                $comment = $this->connection->prepare("DELETE FROM acccomments WHERE commentID = :commentID AND userID = :userID LIMIT 1");
                $comment->execute([':commentID' => $comment_id, ':userID' => $user_id]);
            }

            $comment = $this->connection->prepare("DELETE FROM acccomments WHERE commentID = :commentID LIMIT 1");
            $comment->execute([":commentID" => $comment_id]);

            return "1";
        }
    }
?>