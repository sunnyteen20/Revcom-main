<?php
session_start();
include "db.php";

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Admin flag comes from tbl_sign_in (auth table) joined via users.sign_in_id
$check_admin = $conn->prepare(
    "SELECT COALESCE(s.is_admin, 0) as is_admin_flag
     FROM users u
     LEFT JOIN tbl_sign_in s ON u.sign_in_id = s.sign_in_id
     WHERE u.id = ? LIMIT 1"
);
$check_admin->bind_param("i", $user_id);
$check_admin->execute();
$check_admin->store_result();

if($check_admin->num_rows == 0){
    header("Location: dashboard.php");
    exit();
}

$check_admin->bind_result($is_admin_flag);
$check_admin->fetch();

if($is_admin_flag != 1){
    header("Location: dashboard.php");
    exit();
}

$check_admin->close();

// ---- ADMIN ACTIONS ----
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';
$message_class = '';

if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(isset($_POST['action'])){
        
        // SOFT-DELETE REVIEW (archive)
        if($_POST['action'] == 'delete_review'){
            $review_id = intval($_POST['review_id']);
            // Use soft-delete if column exists
            $stmt = $conn->prepare("UPDATE tbl_movie_review SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?");
            $stmt->bind_param("ii", $user_id, $review_id);
            if($stmt->execute()){
                $message = "Review archived successfully";
                $message_class = "success";
            } else {
                // fallback: try hard delete
                $stmt2 = $conn->prepare("DELETE FROM tbl_movie_review WHERE id=?");
                $stmt2->bind_param("i", $review_id);
                if($stmt2->execute()){
                    $message = "Review deleted successfully (hard delete)";
                    $message_class = "success";
                } else {
                    $message = "Failed to delete review";
                    $message_class = "warning";
                }
                $stmt2->close();
            }
            $stmt->close();
        }
        
        // SOFT-DELETE USER (archive)
        if($_POST['action'] == 'delete_user'){
            $del_user_id = intval($_POST['user_id']);
            if($del_user_id == $user_id){
                $message = "Cannot delete yourself";
                $message_class = "warning";
            } else {
                $stmt = $conn->prepare("UPDATE users SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=?");
                $stmt->bind_param("ii", $user_id, $del_user_id);
                if($stmt->execute()){
                    // soft-delete their reviews as well
                    $stmt2 = $conn->prepare("UPDATE tbl_movie_review SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE user_id=?");
                    $stmt2->bind_param("ii", $user_id, $del_user_id);
                    $stmt2->execute();
                    $stmt2->close();
                    $message = "User archived successfully";
                    $message_class = "success";
                } else {
                    $message = "Failed to archive user";
                    $message_class = "warning";
                }
                $stmt->close();
            }
        }
        
        // MAKE ADMIN
        if($_POST['action'] == 'make_admin'){
            $admin_user_id = intval($_POST['user_id']);
            // Promote user by updating authentication table only (single source of truth)
            $stmt2 = $conn->prepare("SELECT sign_in_id FROM users WHERE id = ? LIMIT 1");
            $stmt2->bind_param("i", $admin_user_id);
            $stmt2->execute();
            $stmt2->bind_result($s_id);
            $stmt2->fetch();
            $stmt2->close();

            if(!empty($s_id)){
                $u = $conn->prepare("UPDATE tbl_sign_in SET is_admin = 1 WHERE sign_in_id = ?");
                $u->bind_param("i", $s_id);
                $ok = $u->execute();
                $u->close();
            } else {
                $ok = false;
            }

            if($ok){
                $message = "User is now admin";
                $message_class = "success";
            } else {
                $message = "Failed to update user";
                $message_class = "warning";
            }
        }
        
        // REMOVE ADMIN
        if($_POST['action'] == 'remove_admin'){
            $admin_user_id = intval($_POST['user_id']);
            if($admin_user_id == $user_id){
                $message = "Cannot remove yourself as admin";
                $message_class = "warning";
            } else {
                // Demote user by updating tbl_sign_in only
                $stmt2 = $conn->prepare("SELECT sign_in_id FROM users WHERE id = ? LIMIT 1");
                $stmt2->bind_param("i", $admin_user_id);
                $stmt2->execute();
                $stmt2->bind_result($s_id2);
                $stmt2->fetch();
                $stmt2->close();

                if(!empty($s_id2)){
                    $u2 = $conn->prepare("UPDATE tbl_sign_in SET is_admin = 0 WHERE sign_in_id = ?");
                    $u2->bind_param("i", $s_id2);
                    $ok = $u2->execute();
                    $u2->close();
                } else {
                    $ok = false;
                }

                if($ok){
                    $message = "Admin privileges removed";
                    $message_class = "success";
                } else {
                    $message = "Failed to update user";
                    $message_class = "warning";
                }
            }
        }

        // RESTORE USER
        if($_POST['action'] == 'restore_user'){
            $restore_user_id = intval($_POST['user_id']);
            $stmt = $conn->prepare("UPDATE users SET is_deleted=0, deleted_at=NULL, deleted_by=NULL WHERE id=?");
            $stmt->bind_param("i", $restore_user_id);
            if($stmt->execute()){
                // restore their reviews
                $stmt2 = $conn->prepare("UPDATE tbl_movie_review SET is_deleted=0, deleted_at=NULL, deleted_by=NULL WHERE user_id=?");
                $stmt2->bind_param("i", $restore_user_id);
                $stmt2->execute();
                $stmt2->close();
                $message = "User restored from archive";
                $message_class = "success";
            } else {
                $message = "Failed to restore user";
                $message_class = "warning";
            }
            $stmt->close();
        }

        // PURGE USER (permanent)
        if($_POST['action'] == 'purge_user'){
            $purge_user_id = intval($_POST['user_id']);
            if($purge_user_id == $user_id){
                $message = "Cannot purge yourself";
                $message_class = "warning";
            } else {
                // delete dependent data first
                $stmt = $conn->prepare("DELETE FROM tbl_movie_review WHERE user_id=?");
                $stmt->bind_param("i", $purge_user_id);
                $stmt->execute();
                $stmt->close();
                $conn->query("DELETE FROM tbl_watchlist WHERE user_id=$purge_user_id");
                $stmt2 = $conn->prepare("DELETE FROM users WHERE id=?");
                $stmt2->bind_param("i", $purge_user_id);
                if($stmt2->execute()){
                    $message = "User permanently deleted";
                    $message_class = "success";
                } else {
                    $message = "Failed to permanently delete user";
                    $message_class = "warning";
                }
                $stmt2->close();
            }
        }

        // RESTORE REVIEW
        if($_POST['action'] == 'restore_review'){
            $restore_review_id = intval($_POST['review_id']);
            $stmt = $conn->prepare("UPDATE tbl_movie_review SET is_deleted=0, deleted_at=NULL, deleted_by=NULL WHERE id=?");
            $stmt->bind_param("i", $restore_review_id);
            if($stmt->execute()){
                $message = "Review restored from archive";
                $message_class = "success";
            } else {
                $message = "Failed to restore review";
                $message_class = "warning";
            }
            $stmt->close();
        }

        // PURGE REVIEW (permanent)
        if($_POST['action'] == 'purge_review'){
            $purge_review_id = intval($_POST['review_id']);
            $stmt = $conn->prepare("DELETE FROM tbl_movie_review WHERE id=?");
            $stmt->bind_param("i", $purge_review_id);
            if($stmt->execute()){
                $message = "Review permanently deleted";
                $message_class = "success";
            } else {
                $message = "Failed to delete review";
                $message_class = "warning";
            }
            $stmt->close();
        }
    }
}

// Get page view
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - REVCOM</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-red: #dd353d;
            --dark-bg: #210b0c;
            --original-gradient: linear-gradient(to right, #210b0c, #dd353d, #210b0c);
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--original-gradient);
            background-attachment: fixed;
            margin: 0;
            padding: 20px;
            color: #fff;
            min-height: 100vh;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            margin-top: 20px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.6);
            border-radius: 10px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 3px solid var(--primary-red);
            padding-bottom: 15px;
        }

        .admin-header h1 {
            margin: 0;
            font-size: 32px;
            color: #fff;
            text-transform: uppercase;
        }

        .admin-header div a {
            margin-left: 10px;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            border: 2px solid var(--primary-red);
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .admin-header div a[href*="dashboard"] {
            background: var(--primary-red);
            color: #fff;
        }

        .admin-header div a[href*="dashboard"]:hover {
            background: transparent;
            color: var(--primary-red);
        }

        .admin-header div a[href*="logout"] {
            background: transparent;
            color: var(--primary-red);
        }

        .admin-header div a[href*="logout"]:hover {
            background: var(--primary-red);
            color: #fff;
        }

        .admin-nav {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .admin-nav a {
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            text-decoration: none;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.3s;
        }

        .admin-nav a:hover {
            background: var(--primary-red);
            border-color: var(--primary-red);
        }

        .admin-nav a.active {
            background: var(--primary-red);
            border-color: var(--primary-red);
        }

        .admin-section {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-red);
            display: flex;
            flex-direction: column;
            min-height: 550px;
            width: 100%;
        }

        .admin-section h2 {
            color: #fff;
            border-bottom: 2px solid var(--primary-red);
            padding-bottom: 10px;
            margin: 0 0 20px 0;
            font-size: 20px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.05);
            min-width: 800px;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            flex: 1;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        table td:last-child {
            text-align: center;
            display: table-cell;
            vertical-align: middle;
        }

        table th:last-child {
            text-align: center;
        }

        table tbody td:nth-child(4) {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        table tbody td:nth-child(4) button {
            flex-shrink: 0;
            margin-left: 0;
        }

        table th {
            background: var(--primary-red);
            color: #fff;
            font-weight: bold;
            text-transform: uppercase;
        }

        table tr:hover {
            background: rgba(221, 53, 61, 0.1);
        }

        .btn-danger, .btn-success {
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: 0.3s;
            background: transparent !important;
            border: 2px solid;
            color: inherit !important;
        }

        .btn-danger {
            color: #ff6b6b !important;
            border-color: #ff6b6b !important;
        }

        .btn-danger:hover {
            background: #ff6b6b !important;
            color: #fff !important;
        }

        .btn-success {
            color: var(--primary-red) !important;
            border-color: var(--primary-red) !important;
        }

        .btn-success:hover {
            background: var(--primary-red) !important;
            color: #fff !important;
        }

        form {
            background: transparent !important;
            display: inline-block;
            margin: 0 3px;
        }

        .btn-danger i, .btn-success i, .admin-header a i, button[type="button"].btn-success i {
            margin-right: 6px;
            font-size: 13px;
        }

        .admin-nav i {
            margin-right: 8px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-section {
            animation: fadeIn 0.4s ease-in-out;
            scroll-margin-top: 40px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(0, 0, 0, 0.95);
            border: 2px solid var(--primary-red);
            border-radius: 10px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            color: #fff;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary-red);
            padding-bottom: 15px;
        }

        .modal-header h2 {
            margin: 0;
            color: #fff;
            font-size: 22px;
        }

        .modal-close {
            background: transparent;
            border: 2px solid var(--primary-red);
            color: var(--primary-red);
            font-size: 24px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            transition: 0.3s;
        }

        .modal-close:hover {
            background: var(--primary-red);
            color: #fff;
        }

        .review-detail {
            margin-bottom: 15px;
        }

        .review-detail-label {
            color: var(--primary-red);
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        button[type="button"].btn-success {
            color: var(--primary-red) !important;
            border-color: var(--primary-red) !important;
            background: transparent !important;
            border: 2px solid;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            font-weight: bold;
            transition: 0.3s;
            min-width: auto;
        }

        button[type="button"].btn-success:hover {
            background: var(--primary-red) !important;
            color: #fff !important;
        }

        .message {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            border: 2px solid;
        }

        .message.success {
            background: rgba(221, 53, 61, 0.15);
            color: #fff;
            border-color: var(--primary-red);
        }

        .message.warning {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border-color: #ffc107;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-red);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .stat-box h3 {
            margin: 0 0 10px 0;
            color: var(--primary-red);
            font-size: 14px;
            text-transform: uppercase;
        }

        .stat-box .number {
            font-size: 28px;
            font-weight: bold;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="admin-header">
        <h1>Admin Panel</h1>
        <div>
            <a href="dashboard.php" title="Back to Dashboard"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <a href="logout.php" title="Logout"><i class="fa-solid fa-power-off"></i> Logout</a>
        </div>
    </div>

    <?php if(!empty($message)): ?>
        <div class="message <?= $message_class ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="admin-nav">
        <a href="admin.php?page=dashboard" class="<?= ($page == 'dashboard') ? 'active' : '' ?>"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="admin.php?page=users" class="<?= ($page == 'users') ? 'active' : '' ?>"><i class="fa-solid fa-users"></i> Manage Users</a>
        <a href="admin.php?page=reviews" class="<?= ($page == 'reviews') ? 'active' : '' ?>"><i class="fa-solid fa-star"></i> Manage Reviews</a>
        <a href="admin.php?page=trash" class="<?= ($page == 'trash') ? 'active' : '' ?>"><i class="fa-solid fa-trash-can"></i></a>
    </div>

    <!-- DASHBOARD PAGE -->
    <?php if($page == 'dashboard'): ?>
        <div class="admin-section page-section">
            <h2>Dashboard Overview</h2>
            
            <?php
            $total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE IFNULL(is_deleted,0)=0")->fetch_assoc()['count'];
            $total_reviews = $conn->query("SELECT COUNT(*) as count FROM tbl_movie_review WHERE IFNULL(is_deleted,0)=0")->fetch_assoc()['count'];
            $total_admins = $conn->query("SELECT COUNT(*) as count FROM users u JOIN tbl_sign_in s ON u.sign_in_id = s.sign_in_id WHERE COALESCE(s.is_admin,0)=1 AND IFNULL(u.is_deleted,0)=0")->fetch_assoc()['count'];
            $total_watchlist = $conn->query("SELECT COUNT(*) as count FROM tbl_watchlist")->fetch_assoc()['count'];
            ?>
            
            <div class="stats">
                <div class="stat-box">
                    <h3>Total Users</h3>
                    <div class="number"><?= $total_users ?></div>
                </div>
                <div class="stat-box">
                    <h3>Total Reviews</h3>
                    <div class="number"><?= $total_reviews ?></div>
                </div>
                <div class="stat-box">
                    <h3>Admin Users</h3>
                    <div class="number"><?= $total_admins ?></div>
                </div>
                <div class="stat-box">
                    <h3>Watchlist Items</h3>
                    <div class="number"><?= $total_watchlist ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- USERS PAGE -->
    <?php if($page == 'users'): ?>
        <div class="admin-section page-section">
            <h2>User Management</h2>
            
            <?php
            $users_result = $conn->query("SELECT u.username, u.email, COALESCE(s.is_admin,0) AS is_admin, u.created_at, u.id FROM users u LEFT JOIN tbl_sign_in s ON u.sign_in_id = s.sign_in_id WHERE IFNULL(u.is_deleted,0)=0 ORDER BY u.created_at DESC");
            ?>
            
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Admin?</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($u = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= $u['is_admin'] ? 'Yes' : 'No' ?></td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if($u['id'] != $user_id): ?>
                                    <?php if($u['is_admin']): ?>
                                        <form method="POST" style="display:inline; margin-right: 6px;">
                                            <input type="hidden" name="action" value="remove_admin">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn-success" onclick="return confirm('Remove admin privileges?');"><i class="fa-solid fa-shield-halved"></i> Remove Admin</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline; margin-right: 6px;">
                                            <input type="hidden" name="action" value="make_admin">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn-success" onclick="return confirm('Make this user admin?');"><i class="fa-solid fa-shield"></i> Make Admin</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline; margin-left: 6px;">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-danger" onclick="return confirm('Delete this user permanently?');"><i class="fa-solid fa-trash"></i> Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="display: inline-block; margin: 0 3px; color: #fff; font-weight: bold; font-size: 17px;">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- REVIEWS PAGE -->
    <?php if($page == 'reviews'): ?>
        <div class="admin-section page-section">
            <h2>Review Management</h2>
            
            <?php
            $reviews_result = $conn->query("
                SELECT r.id, r.movie_title, r.rating, r.review, r.created_at, u.username 
                FROM tbl_movie_review r 
                JOIN users u ON r.user_id = u.id 
                WHERE IFNULL(r.is_deleted,0)=0
                ORDER BY r.created_at DESC
            ");
            ?>
            
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Movie</th>
                        <th>User</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($r = $reviews_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['movie_title']) ?></td>
                            <td><?= htmlspecialchars($r['username']) ?></td>
                            <td style="white-space: nowrap;"><?= $r['rating'] ?>/10</td>
                            <td>
                                <?= htmlspecialchars(substr($r['review'], 0, 50)) ?>...
                                <button type="button" class="btn-success" onclick="viewReview(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['movie_title'])) ?>', '<?= htmlspecialchars(addslashes($r['username'])) ?>', '<?= $r['rating'] ?>', '<?= htmlspecialchars(addslashes($r['review'])) ?>', '<?= $r['created_at'] ?>');"><i class="fa-solid fa-eye"></i></button>
                            </td>
                            <td style="white-space: nowrap;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_review">
                                    <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn-danger" onclick="return confirm('Delete this review?');"><i class="fa-solid fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- TRASH / ARCHIVE PAGE -->
    <?php if($page == 'trash'): ?>
        <div class="admin-section page-section">
            <h2>Trash / Archive</h2>

            <?php
            $deleted_users = $conn->query("SELECT u.id, u.username, u.email, COALESCE(s.is_admin,0) AS is_admin, u.deleted_at, u.deleted_by FROM users u LEFT JOIN tbl_sign_in s ON u.sign_in_id = s.sign_in_id WHERE IFNULL(u.is_deleted,0)=1 ORDER BY u.deleted_at DESC");
            $deleted_reviews = $conn->query("SELECT r.id, r.movie_title, r.rating, r.review, r.created_at, r.deleted_at, u.username FROM tbl_movie_review r JOIN users u ON r.user_id = u.id WHERE IFNULL(r.is_deleted,0)=1 ORDER BY r.deleted_at DESC");
            ?>

            <h3 style="margin-top:0; margin-bottom:10px; color:var(--primary-red);">Deleted Users</h3>
            <div class="table-wrapper" style="margin-bottom:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Admin?</th>
                        <th>Deleted At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($du = $deleted_users->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($du['username']) ?></td>
                            <td><?= htmlspecialchars($du['email']) ?></td>
                            <td><?= $du['is_admin'] ? 'Yes' : 'No' ?></td>
                            <td><?= $du['deleted_at'] ? date('M d, Y H:i', strtotime($du['deleted_at'])) : '-' ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="restore_user">
                                    <input type="hidden" name="user_id" value="<?= $du['id'] ?>">
                                    <button type="submit" class="btn-success" onclick="return confirm('Restore this user?');"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                </form>
                                <form method="POST" style="display:inline; margin-left:6px;">
                                    <input type="hidden" name="action" value="purge_user">
                                    <input type="hidden" name="user_id" value="<?= $du['id'] ?>">
                                    <button type="submit" class="btn-danger" onclick="return confirm('Permanently delete this user? This cannot be undone.');"><i class="fa-solid fa-times"></i> Purge</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>

            <h3 style="margin-top:0; margin-bottom:10px; color:var(--primary-red);">Deleted Reviews</h3>
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Movie</th>
                        <th>User</th>
                        <th>Rating</th>
                        <th>Deleted At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($dr = $deleted_reviews->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($dr['movie_title']) ?></td>
                            <td><?= htmlspecialchars($dr['username']) ?></td>
                            <td style="white-space: nowrap;"><?= $dr['rating'] ?>/10</td>
                            <td><?= $dr['deleted_at'] ? date('M d, Y H:i', strtotime($dr['deleted_at'])) : date('M d, Y H:i', strtotime($dr['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="restore_review">
                                    <input type="hidden" name="review_id" value="<?= $dr['id'] ?>">
                                    <button type="submit" class="btn-success" onclick="return confirm('Restore this review?');"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                </form>
                                <form method="POST" style="display:inline; margin-left:6px;">
                                    <input type="hidden" name="action" value="purge_review">
                                    <input type="hidden" name="review_id" value="<?= $dr['id'] ?>">
                                    <button type="submit" class="btn-danger" onclick="return confirm('Permanently delete this review?');"><i class="fa-solid fa-times"></i> Purge</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

<!-- Review Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Review Details</h2>
            <button type="button" class="modal-close" onclick="closeReviewModal();">×</button>
        </div>
        
        <div class="review-detail">
            <div class="review-detail-label">Movie</div>
            <div class="review-detail-value" id="modalMovie">-</div>
        </div>

        <div class="review-detail">
            <div class="review-detail-label">User</div>
            <div class="review-detail-value" id="modalUser">-</div>
        </div>

        <div class="review-detail">
            <div class="review-detail-label">Rating</div>
            <div class="review-detail-value" id="modalRating">-</div>
        </div>

        <div class="review-detail">
            <div class="review-detail-label">Date</div>
            <div class="review-detail-value" id="modalDate">-</div>
        </div>

        <div class="review-detail">
            <div class="review-detail-label">Review Comment</div>
            <div class="review-detail-value" id="modalReview" style="white-space: pre-wrap; word-wrap: break-word;">-</div>
        </div>
    </div>
</div>

<script>
function viewReview(id, movie, user, rating, review, date) {
    document.getElementById('modalMovie').textContent = movie;
    document.getElementById('modalUser').textContent = user;
    document.getElementById('modalRating').textContent = rating + '/10';
    document.getElementById('modalReview').textContent = review;
    document.getElementById('modalDate').textContent = new Date(date).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
    document.getElementById('reviewModal').classList.add('active');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('active');
}

// Close modal when clicking outside of it
document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReviewModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReviewModal();
    }
});

// Scroll to top when page loads
window.addEventListener('load', function() {
    window.scrollTo({top: 0, behavior: 'smooth'});
});

// Scroll to top when tab is clicked (page param changes)
document.querySelectorAll('.admin-nav a').forEach(link => {
    link.addEventListener('click', function() {
        setTimeout(function() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        }, 50);
    });
});

// Trigger page refresh after form submissions (delete, make admin, remove admin)
document.querySelectorAll('form[method="POST"]').forEach(form => {
    form.addEventListener('submit', function() {
        setTimeout(function() {
            location.reload();
        }, 500);
    });
});
</script>

</div>
</body>
</html>
