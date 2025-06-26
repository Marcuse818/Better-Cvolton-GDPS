<?php
    chdir(dirname(__FILE__));
    
    echo "Setting user names to account names<br>";
    
    ob_flush();
    flush();
    set_time_limit(0);
    
    require_once "../../../core/lib/Database.php";
    
    $new_con = new Database();
    $db = $new_con->open_connection();
    
    $query = $db->prepare("UPDATE users
	    INNER JOIN accounts ON accounts.accountID = users.extID
	    SET users.userName = accounts.userName
	    WHERE users.extID REGEXP '^-?[0-9]+$'
	    AND LENGTH(accounts.userName) <= 69");
    $query->execute();

    $query = $db->prepare("UPDATE users
	    INNER JOIN accounts ON accounts.accountID = users.extID
	    SET users.userName = 'Invalid Username'
	    WHERE users.extID REGEXP '^-?[0-9]+$'
	    AND LENGTH(accounts.userName) > 69");
    $query->execute();
    
    echo "Done<hr>";
    
    $new_con->close_connection($db);
?>