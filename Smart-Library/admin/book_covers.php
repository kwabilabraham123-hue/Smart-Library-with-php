<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin"]);

$message = "";
$messageType = "success";

$uploadFolder = __DIR__ . "/../uploads/books/";

/* Create folder automatically if it does not exist */
if (!is_dir($uploadFolder)) {
    mkdir($uploadFolder, 0755, true);
}

/* Upload book cover */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $bookId = $_POST["book_id"] ?? "";

    if (empty($bookId)) {
        $message = "Please select a book.";
        $messageType = "danger";
    } elseif (!isset($_FILES["book_image"]) || $_FILES["book_image"]["error"] !== UPLOAD_ERR_OK) {
        $message = "Please choose a valid image file.";
        $messageType = "danger";
    } else {
        $image = $_FILES["book_image"];

        $allowedExtensions = ["jpg", "jpeg", "png", "webp"];
        $fileExtension = strtolower(pathinfo($image["name"], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions)) {
            $message = "Only JPG, JPEG, PNG, and WEBP images are allowed.";
            $messageType = "danger";
        } elseif ($image["size"] > 3 * 1024 * 1024) {
            $message = "Image must not be larger than 3MB.";
            $messageType = "danger";
        } else {
            $bookStatement = $pdo->prepare(
                "SELECT id, book_image
                 FROM books
                 WHERE id = ?
                 LIMIT 1"
            );

            $bookStatement->execute([$bookId]);
            $book = $bookStatement->fetch();

            if (!$book) {
                $message = "Book was not found.";
                $messageType = "danger";
            } else {
                $newFileName = "book_" . $bookId . "_" . time() . "." . $fileExtension;
                $destination = $uploadFolder . $newFileName;

                if (move_uploaded_file($image["tmp_name"], $destination)) {

                    /* Delete old image if one exists */
                    if (!empty($book["book_image"])) {
                        $oldImage = $uploadFolder . $book["book_image"];

                        if (file_exists($oldImage)) {
                            unlink($oldImage);
                        }
                    }

                    $updateBook = $pdo->prepare(
                        "UPDATE books
                         SET book_image = ?
                         WHERE id = ?"
                    );

                    $updateBook->execute([
                        $newFileName,
                        $bookId
                    ]);

                    header("Location: book_covers.php?message=uploaded");
                    exit;

                } else {
                    $message = "Image upload failed. Please try again.";
                    $messageType = "danger";
                }
            }
        }
    }
}

if (isset($_GET["message"]) && $_GET["message"] === "uploaded") {
    $message = "Book cover image uploaded successfully.";
}

/* Get books */
$books = $pdo->query(
    "SELECT
        books.id,
        books.title,
        books.author,
        books.book_image,
        categories.category_name
     FROM books
     INNER JOIN categories ON books.category_id = categories.id
     ORDER BY books.title ASC"
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Covers | Smart Library</title>

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
            color: rgba(255, 255, 255, 0.80);
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
            align-items: center;
            justify-content: space-between;
            padding: 17px 30px;
            background: white;
            box-shadow: 0 2px 14px rgba(16, 42, 67, 0.06);
        }

        .page-content {
            padding: 30px;
        }

        .page-title {
            color: var(--navy);
            font-weight: 800;
        }

        .upload-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .upload-header {
            padding: 22px;
            border-radius: 18px 18px 0 0;
            color: white;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
        }

        .form-select,
        .form-control {
            padding: 12px;
            border-radius: 10px;
        }

        .upload-button {
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-weight: 700;
        }

        .book-card {
            height: 100%;
            overflow: hidden;
            border: none;
            border-radius: 16px;
            background: white;
            box-shadow: 0 7px 20px rgba(16, 42, 67, 0.07);
            transition: 0.25s ease;
        }

        .book-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 30px rgba(16, 42, 67, 0.14);
        }

        .cover-image {
            width: 100%;
            height: 210px;
            object-fit: cover;
        }

        .cover-placeholder {
            width: 100%;
            height: 210px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-size: 4rem;
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

    <p class="sidebar-label">ADMIN MENU</p>

    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard.php">
            <i class="fa-solid fa-chart-pie"></i> Dashboard
        </a>

        <a class="nav-link" href="books.php">
            <i class="fa-solid fa-book"></i> Books
        </a>

        <a class="nav-link active" href="book_covers.php">
            <i class="fa-solid fa-image"></i> Book Covers
        </a>

        <a class="nav-link" href="members.php">
            <i class="fa-solid fa-users"></i> Members
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
            <small class="text-muted">Smart Library / Admin / Book Covers</small>
            <h6 class="mb-0 fw-bold">Book Cover Management</h6>
        </div>

        <a href="books.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Back to Books
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">Upload Book Cover Images</h2>

            <p class="text-muted mb-0">
                Upload JPG, PNG, or WEBP book covers. Maximum image size: 3MB.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card upload-card mb-5">
            <div class="upload-header">
                <h5 class="mb-1">
                    <i class="fa-solid fa-cloud-arrow-up me-2"></i>
                    Upload or Replace a Book Cover
                </h5>

                <small class="text-white-50">
                    Select the book, then select an image from your computer.
                </small>
            </div>

            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data" id="coverUploadForm">
                    <div class="row g-3 align-items-end">

                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Select Book</label>

                            <select class="form-select" name="book_id" required>
                                <option value="">Choose a book</option>

                                <?php foreach ($books as $book): ?>
                                    <option value="<?php echo $book["id"]; ?>">
                                        <?php echo e($book["title"]); ?>
                                        — <?php echo e($book["author"]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Choose Cover Image</label>

                            <input
                                type="file"
                                class="form-control"
                                name="book_image"
                                accept=".jpg,.jpeg,.png,.webp"
                                required
                            >
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary upload-button w-100">
                                <i class="fa-solid fa-upload me-1"></i>
                                Upload
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <h4 class="fw-bold mb-3">
            Current Book Covers
            <span class="badge text-bg-light"><?php echo count($books); ?></span>
        </h4>

        <div class="row g-4">
            <?php foreach ($books as $book): ?>
                <div class="col-sm-6 col-md-4 col-xl-3">
                    <div class="card book-card">

                        <?php if (!empty($book["book_image"])): ?>
                            <img
                                src="../uploads/books/<?php echo e($book["book_image"]); ?>"
                                class="cover-image"
                                alt="<?php echo e($book["title"]); ?>"
                            >
                        <?php else: ?>
                            <div class="cover-placeholder">
                                <i class="fa-solid fa-book"></i>
                            </div>
                        <?php endif; ?>

                        <div class="card-body">
                            <span class="badge text-bg-light mb-2">
                                <?php echo e($book["category_name"]); ?>
                            </span>

                            <h6 class="fw-bold mb-1">
                                <?php echo e($book["title"]); ?>
                            </h6>

                            <small class="text-muted">
                                <?php echo e($book["author"]); ?>
                            </small>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const coverUploadForm = document.getElementById("coverUploadForm");

    coverUploadForm.addEventListener("submit", function () {
        const button = coverUploadForm.querySelector("button[type='submit']");

        button.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin me-1"></i>Uploading...';

        button.disabled = true;
    });
</script>

</body>
</html>