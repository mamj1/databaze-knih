<?php
require_once 'inc/user.php';

if (!empty($_POST['book_id']) && !empty($_POST['rating']) && !empty($_POST['comment']) && !empty($_SESSION['user_id'])) {
    $book_id = $_POST['book_id'];
    $user_id = $_SESSION['user_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];

    // Check if the user has already reviewed the book
    $checkReviewQuery = $db->prepare('SELECT id FROM reviews WHERE user_id = :user_id AND book_id = :book_id LIMIT 1');
    $checkReviewQuery->execute([':user_id' => $user_id, ':book_id' => $book_id]);
    $existingReview = $checkReviewQuery->fetch(PDO::FETCH_ASSOC);

    if ($existingReview) {
        // Update the existing review
        $updateReviewQuery = $db->prepare('
            UPDATE reviews 
            SET rating = :rating, comment = :comment, created_at = NOW()
            WHERE id = :id
        ');
        $updateReviewQuery->execute([
            ':rating' => $rating,
            ':comment' => $comment,
            ':id' => $existingReview['id']
        ]);
    } else {
        // Insert a new review
        $insertReviewQuery = $db->prepare('
            INSERT INTO reviews (book_id, user_id, rating, comment, created_at) 
            VALUES (:book_id, :user_id, :rating, :comment, NOW())
        ');
        $insertReviewQuery->execute([
            ':book_id' => $book_id,
            ':user_id' => $user_id,
            ':rating' => $rating,
            ':comment' => $comment
        ]);
    }

    header('Location: book.php?id=' . $book_id);
} else {
    exit('Invalid input.');
}
?>
