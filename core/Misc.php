<?php
    require_once __DIR__."/Main.php";

    require_once __DIR__."/lib/Database.php";
    require_once __DIR__."/lib/SongReupload.php";

    require_once __DIR__."/../config/topArtists.php";

    interface Miscs {
        public function like(int $itemID, int $type, int $like, $hostname): string;
        public function getUrl(): string;
        public function getSong(int $songID): string;
        public function getArtists(int $page, string $url, string $request): string;
    }

    class Misc extends ConfigArtists implements Miscs {
        protected $connection;
        protected $Main, $SongReupload, $Database;


        public function __construct() {
            $this->Main = new Main();
            $this->Database = new Database();
            $this->SongReupload = new SongReupload();

            $this->connection = $this->Database->open_connection();
        }

        public function like(int $itemID, int $type, int $like, $hostname): string {
            $item = $this->connection->prepare("SELECT count(*) FROM actions_likes WHERE itemID = :itemID AND type = :type AND ip = INET6_ATON(:ip)");
            $item->execute([":type" => $type, ":itemID" => $itemID, ":ip" => $hostname]);

            if ($item->fetchColumn() > 2) return -1;

            $item = $this->connection->prepare("INSERT INTO actions_likes (itemID, type, isLike, ip) VALUES (:itemID, :type, :isLike, INET6_ATON(:ip))");
            $item->execute([":itemID" => $itemID, ":type" => $type, ":isLike" => $like, ":ip" => $hostname]);

            switch($type) {
                case 1:
                    $table = "levels";
                    $column = "levelID";
                    break;

                case 2:
                    $table = "comments";
                    $column = "commentID";
                    break;

                case 3:
                    $table = "acccomments";
                    $column = "commentID";
                    break;

                case 4:
                    $table = "lists";
                    $column = "listID";
                    break;
            }

            $item = $this->connection->prepare("SELECT likes FROM $table WHERE $column = :itemID LIMIT 1");
            $item->execute([":itemID" => $itemID]);

            $sign = ($like == 1) ? "+" : "-";

            $item = $this->connection->prepare("UPDATE $table SET likes = likes $sign 1 WHERE $column = :itemID");
            $item->execute([":itemID" => $itemID]);

            return "1";
        }

        public function getUrl(): string {
            $url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
            
            return dirname($url . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
        }

        public function getSong(int $songID): string {
            if (empty($songID)) return -1;
            
            $song = $this->connection->prepare("SELECT ID, name, authorID, authorName, size, isDisabled, download FROM songs WHERE ID = :songID LIMIT 1");
            $song->execute([":songID" => $songID]);
            
            if ($song->rowCount() == 0)
            {
                $url = "http://www.boomlings.com/database/getGJLevels21.php";
                $songData = array(
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
			        'song' => $songID,
			        'customSong' => '1',
			        'secret' => 'Wmfd2893gb7'
                );

                $songCurl = curl_init($url);
                curl_setopt($songCurl, CURLOPT_RETURNTRANSFER, true);
		        curl_setopt($songCurl, CURLOPT_POSTFIELDS, $songData);
		        curl_setopt($songCurl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                $songResult = curl_exec($songCurl);
                curl_close($songCurl);
                   
                if (substr_count($songResult, "1~|~".$songID."~|~2") != 0)
                {
                    $this->song_result = explode('#', $songResult)[2];
                }
                else
                {
                    $songCurl = curl_init();
                    curl_setopt($songCurl, CURLOPT_URL, "https://www.newgrounds.com/audio/listen/".$songID); 
			        curl_setopt($songCurl, CURLOPT_RETURNTRANSFER, 1);
                    $songInfo = curl_exec($songCurl);
                    curl_close($songCurl);
                        
                    if (empty(explode('"url":"', $songInfo)[1])) return -1;
                        
                    $songAuthor = explode('","', explode('artist":"', $songInfo)[1])[0];
                    $songName = explode('<title>', explode('</title>', $songInfo)[0])[1];
                    $songSize = $this->Main->format_bytes(explode(',"', explode('"filesize":', $songInfo)[1])[0]);
                    $songUrl = explode('","', explode('"url":"', $songInfo)[1])[0];
                    $songUrl = str_replace("\/", "/", $songUrl);
                        
                    if ($songUrl == "") return -1;

                    $songResult = "1~|~".$songID."~|~2~|~".$songName."~|~3~|~1234~|~4~|~".$songAuthor."~|~5~|~".$songSize."~|~6~|~~|~10~|~".$songUrl."~|~7~|~~|~8~|~1";
                }

                $this->SongReupload->reupload($songResult);
                return $songResult;
            }
            else
            {
                $song = $song->fetch();
                if ($song['isDisabled'] == "2") return -2;

                $songDownload = $song['download'];
                if (strpos($songDownload, ":") != false) $songDownload = urlencode($songDownload);

                return "1~|~".$song["ID"]."~|~2~|~".$song["name"]."~|~3~|~".$song["authorID"]."~|~4~|~".$song["authorName"]."~|~5~|~".$song["size"]."~|~6~|~~|~10~|~".$songDownload."~|~7~|~~|~8~|~0";
            }
        }

        public function getArtists(int $page, string $url, string $request): string {
            $page = $page * 2;

            if ($this->redirect == 1) 
            {
                parse_str($request, $post);

                $artistsCurl = curl_init($url);
                curl_setopt($artistsCurl, CURLOPT_POSTFIELDS, $post);
                curl_setopt($artistsCurl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($artistsCurl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                $artistResult = curl_exec($artistsCurl);
                curl_close($artistsCurl);

                return $artistResult;
            }   
            else
            {
                $top_artist = $this->connection->prepare("SELECT authorName, download FROM songs WHERE (authorName NOT LIKE '%Reupload%' AND authorName NOT LIKE 'unknown') GROUP BY authorName ORDER BY COUNT(authorName) DESC LIMIT 20 OFFSET $page");
                $top_artist->execute();
                $artistResult = $top_artist->fetchAll();

                $top_artist_count = $this->connection->prepare("SELECT count(DISTINCT(authorName)) FROM songs WHERE (authorName NOT LIKE '%Reupload%' AND authorName NOT LIKE 'unknown'");
                $top_artist_count->execute();
                $top_artist_count = $top_artist_count->fetchColumn();

                foreach($artistResult as $artist) 
                {
                    $string .= "4:$artist[0]";

                    if (substr($artist[1], 0, 26) == "https://api.soundcloud.com") 
                    {
                        $string .= (strpos(urlencode($artist[0]), "+") != false) ? ":7:../redirect?q=https%3A%2F%2Fsoundcloud.com%2Fsearch%2Fpeople?q=$artist[0]" : ":7:../redirect?q=https%3A%2F%2Fsoundcloud.com%2F$artist[0]";
                    }
                }
                $string .= "|";
            }
            
            $string = rtrim($string, "|");
            $string .= "#$top_artist_count:$page:20";
            
            return $string;
        }
    }