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

            // Generate verification token and timestamp
            $token = bin2hex(random_bytes(16));
            $sent_at = date('Y-m-d H:i:s');

            // Insert into tbl_sign_in first with verification fields
            $stmt_sign = $conn->prepare("INSERT INTO tbl_sign_in (email, password, verification_token, verification_sent_at, is_admin, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $stmt_sign->bind_param("ssss", $email, $hashed, $token, $sent_at);

            if($stmt_sign->execute()){
                $sign_in_id = $conn->insert_id;

                // Insert profile row into users and link sign_in_id
                $stmt_user = $conn->prepare("INSERT INTO users (username, name, email, password, sign_in_id) VALUES (?, ?, ?, ?, ?)");
                $stmt_user->bind_param("ssssi", $username, $name, $email, $hashed, $sign_in_id);

                if($stmt_user->execute()){
                    // send verification email (best-effort)
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $verify_link = "http://" . $host . dirname($_SERVER['PHP_SELF']) . "/verify.php?token=" . $token;
                    $subject = "Verify your REVCOM account";
                    $htmlBody = "<p>Hi " . htmlspecialchars($name) . ",</p>\n" .
                                "<p>Please verify your email by clicking the link below:</p>\n" .
                                "<p><a href=\"" . htmlspecialchars($verify_link) . "\">Verify your account</a></p>\n" .
                                "<p>If you didn't sign up, ignore this message.</p>";
                    $altBody = "Hi $name\n\nPlease verify your email by visiting: " . $verify_link . "\n\nIf you didn't sign up, ignore this message.";
                    require_once __DIR__ . '/mailer.php';
                    send_mail($email, $subject, $htmlBody, $altBody);

                    $message = "Sign Up successful! Check your email for a verification link.";
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
<div id="toast" aria-live="polite" aria-atomic="true"></div>
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
<script>
document.addEventListener('DOMContentLoaded', function(){
    var msg = <?= json_encode($message) ?>;
    var cls = <?= json_encode($message_class) ?>;
    var t = document.getElementById('toast');
    if (msg && t) {
        t.innerHTML = msg;
        if (cls) t.className = cls + ' show'; else t.className = 'show';
        setTimeout(function(){ t.classList.remove('show'); }, 8000);
    }
    // clear toast when user edits form fields (to avoid stale error messages from autofill)
    var inputs = document.querySelectorAll('input');
    function clearToast(){ if (t) t.classList.remove('show'); }
    inputs.forEach(function(i){ i.addEventListener('focus', clearToast); i.addEventListener('input', clearToast); });
});
</script>