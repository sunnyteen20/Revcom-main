<?php
session_start();
include "db.php";

$signup_message = "";
$signup_class = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Basic Validation
    if(empty($username) || empty($name) || empty($email) || empty($password)){
        $signup_message = "All fields are required";
        $signup_class = "warning";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signup_message = "Invalid email format";
        $signup_class = "warning";
    } else {
        // 2. Check if username or email already exists
        $check_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_user->bind_param("s", $username);
        $check_user->execute();
        $check_user->store_result();

        $check_email = $conn->prepare("SELECT sign_in_id FROM tbl_sign_in WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();

        if($check_user->num_rows > 0 || $check_email->num_rows > 0){
            $signup_message = "Username or Email already exists";
            $signup_class = "warning";
        } else {
            // 3. Insert into tbl_sign_in then users (to satisfy FK)
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt_sign = $conn->prepare("INSERT INTO tbl_sign_in (email, password, is_admin, created_at) VALUES (?, ?, 0, NOW())");
            $stmt_sign->bind_param("ss", $email, $hashed);

            if($stmt_sign->execute()){
                $sign_in_id = $conn->insert_id;

                $stmt_user = $conn->prepare("INSERT INTO users(username, name, email, password, sign_in_id) VALUES(?,?,?,?,?)");
                $stmt_user->bind_param("ssssi", $username, $name, $email, $hashed, $sign_in_id);

                if($stmt_user->execute()){
                    $signup_message = "Sign Up successful! You can now Sign In.";
                    $signup_class = "success";
                } else {
                    // rollback sign_in on failure
                    $conn->query("DELETE FROM tbl_sign_in WHERE sign_in_id = " . (int)$sign_in_id);
                    $signup_message = "Sign Up failed. Try again.";
                    $signup_class = "warning";
                }
                $stmt_user->close();
            } else {
                $signup_message = "Sign Up failed. Try again.";
                $signup_class = "warning";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In / Sign Up Form</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,800" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container" id="container">

    <div class="form-container sign-up-container">
        <form action="" method="POST">
            <h1>Create Account</h1>
            <input type="text" placeholder="Username" name="username" required
                   pattern="[A-Za-z0-9_]{3,20}" 
                   title="3-20 characters: letters, numbers, underscores"/>
            <input type="text" placeholder="Name" name="name" required
                   pattern="[A-Za-z\s]{3,50}" title="3-50 letters and spaces only"/>
            <input type="email" placeholder="Email" name="email" required/>
            <input type="password" placeholder="Password" name="password" required/>

            <?php if(!empty($signup_message)): ?>
                <div class="message <?= $signup_class ?>"><?= $signup_message ?></div>
            <?php endif; ?>

            <button type="submit">Sign Up</button>
        </form>
        <a href="index.php">Back to Sign In</a>
    </div>

    <div class="form-container sign-in-container">
        <form action="login.php" method="POST">
            <h1>Sign In</h1>
            <input type="email" placeholder="Email" name="email" required/>
            <input type="password" placeholder="Password" name="password" required/>
            <a href="forgot_password.php">Forgot your password?</a>
            <button type="submit">Sign In</button>
        </form>
    </div>

    <div class="overlay-container">
        <div class="overlay">
            <div class="overlay-panel overlay-left">
                <h2>Welcome Back to REVCOM!</h2>
                <p>Sign in to discover the latest movies and share your reviews.</p>
                <button class="ghost" id="signIn">Sign In</button>
            </div>
            <div class="overlay-panel overlay-right">
                <h2>Join REVCOM Today!</h2>
                <p>Create your account and start rating and reviewing movies now.</p>
                <button class="ghost" id="signUp">Sign Up</button>
            </div>
        </div>
    </div>

</div>

<footer>
    <p><a>@2026 REVCOM</a></p>
</footer>

<script src="script.js"></script>
</body>
</html>