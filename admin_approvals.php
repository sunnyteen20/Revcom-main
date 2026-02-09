<?php
session_start();
include "db.php";

// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: dashboard.php");
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$sign_in_id = isset($_POST['sign_in_id']) ? (int)$_POST['sign_in_id'] : 0;

// Handle approve/reject
if ($action && $sign_in_id) {
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE tbl_sign_in SET is_verified = 1, verified_at = NOW() WHERE sign_in_id = ?");
        $stmt->bind_param("i", $sign_in_id);
        $stmt->execute();
        $message = "Account approved successfully.";
        $message_class = "success";
        $stmt->close();
    } elseif ($action === 'reject') {
        // Delete the account (sign_in and associated user)
        $stmt_del_user = $conn->prepare("DELETE FROM tbl_users WHERE sign_in_id = ?");
        $stmt_del_user->bind_param("i", $sign_in_id);
        $stmt_del_user->execute();
        $stmt_del_user->close();

        $stmt_del_signin = $conn->prepare("DELETE FROM tbl_sign_in WHERE sign_in_id = ?");
        $stmt_del_signin->bind_param("i", $sign_in_id);
        $stmt_del_signin->execute();
        $stmt_del_signin->close();

        $message = "Account rejected and deleted.";
        $message_class = "warning";
    }
}

// Fetch pending accounts (unverified)
$stmt = $conn->prepare("
    SELECT s.sign_in_id, s.email, s.created_at, u.username, u.first_name 
    FROM tbl_sign_in s
    LEFT JOIN tbl_users u ON s.sign_in_id = u.sign_in_id
    WHERE s.is_verified = 0
    ORDER BY s.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$pending_accounts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals - REVCOM Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Override global layout rules so admin pages can scroll normally */
        body { display: block; font-family: Arial, sans-serif; margin: 0; background: url('assets/background.jpg') no-repeat center center fixed; background-size: cover; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; overflow: visible; }
        h1 { color: #dd353d; margin-bottom: 10px; font-size: 32px; }
        p { color: #ffffff; margin-bottom: 20px; }
        .table-wrapper { overflow: auto; width: 100%; max-height: 70vh; border: 2px solid #dd353d; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; background-color: #913e3e; }
        th, td { text-align: left; padding: 12px 15px; border-bottom: 1px solid #000000; }
        th { background-color: #dd353d; font-weight: bold; color: #000000; position: sticky; top: 0; z-index: 10; }
        tr:hover { background-color: #e19f9f; }
        .actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; padding: 6px 0; }
        .actions form { display: block; }
        button { padding: 8px 14px; cursor: pointer; border: none; border-radius: 4px; font-size: 14px; white-space: nowrap; font-weight: bold; }
        .approve-btn { background-color: #4CAF50; color: white; }
        .reject-btn { background-color: #dd353d; color: white; }
        .approve-btn:hover { background-color: #45a049; }
        .reject-btn:hover { background-color: #b8272a; }
        .message { padding: 12px; margin: 15px 0; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .no-pending { color: #ffffff; font-size: 18px; margin-top: 20px; padding: 20px; background-color: transparent; border: 2px solid #dd353d; border-radius: 4px; text-align: center; }
        .back-link { margin: 20px 0; }
        .back-link a { background-color: #dd353d; color: #ffffff; text-decoration: none; font-size: 16px; padding: 10px 15px; border-radius: 4px; display: inline-block; }
        .back-link a:hover { background-color: #b8272a; }
        form { display: inline; }
    </style>
</head>
<body>
<div class="container">
    <h1>Pending Account Approvals</h1>
    <p>Review and approve new user sign-ups</p>
    
    <?php if (isset($message)): ?>
        <div class="message <?= $message_class ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (count($pending_accounts) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Signed Up</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_accounts as $account): ?>
                        <tr>
                            <td><?= htmlspecialchars($account['email']) ?></td>
                            <td><?= htmlspecialchars($account['username'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($account['first_name'] ?? 'N/A') ?></td>
                            <td><?= $account['created_at'] ?></td>
                            <td>
                                <div class="actions">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="sign_in_id" value="<?= $account['sign_in_id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="approve-btn">Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to reject this account?');">
                                        <input type="hidden" name="sign_in_id" value="<?= $account['sign_in_id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="reject-btn">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="no-pending">✓ No pending approvals. All accounts have been reviewed.</div>
    <?php endif; ?>

    <div class="back-link">
        <a href="admin.php">← Back to Admin Panel</a>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
