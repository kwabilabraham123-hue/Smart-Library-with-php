<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin"]);

$message = "";
$messageType = "success";

$editCategory = [
    "id" => "",
    "category_name" => "",
    "description" => ""
];

/* Add, update, or delete category */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $categoryName = trim($_POST["category_name"] ?? "");
        $description = trim($_POST["description"] ?? "");

        if (empty($categoryName)) {
            $message = "Category name is required.";
            $messageType = "danger";
        } else {
            try {
                $addCategory = $pdo->prepare(
                    "INSERT INTO categories (category_name, description)
                     VALUES (?, ?)"
                );

                $addCategory->execute([$categoryName, $description]);

                header("Location: categories.php?message=added");
                exit;
            } catch (PDOException $error) {
                $message = "This category already exists.";
                $messageType = "danger";
            }
        }
    }

    if ($action === "update") {
        $categoryId = $_POST["category_id"] ?? "";
        $categoryName = trim($_POST["category_name"] ?? "");
        $description = trim($_POST["description"] ?? "");

        if (empty($categoryId) || empty($categoryName)) {
            $message = "Category name is required.";
            $messageType = "danger";
        } else {
            try {
                $updateCategory = $pdo->prepare(
                    "UPDATE categories
                     SET category_name = ?, description = ?
                     WHERE id = ?"
                );

                $updateCategory->execute([
                    $categoryName,
                    $description,
                    $categoryId
                ]);

                header("Location: categories.php?message=updated");
                exit;
            } catch (PDOException $error) {
                $message = "Category name already exists.";
                $messageType = "danger";
            }
        }
    }

    if ($action === "delete") {
        $categoryId = $_POST["category_id"] ?? "";

        $bookCount = $pdo->prepare(
            "SELECT COUNT(*) FROM books WHERE category_id = ?"
        );

        $bookCount->execute([$categoryId]);

        if ($bookCount->fetchColumn() > 0) {
            $message = "This category cannot be deleted because it contains books.";
            $messageType = "danger";
        } else {
            $deleteCategory = $pdo->prepare(
                "DELETE FROM categories WHERE id = ?"
            );

            $deleteCategory->execute([$categoryId]);

            header("Location: categories.php?message=deleted");
            exit;
        }
    }
}

/* Display success messages */
if (isset($_GET["message"])) {
    if ($_GET["message"] === "added") {
        $message = "Category added successfully.";
    }

    if ($_GET["message"] === "updated") {
        $message = "Category updated successfully.";
    }

    if ($_GET["message"] === "deleted") {
        $message = "Category deleted successfully.";
    }
}

/* Load category details for editing */
if (isset($_GET["edit"])) {
    $editId = $_GET["edit"];

    $editStatement = $pdo->prepare(
        "SELECT * FROM categories WHERE id = ?"
    );

    $editStatement->execute([$editId]);
    $foundCategory = $editStatement->fetch();

    if ($foundCategory) {
        $editCategory = $foundCategory;
    }
}

/* Search categories */
$search = trim($_GET["search"] ?? "");

$categoryStatement = $pdo->prepare(
    "SELECT
        categories.id,
        categories.category_name,
        categories.description,
        categories.created_at,
        COUNT(books.id) AS book_count
     FROM categories
     LEFT JOIN books ON categories.id = books.category_id
     WHERE categories.category_name LIKE ?
     OR categories.description LIKE ?
     GROUP BY categories.id
     ORDER BY categories.category_name ASC"
);

$searchValue = "%" . $search . "%";
$categoryStatement->execute([$searchValue, $searchValue]);
$categories = $categoryStatement->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories | Smart Library</title>

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
            align-items: center;
            justify-content: center;
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

        .form-control {
            padding: 11px 13px;
            border-radius: 10px;
        }

        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.22rem rgba(109, 63, 199, 0.12);
        }

        .save-button {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-weight: 700;
        }

        .category-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border-radius: 12px;
            color: #ffffff;
            background: linear-gradient(135deg, #176b9f, #54a8d9);
        }

        .book-count {
            padding: 6px 10px;
            border-radius: 50px;
            color: #166534;
            background: #dcfce7;
            font-size: 0.78rem;
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

        <a class="nav-link" href="#">
            <i class="fa-solid fa-book"></i> Books
        </a>

        <a class="nav-link active" href="categories.php">
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
            <small class="text-muted">Smart Library / Admin / Categories</small>
            <h6 class="mb-0 fw-bold">Category Management</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="dashboard-body">

        <div class="mb-4">
            <h2 class="page-title mb-1">Book Categories</h2>
            <p class="text-muted mb-0">
                Create and organize categories for your library books.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Add / Edit form -->
            <div class="col-lg-4">
                <div class="card form-card">
                    <div class="form-card-header">
                        <h5 class="mb-1">
                            <i class="fa-solid fa-layer-group me-2"></i>
                            <?php echo !empty($editCategory["id"]) ? "Edit Category" : "Add Category"; ?>
                        </h5>

                        <small class="text-white-50">
                            Enter category details below.
                        </small>
                    </div>

                    <div class="card-body p-4">
                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="<?php echo !empty($editCategory["id"]) ? "update" : "add"; ?>"
                            >

                            <?php if (!empty($editCategory["id"])): ?>
                                <input
                                    type="hidden"
                                    name="category_id"
                                    value="<?php echo e($editCategory["id"]); ?>"
                                >
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    Category Name
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    name="category_name"
                                    value="<?php echo e($editCategory["category_name"]); ?>"
                                    placeholder="Example: Information Security"
                                    required
                                >
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    Description
                                </label>

                                <textarea
                                    class="form-control"
                                    name="description"
                                    rows="4"
                                    placeholder="Brief category description"
                                ><?php echo e($editCategory["description"]); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary save-button w-100">
                                <i class="fa-solid fa-floppy-disk me-2"></i>
                                <?php echo !empty($editCategory["id"]) ? "Update Category" : "Add Category"; ?>
                            </button>

                            <?php if (!empty($editCategory["id"])): ?>
                                <a href="categories.php" class="btn btn-light border w-100 mt-2">
                                    Cancel Edit
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Category table -->
            <div class="col-lg-8">
                <div class="card table-card">
                    <div class="card-body p-4">

                        <form method="GET" class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fa-solid fa-magnifying-glass text-primary"></i>
                                </span>

                                <input
                                    type="text"
                                    class="form-control"
                                    name="search"
                                    value="<?php echo e($search); ?>"
                                    placeholder="Search categories..."
                                >

                                <button class="btn btn-primary" type="submit">
                                    Search
                                </button>
                            </div>
                        </form>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">
                                All Categories
                                <span class="badge text-bg-light"><?php echo count($categories); ?></span>
                            </h5>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Books</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (count($categories) > 0): ?>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="category-icon">
                                                            <i class="fa-solid fa-folder"></i>
                                                        </span>

                                                        <strong>
                                                            <?php echo e($category["category_name"]); ?>
                                                        </strong>
                                                    </div>
                                                </td>

                                                <td class="text-muted">
                                                    <?php echo e($category["description"] ?: "No description"); ?>
                                                </td>

                                                <td>
                                                    <span class="book-count">
                                                        <?php echo $category["book_count"]; ?> book(s)
                                                    </span>
                                                </td>

                                                <td class="text-end">
                                                    <a
                                                        href="categories.php?edit=<?php echo $category["id"]; ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Edit category"
                                                    >
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>

                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input
                                                            type="hidden"
                                                            name="category_id"
                                                            value="<?php echo $category["id"]; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Delete category"
                                                            onclick="return confirm('Delete this category?');"
                                                        >
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-5">
                                                <i class="fa-solid fa-folder-open fs-3 d-block mb-2"></i>
                                                No categories found.
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