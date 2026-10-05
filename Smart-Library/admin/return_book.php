<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin", "librarian"]);

$message = "";
$messageType = "success";

/* Process book return */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $borrowingId = $_POST["borrowing_id"] ?? "";

    try {
        $pdo->beginTransaction();

        $borrowingStatement = $pdo->prepare(
            "SELECT
                borrowings.id,
                borrowings.book_id,
                borrowings.due_date,
                borrowings.status,
                books.title
             FROM borrowings
             INNER JOIN books ON borrowings.book_id = books.id
             WHERE borrowings.id = ?
             FOR UPDATE"
        );

        $borrowingStatement->execute([$borrowingId]);
        $borrowing = $borrowingStatement->fetch();

        if (!$borrowing) {
            throw new Exception("Borrowing record was not found.");
        }

        if ($borrowing["status"] !== "borrowed") {
            throw new Exception("This book has already been returned.");
        }

        $today = new DateTime();
        $dueDate = new DateTime($borrowing["due_date"]);

        $lateDays = 0;
        $fineAmount = 0;

        if ($today > $dueDate) {
            $lateDays = $dueDate->diff($today)->days;
            $fineAmount = $lateDays * 2;
        }

        $returnStatement = $pdo->prepare(
            "UPDATE borrowings
             SET return_date = CURDATE(),
                 fine_amount = ?,
                 fine_status = ?,
                 status = 'returned'
             WHERE id = ?"
        );

        $fineStatus = $fineAmount > 0 ? "unpaid" : "paid";

        $returnStatement->execute([
            $fineAmount,
            $fineStatus,
            $borrowingId
        ]);

        $stockStatement = $pdo->prepare(
            "UPDATE books
             SET available_quantity =
                CASE
                    WHEN available_quantity < quantity
                    THEN available_quantity + 1
                    ELSE quantity
                END
             WHERE id = ?"
        );

        $stockStatement->execute([$borrowing["book_id"]]);

        $pdo->commit();

        if ($fineAmount > 0) {
            header(
                "Location: return_book.php?message=returned&late_days="
                . $lateDays
                . "&fine="
                . $fineAmount
            );
        } else {
            header("Location: return_book.php?message=returned");
        }

        exit;

    } catch (Exception $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $message = $error->getMessage();
        $messageType = "danger";
    }
}

/* Return success message */
if (isset($_GET["message"]) && $_GET["message"] === "returned") {
    $message = "Book returned successfully.";

    if (isset($_GET["fine"]) && $_GET["fine"] > 0) {
        $message .= " Fine: GHS " . number_format($_GET["fine"], 2)
            . " for " . $_GET["late_days"] . " overdue day(s).";
        $messageType = "warning";
    }
}

/* Search active borrowings */
$search = trim($_GET["search"] ?? "");
$searchValue = "%" . $search . "%";

$borrowingsStatement = $pdo->prepare(
    "SELECT
        borrowings.id,
        borrowings.issue_date,
        borrowings.due_date,
        DATEDIFF(CURDATE(), borrowings.due_date) AS late_days,
        books.title AS book_title,
        books.author,
        members.member_code,
        users.full_name AS member_name
     FROM borrowings
     INNER JOIN books ON borrowings.book_id = books.id
     INNER JOIN members ON borrowings.member_id = members.id
     INNER JOIN users ON members.user_id = users.id
     WHERE borrowings.status = 'borrowed'
     AND (
        users.full_name LIKE ?
        OR members.member_code LIKE ?
        OR books.title LIKE ?
        OR books.author LIKE ?
     )
     ORDER BY borrowings.due_date ASC"
);

$borrowingsStatement->execute([
    $searchValue,
    $searchValue,
    $searchValue,
    $searchValue
]);

$activeBorrowings = $borrowingsStatement->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #102a43;
            --blue: #176b9f;
            --purple: #6d3fc7;
            --green: #159957;
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

        .page-content {
            padding: 30px;
        }

        .page-title {
            color: var(--navy);
            font-weight: 800;
        }

        .table-card {
            border: none;
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .notice-card {
            padding: 18px;
            border-radius: 14px;
            color: #0f5132;
            background: linear-gradient(135deg, #e8fff1, #effff9);
        }

        .overdue-badge {
            padding: 6px 10px;
            border-radius: 50px;
            color: #b42318;
            background: #fee4e2;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .on-time-badge {
            padding: 6px 10px;
            border-radius: 50px;
            color: #166534;
            background: #dcfce7;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .return-button {
            border: none;
            border-radius: 9px;
            background: linear-gradient(135deg, #159957, #38ef7d);
            font-weight: 700;
        }

        .form-control {
            padding: 12px;
            border-radius: 10px;
        }

        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.22rem rgba(109, 63, 199, 0.13);
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

    <p class="sidebar-label">LIBRARY ACTIONS</p>

    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard.php">
            <i class="fa-solid fa-chart-pie"></i> Dashboard
        </a>

        <a class="nav-link" href="books.php">
            <i class="fa-solid fa-book"></i> Books
        </a>

        <a class="nav-link" href="issue_book.php">
            <i class="fa-solid fa-hand-holding"></i> Issue Book
        </a>

        <a class="nav-link active" href="return_book.php">
            <i class="fa-solid fa-rotate-left"></i> Return Book
        </a>

        <a class="nav-link" href="#">
            <i class="fa-solid fa-users"></i> Members
        </a>

        <a class="nav-link" href="#">
            <i class="fa-solid fa-clock"></i> Overdue Books
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
            <small class="text-muted">Smart Library / Returns</small>
            <h6 class="mb-0 fw-bold">Book Return Management</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">Return a Borrowed Book</h2>
            <p class="text-muted mb-0">
                Find an active borrowing record and process the return.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="notice-card mb-4">
            <i class="fa-solid fa-circle-info me-2"></i>
            Overdue fines are calculated automatically at
            <strong>GHS 2.00 per late day</strong>.
        </div>

        <div class="card table-card">
            <div class="card-body p-4">

                <form method="GET" class="row g-2 mb-4">
                    <div class="col-md-10">
                        <input
                            type="text"
                            class="form-control"
                            name="search"
                            value="<?php echo e($search); ?>"
                            placeholder="Search by member name, member ID, book title, or author..."
                        >
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>
                            Search
                        </button>
                    </div>
                </form>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">Active Borrowing Records</h5>
                        <small class="text-muted">
                            <?php echo count($activeBorrowings); ?> book(s) currently on loan.
                        </small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Member</th>
                                <th>Book</th>
                                <th>Issued</th>
                                <th>Due Date</th>
                                <th>Fine Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($activeBorrowings) > 0): ?>
                                <?php foreach ($activeBorrowings as $borrowing): ?>
                                    <tr>
                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($borrowing["member_name"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($borrowing["member_code"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($borrowing["book_title"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($borrowing["author"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($borrowing["issue_date"])); ?>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($borrowing["due_date"])); ?>
                                        </td>

                                        <td>
                                            <?php if ($borrowing["late_days"] > 0): ?>
                                                <span class="overdue-badge">
                                                    <?php echo $borrowing["late_days"]; ?> day(s) overdue
                                                </span>
                                            <?php else: ?>
                                                <span class="on-time-badge">
                                                    On Time
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-end">
                                            <form method="POST" class="d-inline">
                                                <input
                                                    type="hidden"
                                                    name="borrowing_id"
                                                    value="<?php echo $borrowing["id"]; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-success btn-sm return-button"
                                                    onclick="return confirm('Confirm that this book has been returned?');"
                                                >
                                                    <i class="fa-solid fa-check me-1"></i>
                                                    Return
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-book-open fs-3 d-block mb-2"></i>
                                        No active borrowing records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>