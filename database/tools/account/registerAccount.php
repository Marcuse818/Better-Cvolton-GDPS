<?php
    require_once __DIR__ . "/../../../config/security.php";
	
    require_once __DIR__ . "/../../../core/lib/Database.php";
    require_once __DIR__ . "/../../../core/lib/exploitPatch.php";
    require_once __DIR__ . "/../../../core/lib/generatePass.php";
    
    $new_con = new Database();
    $db = $new_con->open_connection();
    
    if(!isset(SecurityConfig::$preactivateAccounts)) SecurityConfig::$preactivateAccounts = true;

    if(!empty($_POST["username"]) && !empty($_POST["email"]) && !empty($_POST["repeatemail"]) && !empty($_POST["password"]) && !empty($_POST["repeatpassword"]))
    {
	    $username = ExploitPatch::remove($_POST["username"]);
	    $password = ExploitPatch::remove($_POST["password"]);
	    $repeat_password = ExploitPatch::remove($_POST["repeatpassword"]);
	    $email = ExploitPatch::remove($_POST["email"]);
	    $repeat_email = ExploitPatch::remove($_POST["repeatemail"]);
	    
	    if(strlen($username) < 3)
	    {
		    echo 'Username should be more than 3 characters.<br><br>
			<form action="/" method="post">
				<strong>Username</strong> <input class="input" type="text" name="username" maxlength=15><br>
				<strong>Password</strong> <input class="input" type="password" name="password" maxlength=20><br>
				<strong>Repeat Password</strong> <input class="input" type="password" name="repeatpassword" maxlength=20><br>
				<strong>Email</strong> <input class="input" type="email" name="email" maxlength=50><br>
				<strong>Repeat Email</strong> <input class="input" type="email" name="repeatemail" maxlength=50><br>
				<br><input class="button" type="submit" value="Register">
			</form>';
	    }
	    elseif(strlen($password) < 6)
	    {
		    echo 'Password should be more than 6 characters.<br><br>
			<form action="/" method="post">
				<strong>Username</strong> <input class="input" type="text" name="username" maxlength=15><br>
				<strong>Password</strong> <input class="input" type="password" name="password" maxlength=20><br>
				<strong>Repeat Password</strong> <input class="input" type="password" name="repeatpassword" maxlength=20><br>
				<strong>Email</strong> <input class="input" type="email" name="email" maxlength=50><br>
				<strong>Repeat Email</strong> <input class="input" type="email" name="repeatemail" maxlength=50><br>
				<br><input class="button" type="submit" value="Register">
			</form>';
	    }
	    else
	    {
		    $query = $db->prepare("SELECT count(*) FROM accounts WHERE userName LIKE :userName");
		    $query->execute([':userName' => $username]);
		    $registred_users = $query->fetchColumn();
		    
		    if($registred_users > 0)
		    {
				echo 'Username already taken.<br><br>
				<form action="/" method="post">
					<strong>Username</strong> <input class="input" type="text" name="username" maxlength=15><br>
					<strong>Password</strong> <input class="input" type="password" name="password" maxlength=20><br>
					<strong>Repeat Password</strong> <input class="input" type="password" name="repeatpassword" maxlength=20><br>
					<strong>Email</strong> <input class="input" type="email" name="email" maxlength=50><br>
					<strong>Repeat Email</strong> <input class="input" type="email" name="repeatemail" maxlength=50><br>
					<br><input class="button" type="submit" value="Register">
				</form>';
		    }
		    else
		    {
			    if ($password != $repeat_password)
			    {
				    echo 'Passwords do not match.<br><br>
					<form action="/" method="post">
						<strong>Username</strong> <input class="input" type="text" name="username" maxlength=15><br>
						<strong>Password</strong> <input class="input" type="password" name="password" maxlength=20><br>
						<strong>Repeat Password</strong> <input class="input" type="password" name="repeatpassword" maxlength=20><br>
						<strong>Email</strong> <input class="input" type="email" name="email" maxlength=50><br>
						<strong>Repeat Email</strong> <input class="input" type="email" name="repeatemail" maxlength=50><br>
						<br><input class="button" type="submit" value="Register">
					</form>';
			    }
			    elseif ($email != $repeat_email)
			    {
				    echo 'Emails do not match.<br><br>
					<form action="/" method="post">
						<strong>Username</strong> <input class="input" type="text" name="username" maxlength=15><br>
						<strong>Password</strong> <input class="input" type="password" name="password" maxlength=20><br>
						<strong>Repeat Password</strong> <input class="input" type="password" name="repeatpassword" maxlength=20><br>
						<strong>Email</strong> <input class="input" type="email" name="email" maxlength=50><br>
						<strong>Repeat Email</strong> <input class="input" type="email" name="repeatemail" maxlength=50><br>
						<br><input class="button" type="submit" value="Register">
					</form>';
			    }
			    else
			    {
				    $hashpass = password_hash($password, PASSWORD_DEFAULT);
				    $query2 = $db->prepare("INSERT INTO accounts (userName, password, email, registerDate, isActive, gjp2) VALUES (:userName, :password, :email, :time, :isActive, :gjp2)");
				    $query2->execute([':userName' => $username, ':password' => $hashpass, ':email' => $email,':time' => time(), ':isActive' => SecurityConfig::$preactivateAccounts ? 1 : 0, ':gjp2' => GeneratePass::GJP2hash($password)]);
				    
				    $activationInfo = SecurityConfig::$preactivateAccounts ? "No e-mail verification required, you can login." : "<a href='activateAccount.php'>Click here to activate it.</a>";
				    
				    echo "Account registred. ${activationInfo} <a href='/'>Go back to tools</a>";
			    }
		    }
	    }
    }
    else
    {
	    echo '<form action="/" method="post">
			<strong>Username</strong> <input class="input" type="text" name="username" maxlength=15><br>
			<strong>Password</strong> <input class="input" type="password" name="password" maxlength=20><br>
			<strong>Repeat Password</strong> <input class="input" type="password" name="repeatpassword" maxlength=20><br>
			<strong>Email</strong> <input class="input" type="email" name="email" maxlength=50><br>
			<strong>Repeat Email</strong> <input class="input" type="email" name="repeatemail" maxlength=50><br>
			<br><input class="button" type="submit" value="Register">
		</form>';
    }
?>
