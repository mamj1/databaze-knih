<?php
// Načtení připojení k databázi a inicializace session
require_once 'inc/user.php';

// Vložení hlavičky
include 'inc/header.php';

echo '<link rel="stylesheet" type="text/css" href="style.css">';

$userLoggedIn = isset($_SESSION['user_id']);
$userId = $userLoggedIn ? $_SESSION['user_id'] : null;

// Nastavení výchozího řazení na abecední
$sortOption = isset($_GET['sort']) ? $_GET['sort'] : 'alphabetical';
$categoryOption = isset($_GET['category']) ? $_GET['category'] : '';


$searchQuery = isset($_GET['query']) ? trim($_GET['query']) : '';


// Sestavení SQL dotazu podle zvoleného řazení
$sortQuery = '';
switch ($sortOption) {
    case 'rating':
        $sortQuery = 'ORDER BY average_rating DESC, book_name ASC';
        break;
    case 'alphabetical':
        $sortQuery = 'ORDER BY book_name ASC';
        break;
    default:
        $sortQuery = 'ORDER BY book_name ASC';
        break;
}

// Building the base query
$queryString = '
    SELECT
        books.*, users.name AS user_name, users.email, categories.name AS category_name,
        AVG(reviews.rating) AS average_rating' .
        ($userLoggedIn ? ', ubr.relationship' : '') . '
    FROM books 
    JOIN users USING (user_id) 
    JOIN categories USING (category_id)
    LEFT JOIN reviews ON books.book_id = reviews.book_id' .
    ($userLoggedIn ? ' LEFT JOIN user_book_relationships ubr ON books.book_id = ubr.book_id AND ubr.user_id = :user_id' : '') . '
    WHERE 1=1 ';

// Adding category filter if selected
$params = [];
if (!empty($categoryOption)) {
    $queryString .= ' AND books.category_id = :category';
    $params[':category'] = $categoryOption;
}

// Adding search filter if provided
if (!empty($searchQuery)) {
    $queryString .= ' AND (books.book_name LIKE :search OR users.name LIKE :search)';
    $params[':search'] = '%' . $searchQuery . '%';
}

// Adding sorting
$queryString .= ' GROUP BY books.book_id ' . $sortQuery;

// Preparing and executing the query
$query = $db->prepare($queryString);
if ($userLoggedIn) {
    $params[':user_id'] = $userId;
}
$query->execute($params);

// Input pro vyhledávání
echo '<form method="get" id="searchForm" style="margin:5px;">
        <input type="hidden" name="sort" value="' . htmlspecialchars($sortOption) . '">
        <input type="hidden" name="category" value="' . htmlspecialchars($categoryOption) . '">
        <input style="width:280px" type="text" name="query" placeholder="Vyhledejte jméno knihy či autora" value="' . htmlspecialchars($searchQuery) . '">
        <button type="submit">Vyhledat</button>
      </form>';

// DDMenu pro výběr kategorie
echo '<form method="get" id="categoryFilterForm">
        <input type="hidden" name="sort" value="' . htmlspecialchars($sortOption) . '">
        <input type="hidden" name="query" value="' . htmlspecialchars($searchQuery) . '">
        <label for="category">Kategorie:</label>
        <select name="category" id="category" onchange="document.getElementById(\'categoryFilterForm\').submit();">
          <option value="">Nerozhoduje</option>';

$categories = $db->query('SELECT * FROM categories ORDER BY name;')->fetchAll(PDO::FETCH_ASSOC);
if (!empty($categories)) {
  foreach ($categories as $category) {
    echo '<option value="' . $category['category_id'] . '"';
    if ($category['category_id'] == $categoryOption) {
      echo ' selected="selected" ';
    }
    echo '>' . htmlspecialchars($category['name']) . '</option>';
  }
}

echo '  </select>
        <input type="submit" value="OK" class="d-none" />
      </form>';

// DDMenu pro řazení
echo '<form method="get" id="sortFilterForm">
        <input type="hidden" name="category" value="' . htmlspecialchars($categoryOption) . '">
        <input type="hidden" name="query" value="' . htmlspecialchars($searchQuery) . '">
        <label for="sort">Řadit podle:</label>
        <select name="sort" id="sort" onchange="document.getElementById(\'sortFilterForm\').submit();">
          <option value="alphabetical"' . ($sortOption == 'alphabetical' ? ' selected' : '') . '>Abecedy</option>
          <option value="rating"' . ($sortOption == 'rating' ? ' selected' : '') . '>Hodnocení</option>
        </select>
        <input type="submit" value="OK" class="d-none" />
      </form>';

$books = $query->fetchAll(PDO::FETCH_ASSOC);
if (!empty($books)) {
  // Výpis knih
  echo '<div class="book-list">';
  foreach ($books as $book) {
    echo '<div class="book-card">';
    echo '  <div class="book-title"><a href="book.php?id=' . $book['book_id'] . '">' . htmlspecialchars($book['book_name']) . '</a></div>';
    if (!empty($_SESSION['user_id'])) {
      if (!empty($book['relationship'])) {
        echo ' <div class="relationship-tag">' . htmlspecialchars($book['relationship']) . '</div>';
      }
    }
    echo '  <div class="book-author">Autor: ' . htmlspecialchars($book['user_name']) . '</div>';
    if (empty($book['average_rating'])) {
      echo '  <div class="book-rating">Průměrné hodnocení: 0 / 5</div>';
    } else {
      echo '  <div class="book-rating">Průměrné hodnocení: ' . round($book['average_rating'], 2) . ' / 5</div>';
    }
    echo '  <div class="book-text">' . htmlspecialchars($book['text']) . '</div>';
    if (!empty($_SESSION['user_id'])) {
      if ($_SESSION['user_id'] == $book['user_id']) {
        echo ' - <a href="edit.php?id=' . $book['book_id'] . '" class="text-danger">upravit</a>';
      }
    }
    echo '</div>';
  }
  echo '</div>';
} else {
  echo '<div class="alert alert-info">Nebyly nalezeny žádné knihy.</div>';
}

if (!empty($_SESSION['user_id']) && $_SESSION['user_role'] == "autor") {
    echo '<div class="row my-3">
           <a href="edit.php?category=' . $categoryOption . '" class="btn btn-primary">Přidat knihu</a>
          </div>';
}

if ($userLoggedIn && $_SESSION['user_role'] == "autor") {
  $newestReviewsQuery = '
      SELECT reviews.*, books.book_name, users.name AS reviewer_name
      FROM reviews
      JOIN books ON reviews.book_id = books.book_id
      JOIN users ON reviews.user_id = users.user_id
      WHERE books.user_id = :author_id
      ORDER BY reviews.created_at DESC
      LIMIT 3';
  $newestReviewsStmt = $db->prepare($newestReviewsQuery);
  $newestReviewsStmt->execute([':author_id' => $userId]);
  $newestReviews = $newestReviewsStmt->fetchAll(PDO::FETCH_ASSOC);
  
  if (!empty($newestReviews)) {
      echo '<div class="newest-reviews">';
      echo '<h3>Nejnovější recenze vašich knih</h3>';
      foreach ($newestReviews as $review) {
          echo '<div class="review">';
          echo '<div class="review-book-name"><strong>Kniha:</strong> ' . htmlspecialchars($review['book_name']) . '</div>';
          echo '<div class="review-reviewer-name"><strong>Recenzent:</strong> ' . htmlspecialchars($review['reviewer_name']) . '</div>';
          echo '<div class="review-rating"><strong>Hodnocení:</strong> ' . htmlspecialchars($review['rating']) . '/5</div>';
          echo '<div class="review-text">' . htmlspecialchars($review['comment']) . '</div>';
          echo '<div class="review-date"><strong>Datum:</strong> ' . htmlspecialchars($review['created_at']) . '</div>';
          echo '</div>';
      }
      echo '</div>';
  }
}

// Vložení patičky
include 'inc/footer.php';
?>
