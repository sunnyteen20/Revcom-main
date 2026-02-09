<?php
session_start();
include "db.php";

$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    
    $stmt = $conn->prepare("SELECT sign_in_id FROM tbl_sign_in WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if($stmt->num_rows > 0){
        $_SESSION['reset_email'] = $email;
        header("Location: reset_password.php");
        exit();
    } else {
        $message = "Email not found";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
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
            <h1>Forgot Password</h1>
            <p>Enter your email to reset your password</p>
            <input type="email" name="email" placeholder="Email" required />
            <button type="submit">Send Reset Link</button>
            <?php if($message): ?>
                <p class="message warning"><?= $message ?></p>
            <?php endif; ?>
            <a href="index.php">Back to Sign In</a>
        </form>
    </div>
</body>
</html>
