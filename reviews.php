<?php
session_start();
include "db.php";

// 1. SET TIMEZONE
date_default_timezone_set('Asia/Manila'); 

$message = "";

// 2. Access Control
if(!isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit();
}

// 3. Validate Movie ID
if(!isset($_GET['movie_id'])){
    header("Location: dashboard.php");
    exit();
}

$movie_id = $_GET['movie_id'];
$user_id = $_SESSION['user_id'];

// --- FIXED FUNCTION: Time Ago ---
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Calculate values manually to avoid PHP 8.2+ errors
    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    // Map values to labels
    $string = array(
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    );

    $labels = array(
        'y' => 'year', 'm' => 'month', 'w' => 'week',
        'd' => 'day', 'h' => 'hr', 'i' => 'min', 's' => 'sec',
    );

    foreach ($string as $k => $v) {
        if ($v > 0) {
            $string[$k] = $v . ' ' . $labels[$k] . ($v > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$string) return 'just now';
    $string = array_slice($string, 0, 1);
    return implode(', ', $string) . ' ago';
}

// 4. Handle Form Submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['review']) && trim($_POST['review']) !== '') {
        $review = trim($_POST['review']);
        $movie_title = $_POST['movie_title']; 
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;

        // ✅ Ensure movie exists in tbl_movie_list (cache table)
        $movie_check = $conn->prepare("SELECT movie_id FROM tbl_movie_list WHERE movie_id = ?");
        $movie_check->bind_param("i", $movie_id);
        $movie_check->execute();
        $movie_check->store_result();

        if($movie_check->num_rows === 0) {
            // Movie doesn't exist in cache, insert it
            $poster_path = isset($_POST['poster_path']) ? $_POST['poster_path'] : NULL;
            $insert_movie = $conn->prepare("INSERT IGNORE INTO tbl_movie_list (movie_id, title, poster_path) VALUES (?, ?, ?)");
            $insert_movie->bind_param("iss", $movie_id, $movie_title, $poster_path);
            $insert_movie->execute();
            $insert_movie->close();
        }
        $movie_check->close();

        // ✅ Check if user already reviewed this movie
        $check_stmt = $conn->prepare("SELECT id FROM tbl_movie_review WHERE user_id = ? AND movie_id = ?");
        $check_stmt->bind_param("ii", $user_id, $movie_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if($check_stmt->num_rows > 0){
    // User already reviewed → update review instead
    $update_stmt = $conn->prepare("UPDATE tbl_movie_review SET review = ?, rating = ?, created_at = NOW() WHERE user_id = ? AND movie_id = ?");
    $update_stmt->bind_param("siii", $review, $rating, $user_id, $movie_id);

    if($update_stmt->execute()){
        $message = "Your review has been updated!";
    } else {
        $message = "Failed to update review: " . $conn->error;
    }
    $update_stmt->close();
}
 else {
            // Insert new review
            $stmt = $conn->prepare("INSERT INTO tbl_movie_review (user_id, movie_id, movie_title, review, rating) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $user_id, $movie_id, $movie_title, $review, $rating);

            if($stmt->execute()){
                $message = "Review published successfully!";
            } else {
                $message = "Failed to submit review: " . $conn->error;
            }
            $stmt->close();
        }

        $check_stmt->close();
    } else {
        $message = "Please write a review before submitting.";
    }
}


if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $message = "Review published successfully!";
}

// 5. Fetch Reviews
$sql = "SELECT r.review, r.rating, r.created_at, u.username 
    FROM tbl_movie_review r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.movie_id = ? AND (r.is_deleted IS NULL OR r.is_deleted = 0)
    ORDER BY r.created_at DESC";

$stmt_fetch = $conn->prepare($sql);
$stmt_fetch->bind_param("i", $movie_id);
$stmt_fetch->execute();
$result_reviews = $stmt_fetch->get_result();
$existing_reviews = [];
while($row = $result_reviews->fetch_assoc()) {
    $existing_reviews[] = $row;
}
$stmt_fetch->close();

// 6. Fetch Movie Data
$api_key = "1163142a130a2e01a5fb73752ac05995";
$tmdb_url = "https://api.themoviedb.org/3/movie/$movie_id?api_key=$api_key&language=en-US";
$movie_json = @file_get_contents($tmdb_url);
$movie = json_decode($movie_json, true);

if(!$movie || isset($movie['status_code'])) {
    $movie = ['title' => 'Unknown Movie', 'poster_path' => null];
}

// 7. RECOMMENDATION ALGORITHM
$sim_url = "https://api.themoviedb.org/3/movie/$movie_id/similar?api_key=$api_key&language=en-US&page=1";
$sim_json = @file_get_contents($sim_url);
$sim_data = json_decode($sim_json, true);
$similar_movies = isset($sim_data['results']) ? array_slice($sim_data['results'], 0, 5) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reviews - <?= htmlspecialchars($movie['title']); ?></title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,800" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { box-sizing: border-box; }
        
        body {
            background: url('assets/background.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Montserrat', sans-serif;
            margin: 0;
            padding: 0;
            color: #fff;
            min-height: 100vh;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: -1;
        }

        .page-wrapper {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .main-layout {
            display: flex;
            gap: 30px;
            width: 100%;
            align-items: flex-start;
            margin-bottom: 50px;
        }

        .form-container {
            flex: 1;
            background-color: #fff;
            color: #000;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            position: sticky;
            top: 30px;
        }

        .comments-container {
            flex: 1.5;
            background-color: rgba(255, 255, 255, 0.95);
            color: #000;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            max-height: 80vh;
            overflow-y: auto;
        }

        h1 { font-family: 'Quicksand', sans-serif; font-size: 22px; font-weight: 800; margin: 10px 0; color: #210b0c; }
        
        img.poster {
            width: 120px; height: auto; border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3); margin-bottom: 15px;
        }

        .star-rating {
            display: flex; 
            flex-direction: row-reverse; 
            justify-content: center; 
            gap: 2px; /* Reduced gap for 10 stars */
            margin-bottom: 15px;
        }
        .star-rating input { display: none; }
        .star-rating label { 
            font-size: 20px; /* Smaller font to fit 10 stars */
            color: #ccc; 
            cursor: pointer; 
            transition: color 0.2s; 
            width: 20px; /* Fixed width for alignment */
            text-align: center;
        }
        .star-rating input:checked ~ label, 
        .star-rating label:hover, 
        .star-rating label:hover ~ label { 
            color: #ffc107; 
        }

        form textarea {
            width: 100%; padding: 15px; border-radius: 15px; border: 2px solid #eee;
            background: #f9f9f9; box-sizing: border-box; font-size: 14px;
            resize: vertical; min-height: 120px; font-family: 'Quicksand', sans-serif; outline: none; transition: 0.3s;
        }
        form textarea:focus { border-color: #dd353d; background: #fff; }

        button {
            width: 100%; padding: 12px; border-radius: 50px; border: none;
            background: linear-gradient(135deg, #dd353d 0%, #b02a30 100%);
            color: #fff; font-family: 'Quicksand', sans-serif; font-weight: 700;
            font-size: 15px; text-transform: uppercase; cursor: pointer;
            margin-top: 15px; transition: 0.3s; box-shadow: 0 4px 10px rgba(221, 53, 61, 0.4);
        }
        button:hover { background: linear-gradient(135deg, #ff4d55 0%, #dd353d 100%); transform: translateY(-2px); }

        .message { margin-top: 15px; font-weight: 600; font-size: 14px; }
        .success { color: #28a745; }
        .warning { color: #dd353d; }

        .comments-header {
            font-family: 'Quicksand', sans-serif; font-size: 20px; font-weight: 700;
            margin-bottom: 20px; color: #dd353d; border-bottom: 2px solid #eee;
            padding-bottom: 10px; position: sticky; top: 0;
            background: rgba(255,255,255,0.95); z-index: 10;
        }

        .single-review {
            background: #f4f4f4; padding: 20px; border-radius: 12px;
            margin-bottom: 15px; font-size: 14px; line-height: 1.5; border-left: 4px solid #dd353d;
            position: relative;
        }

        .user-name {
            font-weight: 800; color: #210b0c; font-size: 14px;
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;
        }

        .stars-display { color: #ffc107; font-size: 12px; margin-left: 8px; }
        
        .review-date { 
            color: #888; font-weight: 400; font-size: 11px; text-align: right;
        }

        .empty-state { text-align: center; color: #888; font-style: italic; padding: 40px; }

        .back-link-float {
            position: fixed; top: 20px; left: 20px;
            background: rgba(0,0,0,0.6); padding: 10px 20px;
            border-radius: 50px; color: #fff; text-decoration: none; font-weight: bold;
            font-size: 14px; backdrop-filter: blur(5px); transition: 0.3s; z-index: 100;
        }
        .back-link-float:hover { background: #dd353d; }

        .comments-container::-webkit-scrollbar { width: 8px; }
        .comments-container::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .comments-container::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }
        .comments-container::-webkit-scrollbar-thumb:hover { background: #dd353d; }

        .rec-section { width: 100%; }
        .rec-title {
            font-size: 20px; font-weight: 800; text-transform: uppercase;
            margin-bottom: 20px; border-left: 5px solid #dd353d; padding-left: 15px;
            text-shadow: 0 2px 5px rgba(0,0,0,0.5);
        }
        .rec-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;
        }
        .rec-card {
            background: #fff; color: #000; border-radius: 15px; overflow: hidden;
            transition: 0.3s; cursor: pointer; text-decoration: none;
            display: flex; flex-direction: column; height: 100%;
        }
        .rec-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.5); }
        .rec-card img { width: 100%; height: 280px; object-fit: cover; }
        .rec-card h3 {
            font-size: 15px; font-weight: 700; margin: 10px; text-align: center;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }

        @media (max-width: 850px) {
            .main-layout { flex-direction: column; align-items: stretch; }
            .form-container { position: static; }
            .comments-container { max-height: 500px; }
        }
    </style>
</head>
<body>

<a href="dashboard.php" class="back-link-float">
    <i class="fa-solid fa-house"></i> Dashboard
</a>

<a href="javascript:history.back()" class="back-link-float" style="top: 80px;">
    <i class="fa-solid fa-arrow-left"></i> Go Back
</a>

<div class="page-wrapper">

    <div class="main-layout">
        <div class="form-container">
            <?php if($movie['poster_path']): ?>
                <img class="poster" src="https://image.tmdb.org/t/p/w200<?= $movie['poster_path']; ?>" alt="Poster">
            <?php endif; ?>

            <h1><?= htmlspecialchars($movie['title']); ?></h1>
            
            <form method="POST">
                <input type="hidden" name="movie_id" value="<?= $movie_id; ?>">
                <input type="hidden" name="movie_title" value="<?= htmlspecialchars($movie['title']); ?>">
                <input type="hidden" name="poster_path" value="<?= isset($movie['poster_path']) ? htmlspecialchars($movie['poster_path']) : ''; ?>">
                
            <div class="star-rating">
                <?php for($i = 10; $i >= 1; $i--): ?>
                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" />
                    <label for="star<?= $i ?>" title="<?= $i ?> stars">★</label>
                <?php endfor; ?>
            </div>
                
                <textarea name="review" placeholder="Write your review here..." required></textarea>
                <button type="submit">Publish Review <i class="fa-solid fa-paper-plane"></i></button>
            </form>

            <?php if($message): ?>
                <p class="message <?= (strpos($message, 'successfully') !== false) ? 'success' : 'warning' ?>">
                    <?= $message ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="comments-container">
            <div class="comments-header">
                <i class="fa-solid fa-comments"></i> Community Reviews
            </div>

            <?php if(count($existing_reviews) > 0): ?>
                <?php foreach($existing_reviews as $rev): ?>
                    <div class="single-review">
                        <div class="user-name">
                            <span>
                                <i class="fa-solid fa-user-circle"></i> <?= htmlspecialchars($rev['username']); ?>
                                <span class="stars-display">
                                    <?php for($i=0; $i<$rev['rating']; $i++) echo '★'; ?>
                                </span>
                            </span>
                            
                            <span class="review-date">
                                <?= date("M d, Y • h:i A", strtotime($rev['created_at'])); ?>
                                <br>
                                (<?= time_elapsed_string($rev['created_at']); ?>)
                            </span>
                        </div>
                        <?= nl2br(htmlspecialchars($rev['review'])); ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-regular fa-comment-dots" style="font-size: 40px; margin-bottom: 15px;"></i><br>
                    No reviews yet.<br>Be the first to share your thoughts!
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if(!empty($similar_movies)): ?>
    <div class="rec-section">
        <div class="rec-title">
            Based on "<?= htmlspecialchars($movie['title']); ?>", you might also like...
        </div>
        <div class="rec-grid">
            <?php foreach($similar_movies as $sim): ?>
                <a href="reviews.php?movie_id=<?= $sim['id']; ?>" class="rec-card">
                    <?php $img = $sim['poster_path'] ? "https://image.tmdb.org/t/p/w500".$sim['poster_path'] : "https://via.placeholder.com/200x300?text=No+Image"; ?>
                    <img src="<?= $img; ?>" alt="Poster">
                    <h3><?= $sim['title']; ?></h3>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>