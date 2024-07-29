<?php
require_once 'inc/user.php';

$bookId = '';
$bookCategory = (!empty($_REQUEST['category']) ? intval($_REQUEST['category']) : '');
$bookName = '';
$bookText = '';

if (!empty($_REQUEST['id'])) {
    $bookQuery = $db->prepare('SELECT * FROM books WHERE book_id=:id LIMIT 1;');
    $bookQuery->execute([':id' => $_REQUEST['id']]);
    if ($book = $bookQuery->fetch(PDO::FETCH_ASSOC)) {
        $bookId = $book['book_id'];
        $bookCategory = $book['category_id'];
        $bookName = $book['book_name'];
        $bookText = $book['text'];
    } else {
        exit('Kniha neexistuje.');
    }
}

$errors = [];
if (!empty($_POST)) {
    if (!empty($_POST['category'])) {
        $categoryQuery = $db->prepare('SELECT * FROM categories WHERE category_id=:category LIMIT 1;');
        $categoryQuery->execute([':category' => $_POST['category']]);
        if ($categoryQuery->rowCount() == 0) {
            $errors['category'] = 'Vybraná kategorie neexistuje!';
            $bookCategory = '';
        } else {
            $bookCategory = $_POST['category'];
        }
    } else {
        $errors['category'] = 'Musíte vybrat kategorii.';
    }

    $bookName = trim(@$_POST['book_name']);
    if (empty($bookName)) {
        $errors['book_name'] = 'Musíte zadat název knihy.';
    }

    $bookText = trim(@$_POST['text']);
    if (empty($bookText)) {
        $errors['text'] = 'Musíte zadat text.';
    }

    if (empty($errors)) {
        if ($bookId) {
            $saveQuery = $db->prepare('UPDATE books SET category_id=:category, book_name=:book_name, text=:text, user_id=:user WHERE book_id=:id LIMIT 1;');
            $saveQuery->execute([
                ':category' => $bookCategory,
                ':book_name' => $bookName,
                ':text' => $bookText,
                ':id' => $bookId,
                ':user' => $_SESSION['user_id']
            ]);
        } else {
            $saveQuery = $db->prepare('INSERT INTO books (user_id, category_id, book_name, text) VALUES (:user, :category, :book_name, :text);');
            $saveQuery->execute([
                ':user' => $_SESSION['user_id'],
                ':category' => $bookCategory,
                ':book_name' => $bookName,
                ':text' => $bookText
            ]);
        }
        header('Location: index.php?category=' . $bookCategory);
        exit();
    }
}

if ($bookId) {
    $pageTitle = 'Upravit knihu';
} else {
    $pageTitle = 'Nová kniha';
}

include 'inc/header.php';
?>

<form method="post">
    <input type="hidden" name="id" value="<?php echo $bookId; ?>" />
    <div class="form-group">
        <label for="category">Kategorie:</label>
        <select name="category" id="category" required class="form-control <?php echo (!empty($errors['category']) ? 'is-invalid' : ''); ?>">
            <option value="">--Vyberte--</option>
            <?php
            $categoryQuery = $db->prepare('SELECT * FROM categories ORDER BY name;');
            $categoryQuery->execute();
            $categories = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    echo '<option value="' . $category['category_id'] . '" ' . ($category['category_id'] == $bookCategory ? 'selected="selected"' : '') . '>' . htmlspecialchars($category['name']) . '</option>';
                }
            }
            ?>
        </select>
        <?php
        if (!empty($errors['category'])) {
            echo '<div class="invalid-feedback">' . $errors['category'] . '</div>';
        }
        ?>
    </div>

    <div class="form-group">
        <label for="book_name">Název knihy:</label>
        <input type="text" name="book_name" id="book_name" required class="form-control <?php echo (!empty($errors['book_name']) ? 'is-invalid' : ''); ?>" value="<?php echo htmlspecialchars($bookName); ?>">
        <?php
        if (!empty($errors['book_name'])) {
            echo '<div class="invalid-feedback">' . $errors['book_name'] . '</div>';
        }
        ?>
    </div>

    <div class="form-group">
        <label for="text">Text knihy:</label>
        <textarea name="text" id="text" required class="form-control <?php echo (!empty($errors['text']) ? 'is-invalid' : ''); ?>"><?php echo htmlspecialchars($bookText) ?></textarea>
        <?php
        if (!empty($errors['text'])) {
            echo '<div class="invalid-feedback">' . $errors['text'] . '</div>';
        }
        ?>
    </div>

    <button type="submit" class="btn btn-primary">Uložit</button>
    <a href="index.php?category=<?php echo $bookCategory; ?>" class="btn btn-light">Zrušit</a>
</form>

<?php
include 'inc/footer.php';
?>
