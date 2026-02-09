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

            // Ensure verification_attempts_sent column exists
            $col_check = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_sign_in' AND COLUMN_NAME = 'verification_attempts_sent'");
            $col_check->execute();
            $col_res = $col_check->get_result()->fetch_assoc();
            $col_check->close();
            if (empty($col_res['cnt'])) {
                $conn->query("ALTER TABLE tbl_sign_in ADD COLUMN verification_attempts_sent INT NOT NULL DEFAULT 0");
            }

            // Insert into tbl_sign_in first; token will be updated after we have the sign_in_id
            $stmt_sign = $conn->prepare("INSERT INTO tbl_sign_in (email, password, verification_token, verification_sent_at, is_admin, created_at, verification_attempts_sent) VALUES (?, ?, NULL, NOW(), 0, NOW(), 0)");
            $stmt_sign->bind_param("ss", $email, $hashed);

            if($stmt_sign->execute()){
                $sign_in_id = $conn->insert_id;

                // Insert profile row into users and link sign_in_id
                $stmt_user = $conn->prepare("INSERT INTO users (username, name, sign_in_id) VALUES (?, ?, ?)");
                $stmt_user->bind_param("ssi", $username, $name, $sign_in_id);

                if($stmt_user->execute()){
                    // Generate initial math question and store answer hash in verification_token as JSON
                    $num1 = rand(1,20);
                    $num2 = rand(1,20);
                    $operator = rand(0,1) ? '+' : '-';
                    $correct = ($operator === '+') ? ($num1 + $num2) : ($num1 - $num2);
                    // token format: sign_in_id:answer (plaintext answer stored)
                    $token_payload = $sign_in_id . ':' . $correct;

                    // update the tbl_sign_in row with token and attempts = 1
                    $upd = $conn->prepare("UPDATE tbl_sign_in SET verification_token = ?, verification_sent_at = NOW(), verification_attempts_sent = 1 WHERE sign_in_id = ?");
                    $upd->bind_param('si', $token_payload, $sign_in_id);
                    $upd->execute();
                    $upd->close();

                    // Store the question in session to present on challenge page
                    $_SESSION['current_question_' . $sign_in_id] = "$num1 $operator $num2";
                    $_SESSION['current_answer_' . $sign_in_id] = $correct;

                    // Account created - redirect to challenge verification
                    header("Location: challenge.php?sign_in_id=" . (int)$sign_in_id);
                    exit;
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