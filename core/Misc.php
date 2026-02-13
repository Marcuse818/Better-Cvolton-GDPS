<?php
require_once __DIR__."/Main.php";
require_once __DIR__."/lib/Database.php";
require_once __DIR__."/lib/SongReupload.php";
require_once __DIR__."/../config/topArtists.php";

interface MiscInterface {
    public function like(int $itemId, int $type, int $like, string $hostname): string;
    public function getUrl(): string;
    public function getSong(int $songId): string;
    public function getArtists(int $page, string $url, string $request): string;
}

class Misc extends ConfigArtists implements MiscInterface {
    private Database $db;
    private Main $main;
    private SongReupload $songReupload;

    public function __construct() {
        $this->main = new Main();
        $this->db = new Database();
        $this->songReupload = new SongReupload();
    }

    public function like(int $itemId, int $type, int $like, string $hostname): string {
        $likeCount = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM actions_likes WHERE itemID = ? AND type = ? AND ip = INET6_ATON(?)",
            [$itemId, $type, $hostname]
        );

        if ($likeCount > 2) return "-1";

        $this->db->insert('actions_likes', [
            'itemID' => $itemId,
            'type' => $type,
            'isLike' => $like,
            'ip' => $hostname
        ]);

        switch ($type) {
            case 1: $table = "levels"; $column = "levelID"; break;
            case 2: $table = "comments"; $column = "commentID"; break;
            case 3: $table = "acccomments"; $column = "commentID"; break;
            case 4: $table = "lists"; $column = "listID"; break;
            default: return "-1";
        }

        $sign = ($like == 1) ? "+" : "-";
        
        $this->db->execute(
            "UPDATE $table SET likes = likes $sign 1 WHERE $column = ?",
            [$itemId]
        );

        return "1";
    }

    public function getUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) 
            ? "https" 
            : "http";
        
        return dirname($protocol . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
    }

    public function getSong(int $songId): string {
        if (empty($songId)) return "-1";
        
        $song = $this->db->fetchOne(
            "SELECT ID, name, authorID, authorName, size, isDisabled, download 
             FROM songs 
             WHERE ID = ?",
            [$songId]
        );
        
        if (!$song) {
            return $this->fetchSongFromExternal($songId);
        }
        
        if ($song['isDisabled'] == "2") return "-2";

        $songDownload = $song['download'];
        if (strpos($songDownload, ":") !== false) {
            $songDownload = urlencode($songDownload);
        }

        return sprintf(
            "1~|~%d~|~2~|~%s~|~3~|~%d~|~4~|~%s~|~5~|~%s~|~6~|~~|~10~|~%s~|~7~|~~|~8~|~0",
            $song["ID"],
            $song["name"],
            $song["authorID"],
            $song["authorName"],
            $song["size"],
            $songDownload
        );
    }

    private function fetchSongFromExternal(int $songId): string {
        $url = "http://www.boomlings.com/database/getGJLevels21.php";
        $songData = [
            'gameVersion' => '21',
            'binaryVersion' => '33',
            'gdw' => '0',
            'type' => '2',
            'str' => '',
            'diff' => '-',
            'len' => '-',
            'page' => '0',
            'total' => '9999',
            'uncompleted' => '0',
            'onlyCompleted' => '0',
            'featured' => '0',
            'original' => '0',
            'twoPlayer' => '0',
            'coins' => '0',
            'epic' => '0',
            'song' => $songId,
            'customSong' => '1',
            'secret' => 'Wmfd2893gb7'
        ];

        $songCurl = curl_init($url);
        curl_setopt($songCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($songCurl, CURLOPT_POSTFIELDS, $songData);
        curl_setopt($songCurl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        $songResult = curl_exec($songCurl);
        curl_close($songCurl);
           
        if (substr_count($songResult, "1~|~" . $songId . "~|~2") != 0) {
            $this->songReupload->reupload(explode('#', $songResult)[2]);
            return explode('#', $songResult)[2];
        }
        
        return $this->fetchSongFromNewgrounds($songId);
    }

    private function fetchSongFromNewgrounds(int $songId): string {
        $songCurl = curl_init();
        curl_setopt($songCurl, CURLOPT_URL, "https://www.newgrounds.com/audio/listen/" . $songId); 
        curl_setopt($songCurl, CURLOPT_RETURNTRANSFER, 1);
        $songInfo = curl_exec($songCurl);
        curl_close($songCurl);
            
        if (empty(explode('"url":"', $songInfo)[1])) return "-1";
            
        $songAuthor = explode('","', explode('artist":"', $songInfo)[1])[0];
        $songName = explode('<title>', explode('</title>', $songInfo)[0])[1];
        $songSize = $this->main->formatBytes(explode(',"', explode('"filesize":', $songInfo)[1])[0]);
        $songUrl = explode('","', explode('"url":"', $songInfo)[1])[0];
        $songUrl = str_replace("\/", "/", $songUrl);
            
        if ($songUrl == "") return "-1";

        $songResult = sprintf(
            "1~|~%d~|~2~|~%s~|~3~|~1234~|~4~|~%s~|~5~|~%s~|~6~|~~|~10~|~%s~|~7~|~~|~8~|~1",
            $songId,
            $songName,
            $songAuthor,
            $songSize,
            $songUrl
        );

        $this->songReupload->reupload($songResult);
        return $songResult;
    }

    public function getArtists(int $page, string $url, string $request): string {
        $page *= 2;

        if ($this->redirect == 1) {
            parse_str($request, $post);

            $artistsCurl = curl_init($url);
            curl_setopt($artistsCurl, CURLOPT_POSTFIELDS, $post);
            curl_setopt($artistsCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($artistsCurl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            $artistResult = curl_exec($artistsCurl);
            curl_close($artistsCurl);

            return $artistResult;
        }   
        
        $artistResult = $this->db->fetchAll(
            "SELECT authorName, download FROM songs 
             WHERE (authorName NOT LIKE '%Reupload%' AND authorName NOT LIKE 'unknown') 
             GROUP BY authorName 
             ORDER BY COUNT(authorName) DESC 
             LIMIT 20 OFFSET ?",
            [$page]
        );

        $topArtistCount = $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT(authorName)) FROM songs 
             WHERE (authorName NOT LIKE '%Reupload%' AND authorName NOT LIKE 'unknown')"
        );

        $string = "";
        foreach ($artistResult as $artist) {
            $string .= "4:" . $artist[0];

            if (substr($artist[1], 0, 26) == "https://api.soundcloud.com") {
                $encodedName = urlencode($artist[0]);
                $searchUrl = (strpos($encodedName, "+") !== false) 
                    ? "https%3A%2F%2Fsoundcloud.com%2Fsearch%2Fpeople?q=" . $encodedName
                    : "https%3A%2F%2Fsoundcloud.com%2F" . $encodedName;
                $string .= ":7:../redirect?q=" . $searchUrl;
            }
        }
        
        $string .= "|";
        $string = rtrim($string, "|");
        $string .= "#$topArtistCount:$page:20";
        
        return $string;
    }
}