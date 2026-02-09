<?php
session_start();
include "db.php";

$message = "";
$message_class = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // --- FEATURE: EMAIL AUTHENTICATION (VALIDATOR) ---
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // If the email format is invalid, redirect back with an error
        header("Location: index.php?error=invalidemail");
        exit();
    }

    // Authenticate against tbl_sign_in
    $stmt = $conn->prepare("SELECT sign_in_id, password, is_admin, is_verified FROM tbl_sign_in WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        header("Location: index.php?error=notfound&email=" . urlencode($email));
        exit();
    }

    $stmt->bind_result($sign_in_id, $hashed, $is_admin, $is_verified);
    $stmt->fetch();
    $hashed = $hashed ?? '';

    if (!password_verify($password, $hashed)) {
        header("Location: index.php?error=wrongpassword&email=" . urlencode($email));
        exit();
    }

    // Require verified account (admin approval)
    if (empty($is_verified) || $is_verified == 0) {
        header("Location: index.php?error=notverified&email=" . urlencode($email));
        exit();
    }

    // Find associated users.user_id
    $stmt_u = $conn->prepare("SELECT user_id, username FROM tbl_users WHERE sign_in_id = ? LIMIT 1");
    $stmt_u->bind_param("i", $sign_in_id);
    $stmt_u->execute();
    $stmt_u->store_result();

    if ($stmt_u->num_rows == 0) {
        // Create a minimal profile for this sign-in (fallback)
        $base = strstr($email, '@', true);
        $username_try = $base ?: 'user' . $sign_in_id;
        // ensure username uniqueness
        $orig = $username_try;
        $i = 1;
        $check = $conn->prepare("SELECT user_id FROM tbl_users WHERE username = ?");
        while (true) {
            $check->bind_param("s", $username_try);
            $check->execute();
            $check->store_result();
            if ($check->num_rows == 0) break;
            $i++;
            $username_try = $orig . $i;
        }
        $check->close();

        $stmt_create = $conn->prepare("INSERT INTO tbl_users (username, first_name, sign_in_id) VALUES (?, ?, ?)");
        $name_for_profile = $username_try;
        $stmt_create->bind_param("ssi", $username_try, $name_for_profile, $sign_in_id);
        $stmt_create->execute();
        $user_id = $conn->insert_id;
        $username = $username_try;
        $stmt_create->close();
    } else {
        $stmt_u->bind_result($user_id, $username);
        $stmt_u->fetch();
    }

    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['is_admin'] = $is_admin;

    header("Location: dashboard.php");
    exit();

    $stmt->close();
    $stmt_u->close();
    $conn->close();
}
?>