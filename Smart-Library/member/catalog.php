<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["member"]);

$message = "";
$messageType = "success";

/* Get the logged-in member profile */
$memberStatement = $pdo->prepare(
    "SELECT id
     FROM members
     WHERE user_id = ?
     LIMIT 1"
);

$memberStatement->execute([$_SESSION["user_id"]]);
$member = $memberStatement->fetch();

if (!$member) {
    die("Member profile not found.");
}

$memberId = $member["id"];

/* Make a reservation */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $bookId = $_POST["book_id"] ?? "";

    try {
        $bookStatement = $pdo->prepare(
            "SELECT id, title, available_quantity
             FROM books
             WHERE id = ?
             LIMIT 1"
        );

        $bookStatement->execute([$bookId]);
        $book = $bookStatement->fetch();

        if (!$book) {
            throw new Exception("Book not found.");
        }

        if ($book["available_quantity"] > 0) {
            throw new Exception("This book is currently available. Please visit the library to borrow it.");
        }

        $checkReservation = $pdo->prepare(
            "SELECT COUNT(*)
             FROM reservations
             WHERE member_id = ?
             AND book_id = ?
             AND status IN ('pending', 'ready')"
        );

        $checkReservation->execute([$memberId, $bookId]);

        if ($checkReservation->fetchColumn() > 0) {
            throw new Exception("You already have an active reservation for this book.");
        }

        $reserveBook = $pdo->prepare(
            "INSERT INTO reservations (member_id, book_id, status)
             VALUES (?, ?, 'pending')"
        );

        $reserveBook->execute([$memberId, $bookId]);

        header("Location: catalog.php?message=reserved");
        exit;

    } catch (Exception $error) {
        $message = $error->getMessage();
        $messageType = "danger";
    }
}

if (isset($_GET["message"]) && $_GET["message"] === "reserved") {
    $message = "Book reserved successfully. You will be notified when it is ready for pickup.";
}

/* Search and category filter */
$search = trim($_GET["search"] ?? "");
$categoryId = $_GET["category"] ?? "";

$categories = $pdo->query(
    "SELECT id, category_name
     FROM categories
     ORDER BY category_name ASC"
)->fetchAll();

$catalogStatement = $pdo->prepare(
    "SELECT
        books.id,
        books.title,
        books.author,
        books.isbn,
        books.available_quantity,
        books.quantity,
        books.shelf_number,
        books.description,
        categories.category_name
     FROM books
     INNER JOIN categories ON books.category_id = categories.id
     WHERE
        (
            books.title LIKE ?
            OR books.author LIKE ?
            OR books.isbn LIKE ?
        )
        AND (? = '' OR books.category_id = ?)
     ORDER BY books.title ASC"
);

$searchValue = "%" . $search . "%";

$catalogStatement->execute([
    $searchValue,
    $searchValue,
    $searchValue,
    $categoryId,
    $categoryId
]);

$books = $catalogStatement->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #102a43;
            --blue: #176b9f;
            --purple: #6d3fc7;
            --pink: #e84393;
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
            color: #ffffff;
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
            color: #ffffff;
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
            background: #ffffff;
            box-shadow: 0 2px 14px rgba(16, 42, 67, 0.06);
        }

        .page-content {
            padding: 30px;
        }

        .page-title {
            color: var(--navy);
            font-weight: 800;
        }

        .search-card {
            padding: 20px;
            border: none;
            border-radius: 18px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.10);
        }

        .search-card .form-control,
        .search-card .form-select {
            padding: 12px;
            border: none;
            border-radius: 10px;
        }

        .book-card {
            height: 100%;
            overflow: hidden;
            border: none;
            border-radius: 17px;
            background: #ffffff;
            box-shadow: 0 7px 20px rgba(16, 42, 67, 0.07);
            transition: 0.25s ease;
        }

        .book-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 14px 30px rgba(16, 42, 67, 0.15);
        }

        .book-cover {
            height: 145px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            background:
                radial-gradient(circle at 80% 15%, rgba(255, 255, 255, 0.23), transparent 25%),
                linear-gradient(135deg, #176b9f, #6d3fc7);
            font-size: 3.5rem;
        }

        .category-badge {
            padding: 6px 9px;
            border-radius: 50px;
            color: #4b288c;
            background: #efe8ff;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .available-badge {
            padding: 6px 9px;
            border-radius: 50px;
            color: #166534;
            background: #dcfce7;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .unavailable-badge {
            padding: 6px 9px;
            border-radius: 50px;
            color: #b42318;
            background: #fee4e2;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .reserve-button {
            border: none;
            border-radius: 9px;
            background: linear-gradient(135deg, #e84393, #6d3fc7);
            font-weight: 700;
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

            .page-content {
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

    <p class="sidebar-label">MEMBER MENU</p>

    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard.php">
            <i class="fa-solid fa-house"></i> Dashboard
        </a>

        <a class="nav-link active" href="catalog.php">
            <i class="fa-solid fa-magnifying-glass"></i> Browse Books
        </a>

        <a class="nav-link" href="#">
            <i class="fa-solid fa-book-open"></i> My Borrowed Books
        </a>

        <a class="nav-link" href="#">
            <i class="fa-solid fa-clock-rotate-left"></i> Borrowing History
        </a>

        <a class="nav-link" href="#">
            <i class="fa-solid fa-bookmark"></i> My Reservations
        </a>
    </nav>

    <p class="sidebar-label">ACCOUNT</p>

    <nav class="nav flex-column">
        <a class="nav-link text-warning" href="../auth/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">

    <header class="topbar">
        <div>
            <small class="text-muted">Smart Library / Member / Catalog</small>
            <h6 class="mb-0 fw-bold">Browse Library Books</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">Find Your Next Book</h2>
            <p class="text-muted mb-0">
                Search the library catalog and reserve unavailable books.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search form -->
        <div class="search-card mb-4">
            <form method="GET" class="row g-2">
                <div class="col-md-7">
                    <input
                        type="text"
                        class="form-control"
                        name="search"
                        value="<?php echo e($search); ?>"
                        placeholder="Search by title, author, or ISBN..."
                    >
                </div>

                <div class="col-md-3">
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>

                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?php echo $category["id"]; ?>"
                                <?php echo $categoryId == $category["id"] ? "selected" : ""; ?>
                            >
                                <?php echo e($category["category_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-warning w-100 py-2 fw-bold" type="submit">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>
                        Search
                    </button>
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">
                Books Found
                <span class="badge text-bg-light"><?php echo count($books); ?></span>
            </h5>
        </div>

        <!-- Book cards -->
        <div class="row g-4">
            <?php if (count($books) > 0): ?>
                <?php foreach ($books as $book): ?>
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <div class="card book-card">
                            <div class="book-cover">
                                <i class="fa-solid fa-book"></i>
                            </div>

                            <div class="card-body p-4">
                                <span class="category-badge">
                                    <?php echo e($book["category_name"]); ?>
                                </span>

                                <h5 class="fw-bold mt-3 mb-1">
                                    <?php echo e($book["title"]); ?>
                                </h5>

                                <p class="text-muted small mb-3">
                                    <i class="fa-solid fa-pen-nib me-1"></i>
                                    <?php echo e($book["author"]); ?>
                                </p>

                                <p class="small text-muted mb-3">
                                    <?php echo e(
                                        $book["description"]
                                        ? substr($book["description"], 0, 90) . "..."
                                        : "No description available."
                                    ); ?>
                                </p>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small class="text-muted">
                                        <i class="fa-solid fa-location-dot me-1"></i>
                                        <?php echo e($book["shelf_number"] ?: "Not specified"); ?>
                                    </small>

                                    <?php if ($book["available_quantity"] > 0): ?>
                                        <span class="available-badge">
                                            <?php echo $book["available_quantity"]; ?> Available
                                        </span>
                                    <?php else: ?>
                                        <span class="unavailable-badge">
                                            Unavailable
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($book["available_quantity"] > 0): ?>
                                    <button class="btn btn-light border w-100" disabled>
                                        <i class="fa-solid fa-check me-1 text-success"></i>
                                        Available in Library
                                    </button>
                                <?php else: ?>
                                    <form method="POST">
                                        <input
                                            type="hidden"
                                            name="book_id"
                                            value="<?php echo $book["id"]; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-primary reserve-button w-100"
                                        >
                                            <i class="fa-solid fa-bookmark me-1"></i>
                                            Reserve Book
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center bg-white rounded-4 p-5 shadow-sm">
                        <i class="fa-solid fa-book-open fs-1 text-primary d-block mb-3"></i>
                        <h5>No books found</h5>
                        <p class="text-muted mb-0">
                            Try another title, author, ISBN, or category.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>