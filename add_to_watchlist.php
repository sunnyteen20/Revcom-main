<?php
session_start();
include "db.php";

if(isset($_POST['movie_id']) && isset($_SESSION['user_id'])){
    $u_id = $_SESSION['user_id'];
    $m_id = $_POST['movie_id'];
    $title = $_POST['title'];
    $poster = $_POST['poster'];

    // Check if it's already in the watchlist
    $check = $conn->prepare("SELECT watchlist_id FROM tbl_watchlist WHERE user_id = ? AND movie_id = ?");
    $check->bind_param("ii", $u_id, $m_id);
    $check->execute();
    $result = $check->get_result();
    
    if($result->num_rows > 0){
        // If it exists, remove it (Un-bookmark)
        $del = $conn->prepare("DELETE FROM tbl_watchlist WHERE user_id = ? AND movie_id = ?");
        $del->bind_param("ii", $u_id, $m_id);
        $del->execute();
        echo "removed";
    } else {
        // If it doesn't exist, add it
        $ins = $conn->prepare("INSERT INTO tbl_watchlist (user_id, movie_id, movie_title, poster_path) VALUES (?, ?, ?, ?)");
        $ins->bind_param("iiss", $u_id, $m_id, $title, $poster);
        $ins->execute();
        echo "added";
    }
}