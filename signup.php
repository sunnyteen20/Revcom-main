<?php
session_start();
include "db.php";

$message = "";
$message_class = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if(empty($username) || empty($name) || empty($email) || empty($password)){
        $message = "All fields are required";
        $message_class = "warning";
    } else {
        // Ensure username is unique in users and email is unique in tbl_sign_in
        $check_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_user->bind_param("s", $username);
        $check_user->execute();
        $check_user->store_result();

        $check_email = $conn->prepare("SELECT sign_in_id FROM tbl_sign_in WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();

        if($check_user->num_rows > 0 || $check_email->num_rows > 0){
            $message = "Username or Email already exists";
            $message_class = "warning";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert into tbl_sign_in first
            $stmt_sign = $conn->prepare("INSERT INTO tbl_sign_in (email, password, is_admin, created_at) VALUES (?, ?, 0, NOW())");
            $stmt_sign->bind_param("ss", $email, $hashed);

            if($stmt_sign->execute()){
                $sign_in_id = $conn->insert_id;

                // Insert profile row into users and link sign_in_id
                $stmt_user = $conn->prepare("INSERT INTO users (username, name, email, password, sign_in_id) VALUES (?, ?, ?, ?, ?)");
                $stmt_user->bind_param("ssssi", $username, $name, $email, $hashed, $sign_in_id);

                if($stmt_user->execute()){
                    $message = "Sign Up successful! You can now Sign In.";
                    $message_class = "success";
                } else {
                    // rollback sign_in entry on failure to keep consistency
                    $conn->query("DELETE FROM tbl_sign_in WHERE sign_in_id = " . (int)$sign_in_id);
                    $message = "Sign Up failed. Try again.";
                    $message_class = "warning";
                }
                $stmt_user->close();
            } else {
                $message = "Sign Up failed. Try again.";
                $message_class = "warning";
            }

            $stmt_sign->close();
        }
        $check_user->close();
        $check_email->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - REVCOM</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,800" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container" id="container">
    <div class="form-container sign-up-container">
        <form action="signup.php" method="POST">
            <h1>Create Account</h1>
            <input type="text" placeholder="Username" name="username" required
                   pattern="[A-Za-z0-9_]{3,20}" 
                   title="3-20 characters: letters, numbers, underscores"/>
            <input type="text" placeholder="Name" name="name" required
                   pattern="[A-Za-z\s]{3,50}" title="3-50 letters and spaces only"/>
            <input type="email" placeholder="Email" name="email" required/>
            <input type="password" placeholder="Password" name="password" required/>

            <?php if(!empty($message)): ?>
                <div class="message <?= $message_class ?>"><?= $message ?></div>
            <?php endif; ?>

            <button type="submit">Sign Up</button>
        </form>
        <a href="index.php">Back to Sign In</a>
    </div>
</div>
</body>
</html>