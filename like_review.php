<?php
require_once 'inc/user.php';

if (!empty($_POST['review_id']) && !empty($_SESSION['user_id'])) {
    $review_id = $_POST['review_id'];
    $user_id = $_SESSION['user_id'];
    
    // Check if the user has already liked the review
    $checkLikeQuery = $db->prepare('SELECT like_id FROM review_likes WHERE review_id = :review_id AND user_id = :user_id LIMIT 1');
    $checkLikeQuery->execute([':review_id' => $review_id, ':user_id' => $user_id]);
    $existingLike = $checkLikeQuery->fetch(PDO::FETCH_ASSOC);

    if ($existingLike) {
        // Unlike the review
        $deleteLikeQuery = $db->prepare('DELETE FROM review_likes WHERE like_id = :like_id');
        $deleteLikeQuery->execute([':like_id' => $existingLike['like_id']]);
    } else {
        // Like the review
        $insertLikeQuery = $db->prepare('INSERT INTO review_likes (review_id, user_id) VALUES (:review_id, :user_id)');
        $insertLikeQuery->execute([':review_id' => $review_id, ':user_id' => $user_id]);
    }

    // Get the updated like count
    $likeCountQuery = $db->prepare('SELECT COUNT(*) AS like_count FROM review_likes WHERE review_id = :review_id');
    $likeCountQuery->execute([':review_id' => $review_id]);
    $likeCount = $likeCountQuery->fetch(PDO::FETCH_ASSOC)['like_count'];

    echo json_encode(['success' => true, 'likes' => $likeCount]);
} else {
    echo json_encode(['success' => false]);
}
?>