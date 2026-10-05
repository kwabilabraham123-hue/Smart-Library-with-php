<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin", "librarian"]);

$message = "";
$messageType = "success";

$defaultDueDate = date("Y-m-d", strtotime("+14 days"));

/* Issue a book */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $memberId = $_POST["member_id"] ?? "";
    $bookId = $_POST["book_id"] ?? "";
    $dueDate = $_POST["due_date"] ?? "";

    if (empty($memberId) || empty($bookId) || empty($dueDate)) {
        $message = "Please select a member, book, and due date.";
        $messageType = "danger";
    } elseif ($dueDate < date("Y-m-d")) {
        $message = "Due date cannot be earlier than today.";
        $messageType = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            /* Check whether member is active */
            $memberCheck = $pdo->prepare(
                "SELECT members.id
                 FROM members
                 INNER JOIN users ON members.user_id = users.id
                 WHERE members.id = ?
                 AND users.status = 'active'
                 LIMIT 1"
            );

            $memberCheck->execute([$memberId]);

            if (!$memberCheck->fetch()) {
                throw new Exception("Selected member is inactive or does not exist.");
            }

            /* Maximum three books per member */
            $loanCount = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM borrowings
                 WHERE member_id = ?
                 AND status = 'borrowed'"
            );

            $loanCount->execute([$memberId]);

            if ($loanCount->fetchColumn() >= 3) {
                throw new Exception("This member has reached the maximum borrowing limit of 3 books.");
            }

            /* Do not issue same book twice to the same member */
            $duplicateLoan = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM borrowings
                 WHERE member_id = ?
                 AND book_id = ?
                 AND status = 'borrowed'"
            );

            $duplicateLoan->execute([$memberId, $bookId]);

            if ($duplicateLoan->fetchColumn() > 0) {
                throw new Exception("This member already has this book on loan.");
            }

            /* Check book availability */
            $bookCheck = $pdo->prepare(
                "SELECT available_quantity
                 FROM books
                 WHERE id = ?
                 FOR UPDATE"
            );

            $bookCheck->execute([$bookId]);
            $book = $bookCheck->fetch();

            if (!$book || $book["available_quantity"] < 1) {
                throw new Exception("This book is currently unavailable.");
            }

            /* Save borrowing record */
            $issueBook = $pdo->prepare(
                "INSERT INTO borrowings
                (member_id, book_id, issued_by, issue_date, due_date, status)
                VALUES (?, ?, ?, CURDATE(), ?, 'borrowed')"
            );

            $issueBook->execute([
                $memberId,
                $bookId,
                $_SESSION["user_id"],
                $dueDate
            ]);

            /* Reduce available quantity */
            $updateBookStock = $pdo->prepare(
                "UPDATE books
                 SET available_quantity = available_quantity - 1
                 WHERE id = ?"
            );

            $updateBookStock->execute([$bookId]);

            $pdo->commit();

            header("Location: issue_book.php?message=issued");
            exit;

        } catch (Exception $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $error->getMessage();
            $messageType = "danger";
        }
    }
}

if (isset($_GET["message"]) && $_GET["message"] === "issued") {
    $message = "Book issued successfully. The available quantity has been updated.";
}

/* Active members */
$members = $pdo->query(
    "SELECT
        members.id,
        members.member_code,
        users.full_name,
        members.department_class
     FROM members
     INNER JOIN users ON members.user_id = users.id
     WHERE users.status = 'active'
     ORDER BY users.full_name ASC"
)->fetchAll();

/* Available books */
$books = $pdo->query(
    "SELECT
        books.id,
        books.title,
        books.author,
        books.available_quantity,
        books.shelf_number,
        categories.category_name
     FROM books
     INNER JOIN categories ON books.category_id = categories.id
     WHERE books.available_quantity > 0
     ORDER BY books.title ASC"
)->fetchAll();

/* Recent loans */
$recentLoans = $pdo->query(
    "SELECT
        borrowings.id,
        users.full_name AS member_name,
        members.member_code,
        books.title AS book_title,
        borrowings.issue_date,
        borrowings.due_date,
        borrowings.status
     FROM borrowings
     INNER JOIN members ON borrowings.member_id = members.id
     INNER JOIN users ON members.user_id = users.id
     INNER JOIN books ON borrowings.book_id = books.id
     ORDER BY borrowings.id DESC
     LIMIT 8"
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Book | Smart Library</title>

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

        .issue-card,
        .table-card {
            border: none;
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .issue-card-header {
            padding: 22px 25px;
            border-radius: 18px 18px 0 0;
            color: white;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
        }

        .form-control,
        .form-select {
            padding: 12px;
            border-radius: 10px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.22rem rgba(109, 63, 199, 0.13);
        }

        .issue-button {
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #159957, #38ef7d);
            font-weight: 700;
        }

        .info-box {
            border-radius: 12px;
            color: #0f5132;
            background: #e8fff1;
        }

        .status-badge {
            padding: 6px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .borrowed {
            color: #a16207;
            background: #fef3c7;
        }

        .returned {
            color: #166534;
            background: #dcfce7;
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

        <a class="nav-link active" href="issue_book.php">
            <i class="fa-solid fa-hand-holding"></i> Issue Book
        </a>

        <a class="nav-link" href="#">
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
            <small class="text-muted">Smart Library / Borrowing</small>
            <h6 class="mb-0 fw-bold">Issue Book to Member</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">Issue a Library Book</h2>
            <p class="text-muted mb-0">
                Select an active member and an available book to create a borrowing record.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <div class="col-lg-5">
                <div class="card issue-card">
                    <div class="issue-card-header">
                        <h5 class="mb-1">
                            <i class="fa-solid fa-hand-holding me-2"></i>
                            New Borrowing Transaction
                        </h5>

                        <small class="text-white-50">
                            Books are normally due within 14 days.
                        </small>
                    </div>

                    <div class="card-body p-4">

                        <div class="alert info-box small">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Each member can borrow a maximum of 3 books at a time.
                        </div>

                        <form method="POST" id="issueForm">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select Member</label>

                                <select class="form-select" name="member_id" required>
                                    <option value="">Choose a member</option>

                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member["id"]; ?>">
                                            <?php echo e($member["member_code"]); ?>
                                            —
                                            <?php echo e($member["full_name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select Available Book</label>

                                <select class="form-select" name="book_id" required>
                                    <option value="">Choose a book</option>

                                    <?php foreach ($books as $book): ?>
                                        <option value="<?php echo $book["id"]; ?>">
                                            <?php echo e($book["title"]); ?>
                                            —
                                            <?php echo e($book["author"]); ?>
                                            (<?php echo $book["available_quantity"]; ?> available)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Due Date</label>

                                <input
                                    type="date"
                                    class="form-control"
                                    name="due_date"
                                    min="<?php echo date("Y-m-d"); ?>"
                                    value="<?php echo $defaultDueDate; ?>"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-success issue-button w-100" id="issueButton">
                                <i class="fa-solid fa-check me-2"></i>
                                Issue Book
                            </button>
                        </form>

                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card table-card">
                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">Recent Borrowing Records</h5>
                                <small class="text-muted">Latest issued and returned books.</small>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member</th>
                                        <th>Book</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (count($recentLoans) > 0): ?>
                                        <?php foreach ($recentLoans as $loan): ?>
                                            <tr>
                                                <td>
                                                    <strong class="d-block">
                                                        <?php echo e($loan["member_name"]); ?>
                                                    </strong>

                                                    <small class="text-muted">
                                                        <?php echo e($loan["member_code"]); ?>
                                                    </small>
                                                </td>

                                                <td><?php echo e($loan["book_title"]); ?></td>

                                                <td>
                                                    <?php echo date("d M Y", strtotime($loan["due_date"])); ?>
                                                </td>

                                                <td>
                                                    <?php if ($loan["status"] === "borrowed"): ?>
                                                        <span class="status-badge borrowed">Borrowed</span>
                                                    <?php else: ?>
                                                        <span class="status-badge returned">Returned</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-5">
                                                <i class="fa-solid fa-book-open fs-3 d-block mb-2"></i>
                                                No borrowing records yet.
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

<script>
    const issueForm = document.getElementById("issueForm");
    const issueButton = document.getElementById("issueButton");

    issueForm.addEventListener("submit", function () {
        issueButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin me-2"></i>Issuing Book...';

        issueButton.disabled = true;
    });
</script>

</body>
</html>