<?php
// verify.php - confirm email verification token
session_start();
include 'db.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    header('Location: index.php?error=notfound');
    exit();
}

$stmt = $conn->prepare("SELECT sign_in_id, email, verification_sent_at, is_verified FROM tbl_sign_in WHERE verification_token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    $stmt->close();
    header('Location: index.php?error=notfound');
    exit();
}

$stmt->bind_result($sign_in_id, $email, $sent_at, $is_verified);
$stmt->fetch();
$stmt->close();

if (!empty($is_verified)) {
    header('Location: index.php?success=verified');
    exit();
}

// update verification state
$upd = $conn->prepare("UPDATE tbl_sign_in SET is_verified = 1, verified_at = NOW(), verification_token = NULL WHERE sign_in_id = ?");
$upd->bind_param('i', $sign_in_id);
if ($upd->execute()) {
    $upd->close();
    // attempt to resolve a profile name for a friendlier confirmation
    $name = '';
    $q = $conn->prepare("SELECT first_name FROM tbl_users WHERE sign_in_id = ? LIMIT 1");
    if ($q) {
        $q->bind_param('i', $sign_in_id);
        $q->execute();
        $q->store_result();
        if ($q->num_rows > 0) {
            $q->bind_result($name);
            $q->fetch();
        }
        $q->close();
    }
    $name_param = $name ? '&name=' . rawurlencode($name) : '';
    header('Location: index.php?success=verified' . $name_param);
    exit();
} else {
    $upd->close();
    header('Location: index.php?error=notfound');
    exit();
}

?>
