<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["member"]);

/* Get current member */
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

/* Current borrowed books */
$currentStatement = $pdo->prepare(
    "SELECT
        borrowings.id,
        borrowings.issue_date,
        borrowings.due_date,
        DATEDIFF(CURDATE(), borrowings.due_date) AS late_days,
        books.title,
        books.author,
        books.shelf_number,
        categories.category_name
     FROM borrowings
     INNER JOIN books ON borrowings.book_id = books.id
     INNER JOIN categories ON books.category_id = categories.id
     WHERE borrowings.member_id = ?
     AND borrowings.status = 'borrowed'
     ORDER BY borrowings.due_date ASC"
);

$currentStatement->execute([$memberId]);
$currentLoans = $currentStatement->fetchAll();

/* Returned book history */
$historyStatement = $pdo->prepare(
    "SELECT
        borrowings.id,
        borrowings.issue_date,
        borrowings.due_date,
        borrowings.return_date,
        borrowings.fine_amount,
        borrowings.fine_status,
        books.title,
        books.author,
        categories.category_name
     FROM borrowings
     INNER JOIN books ON borrowings.book_id = books.id
     INNER JOIN categories ON books.category_id = categories.id
     WHERE borrowings.member_id = ?
     AND borrowings.status = 'returned'
     ORDER BY borrowings.return_date DESC"
);

$historyStatement->execute([$memberId]);
$loanHistory = $historyStatement->fetchAll();

$estimatedFine = 0;

foreach ($currentLoans as $loan) {
    if ($loan["late_days"] > 0) {
        $estimatedFine += $loan["late_days"] * 2;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loans | Smart Library</title>

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

        .summary-card {
            height: 100%;
            padding: 22px;
            border: none;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .summary-icon {
            width: 52px;
            height: 52px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 14px;
            color: #ffffff;
            font-size: 1.3rem;
        }

        .blue { background: linear-gradient(135deg, #176b9f, #54a8d9); }
        .orange { background: linear-gradient(135deg, #f39c12, #f7c65c); }
        .pink { background: linear-gradient(135deg, #e84393, #ff8cc1); }

        .summary-number {
            margin: 12px 0 2px;
            color: var(--navy);
            font-size: 1.85rem;
            font-weight: 800;
        }

        .table-card {
            border: none;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .late-badge {
            padding: 6px 10px;
            border-radius: 50px;
            color: #b42318;
            background: #fee4e2;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .ok-badge {
            padding: 6px 10px;
            border-radius: 50px;
            color: #166534;
            background: #dcfce7;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .paid-badge {
            padding: 6px 10px;
            border-radius: 50px;
            color: #166534;
            background: #dcfce7;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .unpaid-badge {
            padding: 6px 10px;
            border-radius: 50px;
            color: #a16207;
            background: #fef3c7;
            font-size: 0.75rem;
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

        <a class="nav-link" href="catalog.php">
            <i class="fa-solid fa-magnifying-glass"></i> Browse Books
        </a>

        <a class="nav-link active" href="my_loans.php">
            <i class="fa-solid fa-book-open"></i> My Borrowed Books
        </a>

        <a class="nav-link" href="my_loans.php">
            <i class="fa-solid fa-clock-rotate-left"></i> Borrowing History
        </a>

        <a class="nav-link" href="reservations.php">
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
            <small class="text-muted">Smart Library / Member</small>
            <h6 class="mb-0 fw-bold">My Borrowing Records</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">My Borrowed Books</h2>
            <p class="text-muted mb-0">
                Track your current loans, due dates, and borrowing history.
            </p>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon blue">
                        <i class="fa-solid fa-book-open"></i>
                    </div>

                    <div class="summary-number"><?php echo count($currentLoans); ?></div>
                    <div class="text-muted">Current Borrowed Books</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon orange">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div class="summary-number">
                        <?php
                        $overdueCount = 0;

                        foreach ($currentLoans as $loan) {
                            if ($loan["late_days"] > 0) {
                                $overdueCount++;
                            }
                        }

                        echo $overdueCount;
                        ?>
                    </div>

                    <div class="text-muted">Overdue Books</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon pink">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>

                    <div class="summary-number">
                        GHS <?php echo number_format($estimatedFine, 2); ?>
                    </div>

                    <div class="text-muted">Estimated Current Fine</div>
                </div>
            </div>

        </div>

        <!-- Current loans -->
        <div class="card table-card mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1">Current Loans</h5>
                <p class="text-muted small mb-4">
                    Return books on or before the due date to avoid a fine of GHS 2.00 per late day.
                </p>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Book</th>
                                <th>Category</th>
                                <th>Issued</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($currentLoans) > 0): ?>
                                <?php foreach ($currentLoans as $loan): ?>
                                    <tr>
                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($loan["title"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($loan["author"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <span class="badge text-bg-light">
                                                <?php echo e($loan["category_name"]); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($loan["issue_date"])); ?>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($loan["due_date"])); ?>
                                        </td>

                                        <td>
                                            <?php if ($loan["late_days"] > 0): ?>
                                                <span class="late-badge">
                                                    <?php echo $loan["late_days"]; ?> day(s) overdue
                                                </span>
                                            <?php else: ?>
                                                <span class="ok-badge">
                                                    On Time
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-book-open fs-3 d-block mb-2"></i>
                                        You do not currently have any borrowed books.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Loan history -->
        <div class="card table-card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1">Borrowing History</h5>
                <p class="text-muted small mb-4">
                    Books that you have returned to the library.
                </p>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Book</th>
                                <th>Issue Date</th>
                                <th>Return Date</th>
                                <th>Fine</th>
                                <th>Payment Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($loanHistory) > 0): ?>
                                <?php foreach ($loanHistory as $history): ?>
                                    <tr>
                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($history["title"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($history["author"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($history["issue_date"])); ?>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($history["return_date"])); ?>
                                        </td>

                                        <td>
                                            GHS <?php echo number_format($history["fine_amount"], 2); ?>
                                        </td>

                                        <td>
                                            <?php if ($history["fine_status"] === "paid"): ?>
                                                <span class="paid-badge">Paid</span>
                                            <?php elseif ($history["fine_status"] === "waived"): ?>
                                                <span class="ok-badge">Waived</span>
                                            <?php else: ?>
                                                <span class="unpaid-badge">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-clock-rotate-left fs-3 d-block mb-2"></i>
                                        No returned book history yet.
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