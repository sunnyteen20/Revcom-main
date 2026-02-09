<?php
session_start();
include "db.php";

$message = "";
if(!isset($_SESSION['reset_email'])){
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if($new !== $confirm){
        $message = "Passwords do not match!";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        // Update both tables to keep them in sync
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $stmt->bind_param("ss", $hashed, $_SESSION['reset_email']);
        $stmt->execute();
        $stmt->close();
        
        $stmt2 = $conn->prepare("UPDATE tbl_sign_in SET password=? WHERE email=?");
        $stmt2->bind_param("ss", $hashed, $_SESSION['reset_email']);
        if($stmt2->execute()){
            unset($_SESSION['reset_email']);
            header("Location: index.php?success=passwordreset");
            exit();
        } else {
            $message = "Failed to reset password.";
        }
        $stmt2->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,800" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: 
                linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)),
                url('assets/background.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Montserrat', sans-serif;
            height: 100vh;
            margin: 0;
            color: #ffffff;
        }
        .container {
            background-color: #ffffff;
            color: #000;
            width: 400px;
            padding: 40px;
            border-radius: 10px;
            text-align: center;
        }
        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            border-radius: 20px;
            border: none;
            background: #dd353d;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
        }
        a {
            display: block;
            margin-top: 15px;
            color: #dd353d;
            text-decoration: none;
        }
        .message {
            margin-top: 10px;
        }
        .warning { color: #dd353d; }
        .success { color: #28a745; }
        
    </style>
</head>
<body>
    <div class="container">
        <form method="POST">
            <h1>Reset Password</h1>
            <p>Enter your new password</p>
            <input type="password" name="new_password" placeholder="New Password" required />
            <input type="password" name="confirm_password" placeholder="Confirm Password" required />
            <button type="submit">Reset Password</button>
            <?php if($message): ?>
                <p class="message warning"><?= $message ?></p>
            <?php endif; ?>
            <a href="index.php">Back to Sign In</a>
        </form>
    </div>
</body>
</html>
