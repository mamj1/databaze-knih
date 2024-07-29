<?php
// Načtení připojení k databázi a inicializace session
require_once 'inc/user.php';

// Vložení hlavičky
include 'inc/header.php';

// Vložení CSS 
echo '<link rel="stylesheet" type="text/css" href="style.css">';

// Kontrola, zda je v požadavku uvedeno ID knihy
if (!empty($_REQUEST['id'])) {
    // Příprava dotazu pro načtení detailů knihy spolu se jménem autora z přidružené tabulky
    $bookQuery = $db->prepare('
    SELECT books.book_id, books.book_name AS book_name, users.name AS author_name, books.text AS book_text, books.user_id AS author_id, categories.name AS category_name
    FROM books 
    JOIN users ON books.user_id = users.user_id 
    JOIN categories ON books.category_id = categories.category_id
    WHERE books.book_id = :id LIMIT 1;
    ');
    $bookQuery->execute([':id' => $_REQUEST['id']]);
    
    // Načtení detailů knihy
    if ($book = $bookQuery->fetch(PDO::FETCH_ASSOC)) {
        echo '<div class="content-wrapper">';
        echo '<div class="book-details">';
       // Zobrazení názvu knihy a jména autora
        echo '<h1>' . htmlspecialchars($book['book_name']) . '</h1>';
        echo '<p>Author: ' . htmlspecialchars($book['author_name']) . '</p>';
        echo '<p>Žánr: ' . htmlspecialchars($book['category_name']) . '</p>';
        echo '<p>' . nl2br(htmlspecialchars($book['book_text'])) . '</p>';

         // Kontrola, zda je uživatel přihlášen
        if (!empty($_SESSION['user_id'])) {
            // Načtení aktuálního vztahu, pokud existuje
            $relationshipQuery = $db->prepare('
            SELECT relationship FROM user_book_relationships
            WHERE user_id = :user_id AND book_id = :book_id
            LIMIT 1
            ');
            $relationshipQuery->execute([
                ':user_id' => $_SESSION['user_id'],
                ':book_id' => $book['book_id']
            ]);
            $relationship = $relationshipQuery->fetch(PDO::FETCH_ASSOC)['relationship'] ?? '';
            // Zobrazení tlačítek pro vztah ke knize
            echo '<div class="relationship-buttons">
                <button type="button" data-value="Have Read"' . ($relationship == 'Have Read' ? ' class="active"' : '') . '>Have Read</button>
                <button type="button" data-value="Want to Read"' . ($relationship == 'Want to Read' ? ' class="active"' : '') . '>Want to Read</button>
                <button type="button" data-value="Have Bought"' . ($relationship == 'Have Bought' ? ' class="active"' : '') . '>Have Bought</button>
                <button type="button" data-value="Will Buy"' . ($relationship == 'Will Buy' ? ' class="active"' : '') . '>Will Buy</button>
            </div>';
            // JS a AJAX pro like a realtionship
            echo '<script>
            document.querySelectorAll(".relationship-buttons button").forEach(button => {
                button.addEventListener("click", function() {
                    const isActive = this.classList.contains("active");
                    document.querySelectorAll(".relationship-buttons button").forEach(btn => btn.classList.remove("active"));
                    const relationship = isActive ? "" : this.getAttribute("data-value");
                    if (!isActive) {
                        this.classList.add("active");
                    }

                    const bookId = ' . $book['book_id'] . ';

                    fetch("update_tag.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: "book_id=" + bookId + "&relationship=" + relationship
                    })
                    .then(response => response.text())
                    .then(data => {
                        console.log(data); 
                    })
                    .catch(error => {
                        console.error("Error:", error);
                    });
                });
            });

            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".like-button").forEach(button => {
                    button.addEventListener("click", function() {
                        const reviewId = this.getAttribute("data-review-id");
                        fetch("like_review.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            },
                            body: "review_id=" + reviewId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.classList.toggle("liked");
                                this.nextElementSibling.textContent = data.likes + " likes";
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                        });
                    });
                });
            });
        </script>';
    }
         
        echo "</div>";
         

         

       

        // Zobrazení review formy pokud je uživatel přihlašen
        if (!empty($_SESSION['user_id'])) {
            echo '<div class="review-form-wrapper">';
            echo '<form class="review-form" action="add_review.php" method="post">
                <input type="hidden" name="book_id" value="' . htmlspecialchars($book['book_id']) . '">
                <label for="rating">Rating (1-5):</label>
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5" required /><label for="star5" title="5 stars">★</label>
                    <input type="radio" id="star4" name="rating" value="4" required /><label for="star4" title="4 stars">★</label>
                    <input type="radio" id="star3" name="rating" value="3" required /><label for="star3" title="3 stars">★</label>
                    <input type="radio" id="star2" name="rating" value="2" required /><label for="star2" title="2 stars">★</label>
                    <input type="radio" id="star1" name="rating" value="1" required /><label for="star1" title="1 star">★</label>
                </div>
                <label for="comment">komentář:</label>
                <textarea id="comment" name="comment" required></textarea>
                <button type="submit">Přidat ohodnocení</button>
            </form>';
            echo '</div>'; 
        }
        
        echo '</div>'; 

        // Kontrola zda uživatel je autor knihy
        if (!empty($_SESSION['user_id'])) {
            if ($_SESSION['user_id'] == $book['author_id']) {
                echo '<a href="edit.php?id=' . htmlspecialchars($book['author_id']) . '" style="color: black; text-decoration: none;">Upravit</a>';
            }
        }

        // Zobrazení review pokud existují
        $reviews = [];
        $reviewQuery = $db->prepare('
        SELECT reviews.id, reviews.rating, reviews.comment, reviews.created_at, users.name AS reviewer_name,
          (SELECT COUNT(*) FROM review_likes WHERE review_likes.review_id = reviews.id) AS like_count,
          (SELECT COUNT(*) FROM review_likes WHERE review_likes.review_id = reviews.id AND review_likes.user_id = :user_id) AS user_liked,
          (SELECT GROUP_CONCAT(u.name SEPARATOR ", ") 
           FROM review_likes rl 
           JOIN users u ON rl.user_id = u.user_id 
           WHERE rl.review_id = reviews.id) AS liked_users
            FROM reviews
             JOIN users ON reviews.user_id = users.user_id
            WHERE reviews.book_id = :book_id
            ORDER BY reviews.created_at DESC');
        $reviewQuery->execute([':book_id' => $book['book_id'], ':user_id' => $_SESSION['user_id'] ?? 0]);
        $reviews = $reviewQuery->fetchAll(PDO::FETCH_ASSOC);

       
        if (count($reviews) > 0) {
            echo '<div class="review-section">'; 
            echo '<h2>Reviews:</h2>';
            foreach ($reviews as $review) {
                $liked = $review['user_liked'] > 0 ? ' liked' : '';
                echo '<div class="review">';
                echo '<p><strong>' . htmlspecialchars($review['reviewer_name']) . ':</strong> ';
                echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) . '</p>';
                echo '<p>' . nl2br(htmlspecialchars($review['comment'])) . '</p>';
                echo '<p><small>Komentář přidán: ' . htmlspecialchars($review['created_at']) . '</small></p>';
                echo '<div class="like-section">';
                echo '<button class="like-button' . $liked . '" data-review-id="' . $review['id'] . '">❤️</button>';
                echo '<span class="like-count">' . $review['like_count'] . ' likes</span>';
                if (!empty($review['liked_users'])) {
                    echo '<p class="liked-users">Liked by: ' . htmlspecialchars($review['liked_users']) . '</p>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }

        
    } else {
        exit('Book not found.');
    }
} else {
    exit('ID not provided.');
}
?>
