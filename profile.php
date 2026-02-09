<?php
session_start();
include "db.php";

// 1. Access Control: Redirect if not logged in
if(!isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit();
}

// 2. Identify whose profile to view (default to logged-in user if no ID in URL)
$profile_id = isset($_GET['id']) ? $_GET['id'] : $_SESSION['user_id'];
$is_owner = ($_SESSION['user_id'] == $profile_id);

// 3. Fetch User Details
$stmt = $conn->prepare("SELECT u.username, u.name, s.email FROM users u LEFT JOIN tbl_sign_in s ON u.sign_in_id = s.sign_in_id WHERE u.id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// 4. Privacy View: Mask sensitive info if the viewer is NOT the owner
$display_name = $is_owner ? $user['name'] : "Private User";
$display_email = $is_owner ? $user['email'] : "********@email.com";

// 5. Fetch Activity History (Reviews)
$activity_stmt = $conn->prepare("SELECT movie_title, review, created_at FROM tbl_movie_review WHERE user_id = ? ORDER BY created_at DESC");
$activity_stmt->bind_param("i", $profile_id);
$activity_stmt->execute();
$activities = $activity_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - <?= htmlspecialchars($user['username']); ?></title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,800" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: linear-gradient(to right, #dd353d, #210b0c); color: #fff; margin: 0; padding: 20px; }
        .container { background: #fff; color: #000; max-width: 600px; margin: 50px auto; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        h1 { border-bottom: 2px solid #dd353d; padding-bottom: 10px; }
        .info { margin-bottom: 30px; }
        .activity-item { background: #f9f9f9; padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 5px solid #dd353d; }
        .activity-item h4 { margin: 0 0 5px 0; color: #dd353d; }
        .date { font-size: 12px; color: #777; }
        .back-btn { display: inline-block; margin-top: 20px; text-decoration: none; color: #dd353d; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= htmlspecialchars($user['username']); ?>'s Profile</h1>
        
        <div class="info">
            <p><strong>Full Name:</strong> <?= htmlspecialchars($display_name); ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($display_email); ?></p>
        </div>

        <h3>Activity History</h3>
        <?php if($activities->num_rows > 0): ?>
            <?php while($row = $activities->fetch_assoc()): ?>
                <div class="activity-item">
                    <h4><?= htmlspecialchars($row['movie_title']); ?></h4>
                    <p>"<?= htmlspecialchars($row['review']); ?>"</p>
                    <span class="date">Reviewed on: <?= date('F j, Y', strtotime($row['created_at'])); ?></span>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No activity yet.</p>
        <?php endif; ?>

        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>
</body>
</html>