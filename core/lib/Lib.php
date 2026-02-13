<?php
require_once __DIR__."/Database.php";

class Lib {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }
    
    public function getDifficulty(int $diff, int $auto, int $demon): string {
        if ($auto != 0) return "Auto";
        if ($demon != 0) return "Demon";
        
        $difficulties = [
            0 => "N/A",
            10 => "Easy",
            20 => "Normal",
            30 => "Hard",
            40 => "Harder",
            50 => "Insane"
        ];
        
        return $difficulties[$diff] ?? "Unknown";
    }

    public function demonFilter(int $demonRating): array {
        $ratings = [
            1 => ["demon" => 3, "name" => "Easy"],
            2 => ["demon" => 4, "name" => "Medium"],
            3 => ["demon" => 0, "name" => "Hard"],
            4 => ["demon" => 5, "name" => "Insane"],
            5 => ["demon" => 6, "name" => "Extreme"]
        ];

        return $ratings[$demonRating] ?? ["demon" => $demonRating, "name" => "Unknown"];
    }
    
    public function makeTime(int $timestamp): string {
        $interval = time() - $timestamp;

        $units = [
            31536000 => "years",
            2678400 => "months",
            604800 => "weeks",
            86400 => "days",
            3600 => "hours",
            60 => "minutes",
            1 => "seconds"
        ];

        foreach ($units as $seconds => $unit) {
            if ($interval >= $seconds) {
                return round($interval / $seconds) . " " . $unit;
            }
        }
        
        return "just now";
    }
    
    public function getAccountName(int $accountId): string {
        if ($accountId <= 0) {
            return "-1";
        }

        try {
            $userName = $this->db->fetchColumn(
                "SELECT userName FROM accounts WHERE accountID = ?",
                [$accountId]
            );
            
            return $userName ?: "-1";
            
        } catch (Exception $e) {
            error_log("Error getting account name: " . $e->getMessage());
            return "-1";
        }
    }

    public function randomString(int $length = 6): string {
        try {
            $randomBytes = openssl_random_pseudo_bytes(ceil($length / 2));
            
            if ($randomBytes === false) {
                throw new RuntimeException("OpenSSL random bytes failed");
            }
            
            return substr(bin2hex($randomBytes), 0, $length);
            
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

    public function getAccountsWithPermission(string $permission): array {
        try {
            $roles = $this->db->fetchAll(
                "SELECT roleID FROM roles WHERE {$permission} = 1 ORDER BY priority DESC"
            );

            $accountList = [];

            foreach ($roles as $role) {
                $accounts = $this->db->fetchAll(
                    "SELECT accountID FROM roleassign WHERE roleID = ?",
                    [$role["roleID"]]
                );

                foreach ($accounts as $user) {
                    $accountList[] = (int)$user["accountID"];
                }
            }

            return array_unique($accountList);

        } catch (Exception $e) {
            error_log("Error getting accounts with permission: " . $e->getMessage());
            return [];
        }
    }
    
    public function songReupload(string $url): int|string {
        require_once __DIR__ . "/../../core/lib/exploitPatch.php";
        
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, "http")) {
            return "-2";
        }

        try {
            $song = $this->prepareSongUrl($url);

            $existingSong = $this->db->fetchOne(
                "SELECT ID FROM songs WHERE download = ?",
                [$song]
            );

            if ($existingSong) {
                return $existingSong['ID'];
            }

            $info = $this->getFileInfo($song);
            if (!$info || !str_starts_with($info['type'], "audio/")) {
                return "-4";
            }

            $name = $this->prepareSongName($song);
            $size = round($info['size'] / 1024 / 1024, 2);
            $nextId = $this->getNextSongId();

            $this->db->insert('songs', [
                'ID' => $nextId,
                'name' => $name,
                'authorID' => 9,
                'authorName' => "Reupload",
                'size' => $size,
                'download' => $song,
                'hash' => ""
            ]);

            return $nextId;

        } catch (Exception $e) {
            error_log("Error in songReupload: " . $e->getMessage());
            return "-3";
        }
    }

    private function prepareSongUrl(string $url): string {
        $url = str_replace("www.dropbox.com", "dl.dropboxusercontent.com", $url);
        $url = str_replace(["?dl=0", "?dl=1"], "", $url);
        return trim($url);
    }

    private function prepareSongName(string $url): string {
        require_once __DIR__ . "/../../core/lib/exploitPatch.php";
        
        $name = ExploitPatch::remove(urldecode(
            str_replace([".mp3", ".webm", ".mp4", ".wav"], "", basename($url))
        ));
        
        if (str_contains($name, "?rlkey=")) {
            $name = explode("?", $name)[0];
        }
        
        $name = str_replace("_", " ", $name);
        $name = ucwords($name);
        
        return $this->sanitizeInput($name);
    }

    private function getFileInfo(string $url): ?array {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 5
            ]
        ]);

        try {
            $headers = get_headers($url, true, $context);
            
            if (!$headers) {
                return null;
            }

            return [
                'size' => isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : 0,
                'type' => $headers['Content-Type'] ?? 'unknown'
            ];
        } catch (Exception $e) {
            error_log("Error getting file info: " . $e->getMessage());
            return null;
        }
    }

    private function getNextSongId(): int {
        try {
            $maxId = $this->db->fetchColumn(
                "SELECT MAX(ID) FROM songs WHERE ID <= 10000001"
            );
            
            return ($maxId ?? 0) + 1;
        } catch (Exception $e) {
            error_log("Error getting next song ID: " . $e->getMessage());
            return 1;
        }
    }

    public function sanitizeInput(string $input): string {
        return htmlspecialchars(
            stripslashes(trim($input)), 
            ENT_QUOTES | ENT_HTML5, 
            'UTF-8'
        );
    }
}