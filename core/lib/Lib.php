<?php
require_once __DIR__."/Database.php";

class Lib {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }
    
    public function get_difficulty($diff, $auto, $demon) {
        if($auto != 0) return "Auto";
        if($demon != 0) return "Demon";
        
        switch($diff) {
            case 0:
                return "N/A";
                
            case 10:
                return "Easy";
                
            case 20:
                return "Normal";
                
            case 30:
                return "Hard";
                
            case 40:
                return "Harder";
                
            case 50:
                return "Insane";
                
            default:
                return "Unknown";
        }
    }

    public function demon_filter($demon_rating) {
        $rating = ["demon" => 0, "name" => "Unknown"];
        
        switch($demon_rating) 
        {
            case 1:
                $rating["demon"] = 3;
                $rating["name"] = "Easy";
                break;

            case 2:
                $rating["demon"] = 4;
                $rating["name"] = "Medium";
                break;

            case 3:
                $rating["demon"] = 0;
                $rating["name"] = "Hard";
                break;

            case 4:
                $rating["demon"] = 5;
                $rating["name"] = "Insane";
                break;

            case 5:
                $rating["demon"] = 6;
                $rating["name"] = "Extreme";
                break;
                
            default:
                $rating["demon"] = $demon_rating;
                $rating["name"] = "Unknown";
                break;
        }

        return $rating;
    }
    
    public function make_time($delta) {
        $interval = time() - $delta;

        if ($interval < 60) return round($interval)." seconds";
        if ($interval < 3600) return round($interval / 60)." minutes";
        if ($interval < 86400) return round($interval / 3600)." hours";
        if ($interval < 604800) return round($interval / 86400)." days";
        if ($interval < 2678400) return round($interval / 604800)." weeks";
        if ($interval < 31536000) return round($interval / 2678400)." months";
        if ($interval > 31536000) return round($interval / 31536000)." years";
        
        return "just now";
    }
    
    public function getAccountName($accountID) {
        if(!is_numeric($accountID) || $accountID <= 0) {
            return "-1";
        }

        try {
            $userName = $this->db->fetch_one(
                "SELECT userName FROM accounts WHERE accountID = :id",
                [':id' => $accountID]
            );
            
            return $userName ? $userName['userName'] : "-1";
            
        } catch (Exception $e) {
            error_log("Error getting account name: " . $e->getMessage());
            return "-1";
        }
    }

    public function randomString($length = 6) {
        try {
            $randomString = openssl_random_pseudo_bytes(ceil($length / 2));
            
            if($randomString === false) {
                throw new RuntimeException("OpenSSL random bytes failed");
            }
            
            return substr(bin2hex($randomString), 0, $length);
            
        } catch (Exception $e) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';

            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[random_int(0, $charactersLength - 1)];
            }

            return $randomString;
        }
    }

    public function get_accounts_with_permission($permission) {
        try {
            $roles = $this->db->fetch_all(
                "SELECT roleID FROM roles WHERE $permission = 1 ORDER BY priority DESC"
            );

            $accountlist = [];

            foreach($roles as $role) {
                $accounts = $this->db->fetch_all(
                    "SELECT accountID FROM roleassign WHERE roleID = :roleID",
                    [':roleID' => $role["roleID"]]
                );

                foreach($accounts as $user) {
                    $accountlist[] = (int)$user["accountID"];
                }
            }

            return array_unique($accountlist);

        } catch (Exception $e) {
            error_log("Error getting accounts with permission: " . $e->getMessage());
            return [];
        }
    }
    
    public function song_reupload($url) {
        require_once __DIR__ . "/../../core/lib/exploitPatch.php";
        
        if (!filter_var($url, FILTER_VALIDATE_URL) || substr($url, 0, 4) !== "http") {
            return "-2";
        }

        try {
            $song = str_replace("www.dropbox.com", "dl.dropboxusercontent.com", $url);
            $song = str_replace(["?dl=0", "?dl=1"], "", $song);
            $song = trim($song);

            $existing_song = $this->db->fetch_one(
                "SELECT id FROM songs WHERE download = :download",
                [':download' => $song]
            );

            if ($existing_song) {
                return $existing_song['id'];
            }

            $info = $this->get_file_info($song);
            if (!$info || substr($info['type'], 0, 6) !== "audio/") {
                return "-4";
            }

            $name = $this->prepare_song_name($song);
            $author = "Reupload";
            $size = round($info['size'] / 1024 / 1024, 2);
            $hash = "";

            $next_id = $this->get_next_song_id();

            $new_id = $this->db->insert(
                "INSERT INTO songs (ID, name, authorID, authorName, size, download, hash) 
                 VALUES (:ID, :name, '9', :author, :size, :download, :hash)",
                [
                    ':ID' => $next_id,
                    ':name' => $name,
                    ':author' => $author,
                    ':size' => $size,
                    ':download' => $song,
                    ':hash' => $hash
                ]
            );

            return $new_id ?: $next_id;

        } catch (Exception $e) {
            error_log("Error in song_reupload: " . $e->getMessage());
            return "-3";
        }
    }

    private function prepare_song_name($url) {
        require_once __DIR__ . "/../../core/lib/exploitPatch.php";
        
        $name = ExploitPatch::remove(urldecode(str_replace([".mp3", ".webm", ".mp4", ".wav"], "", basename($url))));
        
        if (str_contains($name, "?rlkey=")) {
            $name = explode("?", $name)[0];
        }
        
        $name = str_replace("_", " ", $name);
        $name = ucwords($name);
        
        return $this->sanitize_input($name);
    }

    private function get_file_info($url) {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 5
            ]
        ]);

        try {
            $headers = get_headers($url, true, $context);
            
            if (!$headers) {
                return false;
            }

            return [
                'size' => isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : 0,
                'type' => isset($headers['Content-Type']) ? $headers['Content-Type'] : 'unknown'
            ];
        } catch (Exception $e) {
            error_log("Error getting file info: " . $e->getMessage());
            return false;
        }
    }

    private function get_next_song_id() {
        try {
            $max_id = $this->db->fetch_one(
                "SELECT MAX(ID) as max_id FROM songs WHERE ID <= 10000001"
            );
            
            return ($max_id['max_id'] ?? 0) + 1;
        } catch (Exception $e) {
            error_log("Error getting next song ID: " . $e->getMessage());
            return 1;
        }
    }


	public function sanitize_input(string $input): string {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $input;
    }
}
