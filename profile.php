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
$stmt = $conn->prepare("SELECT u.username, u.first_name, u.middle_name, u.surname, u.suffix, s.email FROM tbl_users u LEFT JOIN tbl_sign_in s ON u.sign_in_id = s.sign_in_id WHERE u.user_id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update (owner only)
if ($is_owner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $surname = isset($_POST['surname']) ? trim($_POST['surname']) : '';
    $suffix = isset($_POST['suffix']) ? trim($_POST['suffix']) : '';
    if (!empty($first_name)) {
        $up = $conn->prepare("UPDATE tbl_users SET first_name = ?, middle_name = ?, surname = ?, suffix = ? WHERE user_id = ?");
        $up->bind_param("ssssi", $first_name, $middle_name, $surname, $suffix, $profile_id);
        $up->execute();
        // refresh user data
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    }
}

$full_name = trim(implode(' ', array_filter([$user['first_name'], $user['middle_name'], $user['surname'], $user['suffix']])));
$display_name = $is_owner ? $full_name : "Private User";
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
            <?php if ($is_owner): ?>
                <form method="POST">
                    <label>First name (required)</label>
                    <input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>" />
                    <label>Middle name (optional)</label>
                    <input type="text" name="middle_name" value="<?= htmlspecialchars($user['middle_name']) ?>" />
                    <label>Surname (optional)</label>
                    <input type="text" name="surname" value="<?= htmlspecialchars($user['surname']) ?>" />
                    <label>Suffix (optional)</label>
                    <input type="text" name="suffix" value="<?= htmlspecialchars($user['suffix']) ?>" />
                    <button type="submit">Save</button>
                </form>
                <p><strong>Email:</strong> <?= htmlspecialchars($display_email); ?></p>
            <?php else: ?>
                <p><strong>Full Name:</strong> <?= htmlspecialchars($display_name); ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($display_email); ?></p>
            <?php endif; ?>
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