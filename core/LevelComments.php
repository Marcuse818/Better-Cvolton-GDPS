<?php
    require_once __DIR__."/Main.php";
    
    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/Lib.php";

    interface LevelCommentsInterface {
        public function get_data(int $user_id, int $level_id, int $page): string;
        public function delete(int $account_id, int $user_id, int $comment_id, int $permission): string;
    }

    class LevelComments implements LevelCommentsInterface {
        private $connection, $lib, $main;

        public $upload_date, $count, $mode, $percent, $game_version, $binary_version;
        private $mode_column, $filter_column, $filter_to_filter, $display_level_id, $filter_id, $user_list_join, $user_list_where;

        public function __construct() {
            $database = new Database();
            $this->connection = $database->open_connection();
            $this->lib = new Lib();
            $this->main = new Main();
            $this->upload_date = time();
        }

        public function get_data(int $user_id, int $level_id, int $page): string {
            $page *= $this->count;
            $users = array();
            $comment_string = "";
            $user_string = "";

            $this->mode_column = ($this->mode == 0) ? "timestamp" : "likes";

            if ($level_id) {
                $this->filter_id = $level_id;
                $this->filter_column = 'levelID';
                $this->filter_to_filter = '';
                $this->display_level_id = false;
                
                $this->user_list_join = '';
                $this->user_list_where = '';
            }
            
            if ($user_id) {
                $this->filter_id = $user_id;
                $this->filter_column = "userID";
                $this->filter_to_filter = "comments.";
                $this->display_level_id = true;
                
                $this->user_list_join = 'INNER JOIN levels ON comments.levelID = levels.levelID';
                $this->user_list_where = 'AND levels.unlisted = 0';
            }

            $comment_count = $this->connection->prepare("SELECT count(*) FROM comments $this->user_list_join WHERE $this->filter_to_filter$this->filter_column = :filterID $this->user_list_where");
            $comment_count->execute([":filterID" => $this->filter_id]);
            $comment_count = $comment_count->fetchColumn();
            
            if ($comment_count == 0) return "-2";

            $comments = $this->connection->prepare("SELECT comments.levelID, comments.commentID, comments.timestamp, comments.comment, comments.userID, comments.likes, comments.isSpam, comments.percent, users.userName, users.icon, users.color1, users.color2, users.iconType, users.special, users.extID FROM comments LEFT JOIN users ON comments.userID = users.userID $this->user_list_join WHERE comments.$this->filter_column = :filterID $this->user_list_where ORDER BY comments.$this->mode_column DESC LIMIT $this->count OFFSET $page");
            $comments->execute([":filterID"=> $this->filter_id]);
            $comments_visible = $comments->rowCount();
            $comments = $comments->fetchAll();
            
            foreach ($comments as $comment) {
                if ($comment['commentID'] != 0) {
                    $this->upload_date = $this->lib->make_time($comment['timestamp']);
                    $comment_text = ($this->game_version < 20) ? base64_decode($comment['comment']) : $comment['comment'];  

                    if ($this->display_level_id) $comment_string .=  "1~".$comment["levelID"]."~";

                    $comment_string .= "2~".$comment_text."~3~".$comment["userID"]."~4~".$comment["likes"]."~5~0~7~".$comment["isSpam"]."~9~".$this->upload_date."~6~".$comment["commentID"]."~10~".$comment["percent"];
                    
                    if ($comment["extID"]) {
                        $ext_id = is_numeric($comment['extID']) ? $comment['extID'] : 0;

                        if ($this->binary_version > 31) {
                            $badge = $this->main->getRolePermission($ext_id, 'modBadgeLevel');
                            $color_string = $badge > 0 ? "~12~".$this->main->getRolePermission($ext_id, "commentColor") : "";
                            $comment_string .= "~11~$badge$color_string:1~".$comment["userName"]."~7~1~9~".$comment["icon"]."~10~".$comment["color1"]."~11~".$comment["color2"]."~14~".$comment["iconType"]."~15~".$comment["special"]."~16~".$ext_id;
                        }

                        if (!in_array($comment['userID'], $users)) {
                            $users[] = $comment['userID'];
                            $user_string .= $comment["userID"].":".$comment["userName"].":".$ext_id."|";
                        }

                        $comment_string .= "|";
                    }
                }
            }

            $comment_string = substr($comment_string, 0, -1);
                    
            if ($this->binary_version < 32) {
                $user_string = substr($user_string, 0, -1);
                return $comment_string . "#" . $user_string . "#" . $comment_count . ":" . $page . ":" . $comments_visible;
            }

            return $comment_string . "#" . $comment_count . ":" . $page . ":" . $comments_visible;
        }

        public function delete(int $account_id, int $user_id, int $comment_id, int $permission): string {
            $comment = $this->connection->prepare("DELETE FROM comments WHERE commentID = :commentID AND userID = :userID LIMIT 1");
            $comment->execute([':userID' => $user_id, ':commentID' => $comment_id]);
            $comment = $comment->rowCount();

            if ($comment == 0) {
                $comment = $this->connection->prepare("SELECT users.extID FROM comments INNER JOIN levels ON levels.levelID = comments.levelID INNER JOIN users ON levels.userID = users.userID WHERE commentID = :commentID");
                $comment->execute([":commentID"=> $comment_id]);
                $comment = $comment->fetchColumn();

                if ($comment == $account_id || $permission) {
                    $comment = $this->connection->prepare("DELETE FROM comments WHERE commentID = :commentID LIMIT 1");
                    $comment->execute([':commetID' => $comment_id]);
                }
            }

            return "1";
        }

        public function upload(int $account_id, int $user_id, string $user_name, string $comment, int $level_id): string {
            if ($account_id == '' || $comment == '') return "-1";

            $comment_upload = $this->connection->prepare("INSERT INTO comments (userName, comment, levelID, userID, timeStamp, percent) VALUES (:userName, :comment, :levelID, :userID, :uploadDate, :percent)");
            $comment_upload->execute([":userName" => $user_name, ":comment" => $comment, ":levelID" => $level_id, ":userID" => $user_id, ":uploadDate" => $this->upload_date, ':percent' => $this->percent]);

            if (!is_numeric($account_id)) return "1";

            if ($this->percent != 0) {
                $comment_result = $this->connection->prepare("SELECT percent FROM levelscores WHERE accountID = :accountID AND levelID = :levelID");
                $comment_result->execute([":accountID" => $account_id, ":levelID" => $level_id]);
                $comment_result = $comment_result->fetchColumn();

                if ($comment_result < $this->percent) {
                    $comment_update = $this->connection->prepare("UPDATE levelscores SET percent = :percent, uploadDate = :uploadDate WHERE accountID = :accountID AND levelID = :levelID");
                    $comment_update->execute([":percent" => $this->percent, ":uploadDate" => $this->upload_date, ":accountID" => $account_id, ":levelID" => $level_id]);
                }
            } 

            return '1';
        }
    }
?>