<?php
// resend_verification.php - generate fresh verification math challenge
session_start();
include 'db.php';

// Accept both GET parameter and direct sign_in_id
$sign_in_id = isset($_GET['sign_in_id']) ? (int)$_GET['sign_in_id'] : 0;
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

// If we have sign_in_id, use it directly
if ($sign_in_id > 0) {
    $stmt = $conn->prepare("SELECT sign_in_id, is_verified, verification_token, verification_sent_at FROM tbl_sign_in WHERE sign_in_id = ? LIMIT 1");
    $stmt->bind_param('i', $sign_in_id);
} elseif (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt = $conn->prepare("SELECT sign_in_id, is_verified, verification_token, verification_sent_at FROM tbl_sign_in WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
} else {
    header('Location: index.php?error=notfound');
    exit();
}

$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    $stmt->close();
    header('Location: index.php?error=notfound');
    exit();
}

$stmt->bind_result($sign_in_id, $is_verified, $verification_token, $verification_sent_at);
$stmt->fetch();
$stmt->close();

// If already verified, redirect to login
if ($is_verified) {
    header('Location: index.php?error=alreadyverified');
    exit();
}

// Check if a challenge is already in progress (less than 60 seconds old)
if ($verification_token && strpos($verification_token, 'CHG-') === 0 && $verification_sent_at) {
    $elapsed = time() - strtotime($verification_sent_at);
    if ($elapsed < 60) {
        // Challenge already in progress - don't allow resend
        header('Location: index.php?error=challengeinprogress');
        exit();
    }
}

// Ensure verification_attempts_sent column exists
$col_check = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_sign_in' AND COLUMN_NAME = 'verification_attempts_sent'");
$col_check->execute();
$col_res = $col_check->get_result()->fetch_assoc();
$col_check->close();
if (empty($col_res['cnt'])) {
    $conn->query("ALTER TABLE tbl_sign_in ADD COLUMN verification_attempts_sent INT NOT NULL DEFAULT 0");
}

// Generate a fresh question and store token JSON with hashed answer
$num1 = rand(1,20);
$num2 = rand(1,20);
$operator = rand(0,1) ? '+' : '-';
$correct = ($operator === '+') ? ($num1 + $num2) : ($num1 - $num2);
$answer_plain = (string)$correct;
// token format: sign_in_id:answer (plaintext)
$token_payload = $sign_in_id . ':' . $answer_plain;

$upd = $conn->prepare("UPDATE tbl_sign_in SET verification_token = ?, verification_sent_at = NOW(), verification_attempts_sent = 1 WHERE sign_in_id = ?");
$upd->bind_param('si', $token_payload, $sign_in_id);
$upd->execute();
$upd->close();

// Store the question in session so the challenge page can show it immediately
$_SESSION['current_question_' . $sign_in_id] = "$num1 $operator $num2";
$_SESSION['current_answer_' . $sign_in_id] = $correct;

// Redirect to challenge page to start fresh
header('Location: challenge.php?sign_in_id=' . (int)$sign_in_id);
exit();

?>
