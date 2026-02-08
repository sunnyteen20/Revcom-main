<?php
// resend_verification.php - generate and send a new verification token
session_start();
include 'db.php';

$email = $_GET['email'] ?? ($_POST['email'] ?? '');
$email = trim($email);
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php?error=notfound');
    exit();
}

$stmt = $conn->prepare("SELECT sign_in_id, is_verified FROM tbl_sign_in WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    $stmt->close();
    header('Location: index.php?error=notfound');
    exit();
}

$stmt->bind_result($sign_in_id, $is_verified);
$stmt->fetch();
$stmt->close();

if (!empty($is_verified)) {
    // already verified
    header('Location: index.php?success=verified');
    exit();
}

// generate new token
$token = bin2hex(random_bytes(16));
$sent_at = date('Y-m-d H:i:s');

$upd = $conn->prepare("UPDATE tbl_sign_in SET verification_token = ?, verification_sent_at = ? WHERE sign_in_id = ?");
$upd->bind_param('ssi', $token, $sent_at, $sign_in_id);
if ($upd->execute()) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verify_link = "http://" . $host . dirname($_SERVER['PHP_SELF']) . "/verify.php?token=" . $token;
    $subject = "Verify your REVCOM account";
    $body = "Hi,\n\nPlease verify your email by clicking the link below:\n" . $verify_link . "\n\nIf you didn't request this, ignore this message.";
    require_once __DIR__ . '/mailer.php';
    $sent = send_mail($email, $subject, $body);
    if (!$sent) {
        $headers = "From: no-reply@" . $host . "\r\n";
        @mail($email, $subject, $body, $headers);
    }
    $upd->close();
    header('Location: index.php?resent=1');
    exit();
} else {
    $upd->close();
    header('Location: index.php?error=notfound');
    exit();
}

?>
