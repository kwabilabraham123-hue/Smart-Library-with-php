<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin", "librarian"]);

$overdueStatement = $pdo->query(
    "SELECT
        borrowings.id,
        borrowings.issue_date,
        borrowings.due_date,
        DATEDIFF(CURDATE(), borrowings.due_date) AS late_days,
        (DATEDIFF(CURDATE(), borrowings.due_date) * 2) AS estimated_fine,
        books.title AS book_title,
        books.author,
        members.member_code,
        members.phone,
        users.full_name AS member_name,
        users.email AS member_email
     FROM borrowings
     INNER JOIN books ON borrowings.book_id = books.id
     INNER JOIN members ON borrowings.member_id = members.id
     INNER JOIN users ON members.user_id = users.id
     WHERE borrowings.status = 'borrowed'
     AND borrowings.due_date < CURDATE()
     ORDER BY borrowings.due_date ASC"
);

$overdueBooks = $overdueStatement->fetchAll();

$totalFine = 0;

foreach ($overdueBooks as $book) {
    $totalFine += $book["estimated_fine"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overdue Books | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #102a43;
            --blue: #176b9f;
            --red: #dc3545;
            --orange: #f39c12;
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
            padding: 23px;
            border: none;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .summary-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            color: #ffffff;
            background: linear-gradient(135deg, #dc3545, #ff7a86);
            font-size: 1.3rem;
        }

        .summary-number {
            margin: 12px 0 2px;
            color: var(--navy);
            font-size: 1.9rem;
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
            font-size: 0.76rem;
            font-weight: 700;
        }

        .fine-badge {
            padding: 6px 10px;
            border-radius: 50px;
            color: #a16207;
            background: #fef3c7;
            font-size: 0.76rem;
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

        @media print {
            .sidebar,
            .topbar,
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
            }

            .page-content {
                padding: 0;
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

        <a class="nav-link" href="return_book.php">
            <i class="fa-solid fa-rotate-left"></i> Return Book
        </a>

        <a class="nav-link active" href="overdue_books.php">
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
            <small class="text-muted">Smart Library / Fines</small>
            <h6 class="mb-0 fw-bold">Overdue Book Monitor</h6>
        </div>

        <div class="d-flex gap-2 no-print">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-print me-1"></i>
                Print
            </button>

            <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Dashboard
            </a>
        </div>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">Overdue Books</h2>
            <p class="text-muted mb-0">
                Monitor late returns and estimated overdue fines.
            </p>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-md-6">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div class="summary-number"><?php echo count($overdueBooks); ?></div>
                    <div class="text-muted">Overdue Borrowing Records</div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="summary-card">
                    <div class="summary-icon" style="background: linear-gradient(135deg, #f39c12, #f7c65c);">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>

                    <div class="summary-number">GHS <?php echo number_format($totalFine, 2); ?></div>
                    <div class="text-muted">Estimated Outstanding Fines</div>
                </div>
            </div>

        </div>

        <div class="card table-card">
            <div class="card-body p-4">

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Member</th>
                                <th>Book</th>
                                <th>Contact</th>
                                <th>Due Date</th>
                                <th>Late Days</th>
                                <th>Estimated Fine</th>
                                <th class="text-end no-print">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($overdueBooks) > 0): ?>
                                <?php foreach ($overdueBooks as $book): ?>
                                    <tr>
                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($book["member_name"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($book["member_code"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($book["book_title"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($book["author"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <small class="d-block">
                                                <i class="fa-solid fa-phone me-1"></i>
                                                <?php echo e($book["phone"]); ?>
                                            </small>

                                            <small class="text-muted">
                                                <?php echo e($book["member_email"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($book["due_date"])); ?>
                                        </td>

                                        <td>
                                            <span class="late-badge">
                                                <?php echo $book["late_days"]; ?> day(s)
                                            </span>
                                        </td>

                                        <td>
                                            <span class="fine-badge">
                                                GHS <?php echo number_format($book["estimated_fine"], 2); ?>
                                            </span>
                                        </td>

                                        <td class="text-end no-print">
                                            <a
                                                href="return_book.php?search=<?php echo urlencode($book["member_code"]); ?>"
                                                class="btn btn-sm btn-success"
                                            >
                                                <i class="fa-solid fa-rotate-left me-1"></i>
                                                Return
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-circle-check text-success fs-2 d-block mb-2"></i>
                                        Great! There are no overdue books at the moment.
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