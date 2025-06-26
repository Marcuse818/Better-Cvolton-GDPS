<?php
    chdir(dirname(__FILE__));
    set_time_limit(0);
    
    require_once "../../../core/lib/Database.php";
    
    $new_con = new Database();
    $db = $new_con->open_connection();
    
    echo "Calculating levelsCount for songs";
    
    $query = $db->prepare("UPDATE songs
	    LEFT JOIN
	    (
	        SELECT count(*) AS levelsCount, songID FROM levels GROUP BY songID
	    ) calculated
	    ON calculated.songID = songs.ID
	    SET songs.levelsCount = IFNULL(calculated.levelsCount, 0)");
    $query->execute();
    
    echo "<hr>";
    
    $new_con->close_connection($db);
?>
