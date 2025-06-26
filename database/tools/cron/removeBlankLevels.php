<?php
    require_once "../../../core/lib/Database.php";
    
    $new_con = new Database();
    $db = $new_con->open_connection();
   
    $query = $db->prepare("DELETE FROM users WHERE extID = ''");
    $query->execute();
    
    $query = $db->prepare("DELETE FROM songs WHERE download = ''");
    $query->execute();
    
    echo "Deleted invalid users and songs.<br>";
    
    ob_flush();
    flush();
    
    $query = $db->prepare("UPDATE levels SET password = 0 WHERE password = 2");
    $query->execute();
    
    echo "Fixed reuploaded levels with invalid passwords.<br>";
    
    ob_flush();
    flush();
    
    $query = $db->prepare("DELETE FROM songs WHERE download = '10' OR download LIKE 'file:%'");
    $query->execute();
    
    echo "Removed songs with nonsensical URLs.<br>";
    echo "<hr>";
    
    $new_con->close_connection($db);
    ob_flush();
    flush();
?>