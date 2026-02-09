<?php
session_start();
include "db.php";

$signup_message = "";
$signup_class = "";
$signin_message = "";
$signin_class = "";

// messages from redirects (login / verification)
if (isset($_GET['error'])) {
    $err = $_GET['error'];
    if ($err === 'invalidemail') {
        $signin_message = 'Invalid email format';
        $signin_class = 'warning';
    } elseif ($err === 'notfound') {
        $signin_message = 'Account not found';
        $signin_class = 'warning';
    } elseif ($err === 'wrongpassword') {
        $signin_message = 'Incorrect password';
        $signin_class = 'warning';
    } elseif ($err === 'notverified') {
        $email_q = isset($_GET['email']) ? urlencode($_GET['email']) : '';
        if ($email_q) {
            $signin_message = 'Your account has not been verified yet. <a href="resend_verification.php?email=' . htmlspecialchars($email_q) . '" style="color: #dd353d; text-decoration: underline;">Resend verification challenge</a>';
        } else {
            $signin_message = 'Your account has not been verified yet.';
        }
        $signin_class = 'warning';
    } elseif ($err === 'challengeexpired') {
        $signin_message = 'Time limit expired during account verification. Your account has been deleted. Please sign up again.';
        $signin_class = 'warning';
    } elseif ($err === 'challengefailed') {
        $signin_message = 'You exceeded the maximum number of verification attempts. Your account has been deleted. Please sign up again.';
        $signin_class = 'warning';
    } elseif ($err === 'invalid') {
        $signin_message = 'Invalid request.';
        $signin_class = 'warning';
    } elseif ($err === 'alreadyverified') {
        $signin_message = 'Your account is already verified. Please sign in.';
        $signin_class = 'success';
    } elseif ($err === 'challengeinprogress') {
        $signin_message = 'A verification challenge is already in progress for your account. Please complete it or wait for it to expire (1 minute).';
        $signin_class = 'warning';
    } elseif ($err === 'emailnotfound') {
        $signin_message = 'Email not found in our system.';
        $signin_class = 'warning';
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'verified') {
    $signin_message = '✓ Account verified successfully! You may now sign in.';
    $signin_class = 'success';
}

if (isset($_GET['success']) && $_GET['success'] === 'passwordreset') {
    $signin_message = '✓ Password reset successfully! You may now sign in with your new password.';
    $signin_class = 'success';
}

if (isset($_GET['resent']) && $_GET['resent'] == '1') {
    $signin_message = 'Verification email resent. Check your inbox.';
    $signin_class = 'success';
}

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

            // create verification token and timestamp
            $token = bin2hex(random_bytes(16));
            $sent_at = date('Y-m-d H:i:s');

            $stmt_sign = $conn->prepare("INSERT INTO tbl_sign_in (email, password, verification_token, verification_sent_at, is_admin, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            $stmt_sign->bind_param("ssss", $email, $hashed, $token, $sent_at);

            if($stmt_sign->execute()){
                $sign_in_id = $conn->insert_id;

                $stmt_user = $conn->prepare("INSERT INTO users(username, name, sign_in_id) VALUES(?,?,?)");
                $stmt_user->bind_param("ssi", $username, $name, $sign_in_id);

                if($stmt_user->execute()){
                    // send verification email
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $verify_link = "http://" . $host . dirname($_SERVER['PHP_SELF']) . "/verify.php?token=" . $token;
                    $subject = "Verify your REVCOM account";
                    $body = "Hi $name,\n\nPlease verify your email by clicking the link below:\n" . $verify_link . "\n\nIf you didn't sign up, ignore this message.";
                    require_once __DIR__ . '/mailer.php';
                    // try sending via SMTP helper; fallback to mail()
                    $sent = send_mail($email, $subject, $body);
                    if (!$sent) {
                        $headers = "From: no-reply@" . $host . "\r\n";
                        @mail($email, $subject, $body, $headers);
                    }

                    $signup_message = "Sign Up successful! Check your email for a verification link.";
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
<div id="toast" aria-live="polite" aria-atomic="true"></div>
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
            <input type="email" placeholder="Email" name="email" required value="<?= isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '' ?>"/>
            <input type="password" placeholder="Password" name="password" required/>
            <?php if(!empty($signin_message)): ?>
                <div class="message <?= $signin_class ?>"><?= $signin_message ?></div>
            <?php endif; ?>
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
<div id="toast" aria-live="polite" aria-atomic="true"></div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var signupMsg = <?= json_encode($signup_message) ?>;
    var signupCls = <?= json_encode($signup_class) ?>;
    var signinMsg = <?= json_encode($signin_message) ?>;
    var signinCls = <?= json_encode($signin_class) ?>;
    var msg = signupMsg || signinMsg || '';
    var cls = signupMsg ? signupCls : signinMsg ? signinCls : '';
    if (msg) {
        var t = document.getElementById('toast');
        t.innerHTML = msg;
        if (cls) t.className = cls + ' show'; else t.className = 'show';
        // Remove after 8 seconds
        setTimeout(function(){ t.classList.remove('show'); }, 8000);
    }
});
// enhance: clear toast when user focuses inputs
document.addEventListener('DOMContentLoaded', function(){
    var inputs = document.querySelectorAll('input[type="email"], input[type="password"], input[type="text"]');
    var t = document.getElementById('toast');
    function clearToast(){ if (t) t.classList.remove('show'); }
    inputs.forEach(function(i){ i.addEventListener('focus', clearToast); i.addEventListener('input', clearToast); });
});
</script>
</body>
</html>