<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$api_key = "1163142a130a2e01a5fb73752ac05995";
$search_query = isset($_GET['search']) ? urlencode($_GET['search']) : '';
$genre = isset($_GET['genre']) ? $_GET['genre'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

// --- LOGIC FUNCTIONS ---
function fetchTmdbMovies($url) {
    $json = @file_get_contents($url);
    if ($json === FALSE) return [];
    $data = json_decode($json, true);
    return isset($data['results']) ? $data['results'] : [];
}

function getLocalReviewCounts($conn) {
    $counts = [];
    $res = $conn->query("SELECT movie_id, COUNT(*) as count FROM tbl_movie_review WHERE (is_deleted IS NULL OR is_deleted = 0) GROUP BY movie_id");
    while($row = $res->fetch_assoc()) { $counts[$row['movie_id']] = $row['count']; }
    return $counts;
}

function getLocalReviewRatings($conn) {
    $ratings = [];
    $res = $conn->query("SELECT movie_id, AVG(rating) as avg_rating FROM tbl_movie_review WHERE (is_deleted IS NULL OR is_deleted = 0) GROUP BY movie_id");
    while($row = $res->fetch_assoc()) { $ratings[$row['movie_id']] = round($row['avg_rating'], 1); }
    return $ratings;
}

function getUserWatchlist($conn, $u_id) {
    $watchlist = [];
    $res = $conn->query("SELECT movie_id FROM tbl_watchlist WHERE user_id = $u_id");
    while($row = $res->fetch_assoc()) { $watchlist[] = $row['movie_id']; }
    return $watchlist;
}

function sortMovies(&$movieArray, $sortType, $reviewCounts, $reviewRatings = []) {
    if (empty($movieArray)) return;
    usort($movieArray, function($a, $b) use ($sortType, $reviewCounts, $reviewRatings) {
        if ($sortType == 'asc') return strcmp($a['title'], $b['title']);
        if ($sortType == 'desc') return strcmp($b['title'], $a['title']);
        
        // --- UPDATED: 'pop' now sorts by local review COUNT (most reviewed first)
        // Tie-break: higher average user rating, then title
        if ($sortType == 'pop') {
            $countA = $reviewCounts[$a['id']] ?? 0;
            $countB = $reviewCounts[$b['id']] ?? 0;
            if ($countA === $countB) {
                $ratingA = $reviewRatings[$a['id']] ?? 0;
                $ratingB = $reviewRatings[$b['id']] ?? 0;
                if ($ratingA === $ratingB) return strcmp($a['title'], $b['title']);
                return $ratingB <=> $ratingA;
            }
            return $countB <=> $countA;
        }
        
        if ($sortType == 'rev') {
            $ratingA = $reviewRatings[$a['id']] ?? 0;
            $ratingB = $reviewRatings[$b['id']] ?? 0;
            return $ratingB <=> $ratingA;
        }
        return 0;
    });
}

// --- DATA FETCHING ---
$all_movies = []; 
$search_results = [];

if (!empty($search_query)) {
    $search_results = fetchTmdbMovies("https://api.themoviedb.org/3/search/movie?api_key=$api_key&query=$search_query&language=en-US&page=1");
} elseif (!empty($genre)) {
    $search_results = fetchTmdbMovies("https://api.themoviedb.org/3/discover/movie?api_key=$api_key&with_genres=$genre&language=en-US&page=1");
} else {
    // --- UPDATED: MERGE MULTIPLE LISTS FOR A BIGGER "ALL" CATEGORY ---
    $now_playing = fetchTmdbMovies("https://api.themoviedb.org/3/movie/now_playing?api_key=$api_key&language=en-US&page=1");
    $popular = fetchTmdbMovies("https://api.themoviedb.org/3/movie/popular?api_key=$api_key&language=en-US&page=1");
    $top_rated = fetchTmdbMovies("https://api.themoviedb.org/3/movie/top_rated?api_key=$api_key&language=en-US&page=1");
    
    $combined = array_merge($now_playing, $popular, $top_rated);
    
    $temp_ids = [];
    foreach($combined as $movie) {
        if(!in_array($movie['id'], $temp_ids)) {
            $temp_ids[] = $movie['id'];
            $all_movies[] = $movie;
        }
    }
}

$user_watchlist = getUserWatchlist($conn, $user_id);

if (!empty($sort)) {
    $reviewCounts = getLocalReviewCounts($conn);
    $reviewRatings = getLocalReviewRatings($conn);
    
    // If sorting by "Top Review", filter to only show movies that have reviews
    if ($sort == 'rev') {
        $search_results = array_filter($search_results, function($movie) use ($reviewCounts) {
            return isset($reviewCounts[$movie['id']]) && $reviewCounts[$movie['id']] > 0;
        });
        $all_movies = array_filter($all_movies, function($movie) use ($reviewCounts) {
            return isset($reviewCounts[$movie['id']]) && $reviewCounts[$movie['id']] > 0;
        });
    }
    
    // If sorting by "Most Popular", only include movies that have at least one local review
    if ($sort == 'pop') {
        $search_results = array_filter($search_results, function($movie) use ($reviewCounts) {
            return isset($reviewCounts[$movie['id']]) && $reviewCounts[$movie['id']] > 0;
        });
        $all_movies = array_filter($all_movies, function($movie) use ($reviewCounts) {
            return isset($reviewCounts[$movie['id']]) && $reviewCounts[$movie['id']] > 0;
        });
    }
    
    sortMovies($search_results, $sort, $reviewCounts, $reviewRatings);
    sortMovies($all_movies, $sort, $reviewCounts, $reviewRatings);
}

// Get average ratings and counts for all reviewed movies
$reviewRatings = getLocalReviewRatings($conn);
$reviewCounts = getLocalReviewCounts($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REVCOM - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;800&family=Quicksand:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #dd353d;
            --original-gradient: linear-gradient(to right, #210b0c, #dd353d, #210b0c);
        }

        body { 
            font-family: 'Montserrat', sans-serif; 
            background: var(--original-gradient);
            background-attachment: fixed;
            margin: 0; 
            padding-top: 170px; 
            color: #fff;
            overflow-x: hidden;
            width: 100%;
        }

        header { 
            position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; 
            background: rgba(0, 0, 0, 0.95); 
            padding: 15px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.6);
        }

        .header-wrap {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 40px;
            display: grid;
            grid-template-columns: 200px 1fr 200px; 
            align-items: center;
        }

        .brand { font-size: 26px; font-weight: 800; color: #fff; text-decoration: none; text-transform: uppercase; }
        .brand span { color: var(--primary-red); }

        .search-section {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            width: 100%;
        }
        .search-box { position: relative; width: 100%; max-width: 400px; }
        .search-input { 
            width: 100%; padding: 10px 45px 10px 20px; border-radius: 50px; 
            border: none; background: #fff; color: #333; font-weight: 700;
            font-family: 'Quicksand'; box-sizing: border-box;
        }
        .search-btn { 
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--primary-red); cursor: pointer;
        }

        .sort-select {
            width: 160px;
            padding: 10px 20px;
            background-color: #fff;
            color: var(--primary-red);
            font-family: 'Quicksand';
            font-size: 12px;
            font-weight: 800;
            border: none;
            border-radius: 50px;
            outline: none;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23e63946' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3e%3cpath d='M6 9l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 12px;
        }

        .user-actions { display: flex; align-items: center; justify-content: flex-end; gap: 12px; }
        .profile-pic {
            width: 38px; height: 38px; border-radius: 50%; border: 2px solid #fff;
            object-fit: cover; background: #fff;
        }
        .icon-btn { 
            background: #fff; color: var(--primary-red); width: 38px; height: 38px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            text-decoration: none; transition: 0.3s;
        }
        .icon-btn:hover { background: var(--primary-red); color: #fff; }

        .category-nav { 
            display: flex; justify-content: center; gap: 10px; margin-top: 15px; 
            flex-wrap: wrap; padding: 0 20px;
        }
        .cat-link { 
            background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2);
            text-decoration: none; font-size: 11px; font-weight: 700; padding: 6px 16px; 
            border-radius: 20px; transition: 0.3s;
        }
        .cat-link:hover, .cat-link.active { background: var(--primary-red); border-color: var(--primary-red); }

        .section-title { 
            width: 90%; max-width: 1400px; margin: 40px auto 10px; 
            font-size: 24px; font-weight: 800; border-left: 6px solid #fff; padding-left: 15px;
            text-transform: uppercase;
        }

        .movie-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
            gap: 30px; width: 90%; max-width: 1400px; margin: 0 auto; padding-bottom: 50px;
        }

        .movie-card { 
            background: #fff; color: #000; border-radius: 15px; 
            overflow: hidden; transition: 0.3s; position: relative;
        }
        .movie-card:hover { transform: translateY(-8px); box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .movie-card img { width: 100%; height: 320px; object-fit: cover; }
        
        .movie-info { padding: 15px; text-align: center; }
        .movie-info h3 { margin: 0 0 10px; font-size: 15px; height: 38px; overflow: hidden; font-weight: 800; }
        .movie-info p { margin: 0; font-size: 14px; color: var(--primary-red); font-weight: 700; }

        .btn-review { 
            display: block; width: 100%; margin-top: 15px; padding: 10px 0;
            background: var(--primary-red); color: #fff; border-radius: 50px;
            text-decoration: none; font-weight: 700; font-size: 12px; border: none;
        }

        .watchlist-btn {
            position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.9);
            color: var(--primary-red); width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; cursor: pointer; border: none;
            transition: 0.3s; z-index: 5;
        }
        .watchlist-btn.active { color: #dd353d; }
        .watchlist-btn.active i { font-weight: 900; }
    </style>
</head>
<body>

<header>
    <div class="header-wrap">
        <a href="dashboard.php" class="brand">REV<span>COM</span></a>
        
        <div class="search-section">
            <form action="dashboard.php" method="GET" class="search-box">
                <?php if($genre): ?><input type="hidden" name="genre" value="<?= $genre ?>"><?php endif; ?>
                <input type="text" name="search" class="search-input" placeholder="Search movies..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>

            <form action="dashboard.php" method="GET">
                <?php if(!empty($search_query)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($_GET['search']) ?>"><?php endif; ?>
                <?php if($genre): ?><input type="hidden" name="genre" value="<?= $genre ?>"><?php endif; ?>
                <select name="sort" class="sort-select" onchange="this.form.submit()">
                    <option value="">Sort</option>
                    <option value="asc" <?= $sort=='asc'?'selected':'' ?>>A-Z</option>
                    <option value="desc" <?= $sort=='desc'?'selected':'' ?>>Z-A</option>
                    <option value="pop" <?= $sort=='pop'?'selected':'' ?>>Most Popular</option>
                    <option value="rev" <?= $sort=='rev'?'selected':'' ?>>Top Review</option>
                </select>
            </form>
        </div>

        <div class="user-actions">
            <a href="watchlist.php" class="icon-btn" title="Watchlist"><i class="fa-solid fa-bookmark"></i></a>
            <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                <a href="admin.php" class="icon-btn" title="Admin Panel"><i class="fa-solid fa-shield"></i></a>
            <?php endif; ?>
            <a href="profile.php">
                <img src="https://ui-avatars.com/api/?name=<?= $_SESSION['username'] ?>&background=dd353d&color=fff" class="profile-pic" alt="Profile">
            </a>
            <a href="logout.php" class="icon-btn"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </div>

    <div class="category-nav">
        <a href="dashboard.php" class="cat-link <?= empty($genre)?'active':'' ?>">All</a>
        <a href="dashboard.php?genre=28" class="cat-link <?= $genre=='28'?'active':'' ?>">Action</a>
        <a href="dashboard.php?genre=35" class="cat-link <?= $genre=='35'?'active':'' ?>">Comedy</a>
        <a href="dashboard.php?genre=27" class="cat-link <?= $genre=='27'?'active':'' ?>">Horror</a>
        <a href="dashboard.php?genre=10749" class="cat-link <?= $genre=='10749'?'active':'' ?>">Romance</a>
        <a href="dashboard.php?genre=878" class="cat-link <?= $genre=='878'?'active':'' ?>">Sci-Fi</a>
        <a href="dashboard.php?genre=16" class="cat-link <?= $genre=='16'?'active':'' ?>">Animation</a>
    </div>
</header>

<main>
    <?php if (!empty($search_query) || !empty($genre)): ?>
        <h2 class="section-title"><?= !empty($genre) ? "Filtered Content" : "Search results" ?></h2>
        <div class="movie-grid">
            <?php foreach($search_results as $movie): 
                $is_in_watchlist = in_array($movie['id'], $user_watchlist);
            ?>
                <div class="movie-card" 
                     data-id="<?= $movie['id'] ?>" 
                     data-title="<?= htmlspecialchars($movie['title']) ?>" 
                     data-poster="<?= $movie['poster_path'] ?>">
                    
                    <button class="watchlist-btn <?= $is_in_watchlist ? 'active' : '' ?>">
                        <i class="<?= $is_in_watchlist ? 'fa-solid' : 'fa-regular' ?> fa-bookmark"></i>
                    </button>

                    <img src="<?= $movie['poster_path'] ? "https://image.tmdb.org/t/p/w500".$movie['poster_path'] : "https://via.placeholder.com/500x750?text=No+Image" ?>" alt="Poster">
                    <div class="movie-info">
                        <h3><?= $movie['title'] ?></h3>
                        <?php $rc = $reviewCounts[$movie['id']] ?? 0; ?>
                        <p>⭐ Rev Rating: <?php if($rc){ echo (isset($reviewRatings[$movie['id']]) ? $reviewRatings[$movie['id']] : 'N/A') . ' (' . $rc . ' ' . ($rc==1? 'review' : 'reviews') . ')'; } else { echo 'N/A'; } ?></p>
                        <p>TMDB Rating: <?= (isset($movie['vote_average']) && $movie['vote_average']) ? number_format($movie['vote_average'], 1) : 'N/A' ?></p>
                        <a href="reviews.php?movie_id=<?= $movie['id'] ?>" class="btn-review" style="text-align: center; text-decoration:none;">Review</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>

        <h2 class="section-title">Trending</h2>
        <div class="movie-grid">
            <?php foreach($all_movies as $movie): 
                $is_in_watchlist = in_array($movie['id'], $user_watchlist);
            ?>
                <div class="movie-card" 
                     data-id="<?= $movie['id'] ?>" 
                     data-title="<?= htmlspecialchars($movie['title']) ?>" 
                     data-poster="<?= $movie['poster_path'] ?>">
                    
                    <button class="watchlist-btn <?= $is_in_watchlist ? 'active' : '' ?>">
                        <i class="<?= $is_in_watchlist ? 'fa-solid' : 'fa-regular' ?> fa-bookmark"></i>
                    </button>

                    <img src="https://image.tmdb.org/t/p/w500<?= $movie['poster_path'] ?>" alt="Poster">
                    <div class="movie-info">
                        <h3><?= $movie['title'] ?></h3>
                        <?php $rc = $reviewCounts[$movie['id']] ?? 0; ?>
                        <p>⭐ Rev Rating: <?php if($rc){ echo (isset($reviewRatings[$movie['id']]) ? $reviewRatings[$movie['id']] : 'N/A') . ' (' . $rc . ' ' . ($rc==1? 'review' : 'reviews') . ')'; } else { echo 'N/A'; } ?></p>
                        <p>🍿 TMDB Rating: <?= (isset($movie['vote_average']) && $movie['vote_average']) ? number_format($movie['vote_average'], 1) : 'N/A' ?></p>
                        <a href="reviews.php?movie_id=<?= $movie['id'] ?>" class="btn-review" style="text-align: center; text-decoration:none;">Review</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</main>

<script src="script.js"></script>
</body>
</html>