<?php
session_start();
include "db.php";

// Record last login timestamp before logout
if (isset($_SESSION['user_id'])) {
	$user_id = $_SESSION['user_id'];
	$stmt = $conn->prepare("UPDATE tbl_sign_in SET last_login = NOW() WHERE sign_in_id = ?");
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$stmt->close();
}

session_unset();
session_destroy();
header("Location: index.php");
exit();
?>
