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

    $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    // --- FEATURE: SIGN-IN REDIRECT (IF NOT REGISTERED) ---
    if ($stmt->num_rows == 0) {
        // Redirect to index with error parameter to trigger the "Sign Up" panel switch
        header("Location: index.php?error=notfound");
        exit();
    } else {
        $stmt->bind_result($id, $username, $hashed, $is_admin);
        $stmt->fetch();
        $hashed = $hashed ?? ''; 

        if (password_verify($password, $hashed)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = $is_admin;

            header("Location: dashboard.php");
            exit();
        } else {
            // Case where email exists but password is wrong
            header("Location: index.php?error=wrongpassword");
            exit();
        }
    }

    $stmt->close();
    $conn->close();
}
?>