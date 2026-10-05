<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin"]);

$message = "";
$messageType = "success";

$editBook = [
    "id" => "",
    "title" => "",
    "author" => "",
    "isbn" => "",
    "category_id" => "",
    "quantity" => 1,
    "available_quantity" => 1,
    "shelf_number" => "",
    "description" => ""
];

/* Get categories for the book form */
$categories = $pdo->query(
    "SELECT id, category_name
     FROM categories
     ORDER BY category_name ASC"
)->fetchAll();

/* Add, update, or delete a book */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    $title = trim($_POST["title"] ?? "");
    $author = trim($_POST["author"] ?? "");
    $isbn = trim($_POST["isbn"] ?? "");
    $categoryId = $_POST["category_id"] ?? "";
    $quantity = (int) ($_POST["quantity"] ?? 0);
    $availableQuantity = (int) ($_POST["available_quantity"] ?? 0);
    $shelfNumber = trim($_POST["shelf_number"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($action === "add" || $action === "update") {
        if (empty($title) || empty($author) || empty($categoryId)) {
            $message = "Title, author, and category are required.";
            $messageType = "danger";
        } elseif ($quantity < 1) {
            $message = "Quantity must be at least 1.";
            $messageType = "danger";
        } elseif ($availableQuantity < 0 || $availableQuantity > $quantity) {
            $message = "Available quantity must be between 0 and total quantity.";
            $messageType = "danger";
        } else {
            try {
                if ($action === "add") {
                    $addBook = $pdo->prepare(
                        "INSERT INTO books
                        (title, author, isbn, category_id, quantity, available_quantity, shelf_number, description)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    $addBook->execute([
                        $title,
                        $author,
                        $isbn ?: null,
                        $categoryId,
                        $quantity,
                        $availableQuantity,
                        $shelfNumber ?: null,
                        $description ?: null
                    ]);

                    header("Location: books.php?message=added");
                    exit;
                }

                if ($action === "update") {
                    $bookId = $_POST["book_id"] ?? "";

                    $updateBook = $pdo->prepare(
                        "UPDATE books
                         SET title = ?, author = ?, isbn = ?, category_id = ?,
                             quantity = ?, available_quantity = ?, shelf_number = ?, description = ?
                         WHERE id = ?"
                    );

                    $updateBook->execute([
                        $title,
                        $author,
                        $isbn ?: null,
                        $categoryId,
                        $quantity,
                        $availableQuantity,
                        $shelfNumber ?: null,
                        $description ?: null,
                        $bookId
                    ]);

                    header("Location: books.php?message=updated");
                    exit;
                }
            } catch (PDOException $error) {
                $message = "Book could not be saved. The ISBN may already exist.";
                $messageType = "danger";
            }
        }
    }

    if ($action === "delete") {
        $bookId = $_POST["book_id"] ?? "";

        $borrowedCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM borrowings WHERE book_id = ?"
        );

        $borrowedCheck->execute([$bookId]);

        if ($borrowedCheck->fetchColumn() > 0) {
            $message = "This book cannot be deleted because it has borrowing records.";
            $messageType = "danger";
        } else {
            $deleteBook = $pdo->prepare(
                "DELETE FROM books WHERE id = ?"
            );

            $deleteBook->execute([$bookId]);

            header("Location: books.php?message=deleted");
            exit;
        }
    }
}

/* Success notifications */
if (isset($_GET["message"])) {
    if ($_GET["message"] === "added") {
        $message = "Book added successfully.";
    }

    if ($_GET["message"] === "updated") {
        $message = "Book updated successfully.";
    }

    if ($_GET["message"] === "deleted") {
        $message = "Book deleted successfully.";
    }
}

/* Load a book for editing */
if (isset($_GET["edit"])) {
    $editStatement = $pdo->prepare(
        "SELECT * FROM books WHERE id = ?"
    );

    $editStatement->execute([$_GET["edit"]]);
    $foundBook = $editStatement->fetch();

    if ($foundBook) {
        $editBook = $foundBook;
    }
}

/* Search and filter books */
$search = trim($_GET["search"] ?? "");
$selectedCategory = $_GET["category"] ?? "";

$bookStatement = $pdo->prepare(
    "SELECT
        books.*,
        categories.category_name
     FROM books
     INNER JOIN categories ON books.category_id = categories.id
     WHERE
        (books.title LIKE ?
        OR books.author LIKE ?
        OR books.isbn LIKE ?
        OR books.shelf_number LIKE ?)
     AND (? = '' OR books.category_id = ?)
     ORDER BY books.id DESC"
);

$searchValue = "%" . $search . "%";

$bookStatement->execute([
    $searchValue,
    $searchValue,
    $searchValue,
    $searchValue,
    $selectedCategory,
    $selectedCategory
]);

$books = $bookStatement->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #102a43;
            --blue: #176b9f;
            --purple: #6d3fc7;
            --background: #f5f7fb;
        }

        body {
            margin: 0;
            background: var(--background);
            color: #243447;
            font-family: Arial, Helvetica, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 255px;
            min-height: 100vh;
            padding: 22px 16px;
            color: white;
            background: linear-gradient(180deg, #102a43, #123d63);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 1.15rem;
            font-weight: 800;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 10px;
            background: linear-gradient(135deg, #e84393, #f39c12);
        }

        .sidebar-label {
            margin: 25px 12px 10px;
            color: rgba(255, 255, 255, 0.50);
            font-size: 0.70rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .sidebar .nav-link {
            margin-bottom: 5px;
            padding: 11px 12px;
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.78);
            transition: 0.2s ease;
        }

        .sidebar .nav-link i {
            width: 23px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.13);
        }

        .main-content {
            min-height: 100vh;
            margin-left: 255px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 17px 30px;
            background: white;
            box-shadow: 0 2px 14px rgba(16, 42, 67, 0.06);
        }

        .dashboard-body {
            padding: 30px;
        }

        .page-title {
            color: var(--navy);
            font-weight: 800;
        }

        .form-card,
        .table-card {
            border: none;
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .form-card-header {
            padding: 20px 24px;
            border-radius: 18px 18px 0 0;
            color: white;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
        }

        .form-control,
        .form-select {
            padding: 11px 13px;
            border-radius: 10px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.22rem rgba(109, 63, 199, 0.12);
        }

        .save-button {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-weight: 700;
        }

        .book-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: white;
            background: linear-gradient(135deg, #176b9f, #54a8d9);
        }

        .availability {
            padding: 6px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .available {
            color: #166534;
            background: #dcfce7;
        }

        .low-stock {
            color: #a16207;
            background: #fef3c7;
        }

        .unavailable {
            color: #b42318;
            background: #fee4e2;
        }

        @media (max-width: 991px) {
            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .dashboard-body {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <span class="brand-icon">
            <i class="fa-solid fa-book-open-reader"></i>
        </span>
        Smart Library
    </div>

    <p class="sidebar-label">MAIN MENU</p>

    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard.php">
            <i class="fa-solid fa-chart-pie"></i> Dashboard
        </a>

        <a class="nav-link active" href="books.php">
            <i class="fa-solid fa-book"></i> Books
        </a>

        <a class="nav-link" href="categories.php">
            <i class="fa-solid fa-layer-group"></i> Categories
        </a>

        <a class="nav-link" href="#">
            <i class="fa-solid fa-users"></i> Members
        </a>

        <a class="nav-link" href="#">
            <i class="fa-solid fa-arrow-right-arrow-left"></i> Borrow & Return
        </a>

        <a class="nav-link" href="#">
            <i class="fa-solid fa-clock"></i> Overdue Books
        </a>
    </nav>

    <p class="sidebar-label">SYSTEM</p>

    <nav class="nav flex-column">
        <a class="nav-link text-warning" href="../auth/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">

    <header class="topbar">
        <div>
            <small class="text-muted">Smart Library / Admin / Books</small>
            <h6 class="mb-0 fw-bold">Book Management</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="dashboard-body">

        <div class="mb-4">
            <h2 class="page-title mb-1">Library Book Catalog</h2>
            <p class="text-muted mb-0">
                Add, edit, search, and manage books in your library.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Add / Edit book form -->
            <div class="col-lg-4">
                <div class="card form-card">
                    <div class="form-card-header">
                        <h5 class="mb-1">
                            <i class="fa-solid fa-book-medical me-2"></i>
                            <?php echo !empty($editBook["id"]) ? "Edit Book" : "Add New Book"; ?>
                        </h5>

                        <small class="text-white-50">
                            Fill in the book details below.
                        </small>
                    </div>

                    <div class="card-body p-4">
                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="<?php echo !empty($editBook["id"]) ? "update" : "add"; ?>"
                            >

                            <?php if (!empty($editBook["id"])): ?>
                                <input
                                    type="hidden"
                                    name="book_id"
                                    value="<?php echo e($editBook["id"]); ?>"
                                >
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Book Title</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="title"
                                    value="<?php echo e($editBook["title"]); ?>"
                                    placeholder="Enter book title"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Author</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="author"
                                    value="<?php echo e($editBook["author"]); ?>"
                                    placeholder="Enter author name"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">ISBN</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="isbn"
                                    value="<?php echo e($editBook["isbn"]); ?>"
                                    placeholder="Optional ISBN number"
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select category</option>

                                    <?php foreach ($categories as $category): ?>
                                        <option
                                            value="<?php echo $category["id"]; ?>"
                                            <?php echo $editBook["category_id"] == $category["id"] ? "selected" : ""; ?>
                                        >
                                            <?php echo e($category["category_name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Quantity</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        name="quantity"
                                        min="1"
                                        value="<?php echo e($editBook["quantity"]); ?>"
                                        required
                                    >
                                </div>

                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Available</label>
                                    <input
                                        type="number"
                                        class="form-control"
                                        name="available_quantity"
                                        min="0"
                                        value="<?php echo e($editBook["available_quantity"]); ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Shelf Number</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="shelf_number"
                                    value="<?php echo e($editBook["shelf_number"]); ?>"
                                    placeholder="Example: IS-01"
                                >
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea
                                    class="form-control"
                                    name="description"
                                    rows="3"
                                    placeholder="Brief book description"
                                ><?php echo e($editBook["description"]); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary save-button w-100">
                                <i class="fa-solid fa-floppy-disk me-2"></i>
                                <?php echo !empty($editBook["id"]) ? "Update Book" : "Add Book"; ?>
                            </button>

                            <?php if (!empty($editBook["id"])): ?>
                                <a href="books.php" class="btn btn-light border w-100 mt-2">
                                    Cancel Edit
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Books list -->
            <div class="col-lg-8">
                <div class="card table-card">
                    <div class="card-body p-4">

                        <form method="GET" class="row g-2 mb-4">
                            <div class="col-md-7">
                                <input
                                    type="text"
                                    class="form-control"
                                    name="search"
                                    value="<?php echo e($search); ?>"
                                    placeholder="Search title, author, ISBN, or shelf..."
                                >
                            </div>

                            <div class="col-md-3">
                                <select class="form-select" name="category">
                                    <option value="">All categories</option>

                                    <?php foreach ($categories as $category): ?>
                                        <option
                                            value="<?php echo $category["id"]; ?>"
                                            <?php echo $selectedCategory == $category["id"] ? "selected" : ""; ?>
                                        >
                                            <?php echo e($category["category_name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" type="submit">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    Search
                                </button>
                            </div>
                        </form>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">
                                All Books
                                <span class="badge text-bg-light"><?php echo count($books); ?></span>
                            </h5>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Book</th>
                                        <th>Category</th>
                                        <th>Stock</th>
                                        <th>Shelf</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (count($books) > 0): ?>
                                        <?php foreach ($books as $book): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="book-icon">
                                                            <i class="fa-solid fa-book"></i>
                                                        </span>

                                                        <div>
                                                            <strong class="d-block">
                                                                <?php echo e($book["title"]); ?>
                                                            </strong>

                                                            <small class="text-muted">
                                                                <?php echo e($book["author"]); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td>
                                                    <span class="badge text-bg-light">
                                                        <?php echo e($book["category_name"]); ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?php
                                                    if ($book["available_quantity"] == 0) {
                                                        echo '<span class="availability unavailable">Unavailable</span>';
                                                    } elseif ($book["available_quantity"] <= 2) {
                                                        echo '<span class="availability low-stock">'
                                                            . $book["available_quantity"]
                                                            . ' / '
                                                            . $book["quantity"]
                                                            . ' available</span>';
                                                    } else {
                                                        echo '<span class="availability available">'
                                                            . $book["available_quantity"]
                                                            . ' / '
                                                            . $book["quantity"]
                                                            . ' available</span>';
                                                    }
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php echo e($book["shelf_number"] ?: "-"); ?>
                                                </td>

                                                <td class="text-end">
                                                    <a
                                                        href="books.php?edit=<?php echo $book["id"]; ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Edit book"
                                                    >
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>

                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input
                                                            type="hidden"
                                                            name="book_id"
                                                            value="<?php echo $book["id"]; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Delete book"
                                                            onclick="return confirm('Delete this book?');"
                                                        >
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
                                                <i class="fa-solid fa-book-open fs-3 d-block mb-2"></i>
                                                No books found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>