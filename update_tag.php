<?php
require_once 'inc/user.php';

if (!empty($_POST['book_id']) && !empty($_POST['relationship']) && !empty($_SESSION['user_id'])) {
    // Prepare a query to insert or update the relationship
    $relationshipQuery = $db->prepare('
    INSERT INTO user_book_relationships (user_id, book_id, relationship) VALUES (:user_id, :book_id, :relationship)
    ON DUPLICATE KEY UPDATE relationship = :relationship
    ');
    $relationshipQuery->execute([
        ':user_id' => $_SESSION['user_id'],
        ':book_id' => $_POST['book_id'],
        ':relationship' => $_POST['relationship']
    ]);
    header('Location: book.php?id=' . $_POST['book_id']);
} else {
    exit('Invalid input.');
}
?>
