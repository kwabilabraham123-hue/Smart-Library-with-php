<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin", "librarian"]);

$startDate = $_GET["start_date"] ?? date("Y-m-01");
$endDate = $_GET["end_date"] ?? date("Y-m-d");

/* Borrowing transactions for the selected period */
$transactionStatement = $pdo->prepare(
    "SELECT
        borrowings.id,
        users.full_name AS member_name,
        members.member_code,
        books.title AS book_title,
        books.author,
        borrowings.issue_date,
        borrowings.due_date,
        borrowings.return_date,
        borrowings.fine_amount,
        borrowings.fine_status,
        borrowings.status
     FROM borrowings
     INNER JOIN members ON borrowings.member_id = members.id
     INNER JOIN users ON members.user_id = users.id
     INNER JOIN books ON borrowings.book_id = books.id
     WHERE borrowings.issue_date BETWEEN ? AND ?
     ORDER BY borrowings.id DESC"
);

$transactionStatement->execute([$startDate, $endDate]);
$transactions = $transactionStatement->fetchAll();

/* Export report as CSV */
if (isset($_GET["export"]) && $_GET["export"] === "csv") {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=smart_library_report.csv");

    $output = fopen("php://output", "w");

    fputcsv($output, [
        "Transaction ID",
        "Member",
        "Member ID",
        "Book Title",
        "Author",
        "Issue Date",
        "Due Date",
        "Return Date",
        "Fine Amount",
        "Fine Status",
        "Status"
    ]);

    foreach ($transactions as $transaction) {
        fputcsv($output, [
            $transaction["id"],
            $transaction["member_name"],
            $transaction["member_code"],
            $transaction["book_title"],
            $transaction["author"],
            $transaction["issue_date"],
            $transaction["due_date"],
            $transaction["return_date"],
            $transaction["fine_amount"],
            $transaction["fine_status"],
            $transaction["status"]
        ]);
    }

    fclose($output);
    exit;
}

/* Summary statistics */
$totalBooks = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();

$totalMembers = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();

$borrowedBooks = $pdo->query(
    "SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed'"
)->fetchColumn();

$returnedBooks = $pdo->query(
    "SELECT COUNT(*) FROM borrowings WHERE status = 'returned'"
)->fetchColumn();

$overdueBooks = $pdo->query(
    "SELECT COUNT(*)
     FROM borrowings
     WHERE status = 'borrowed'
     AND due_date < CURDATE()"
)->fetchColumn();

$fineStatement = $pdo->prepare(
    "SELECT COALESCE(SUM(fine_amount), 0)
     FROM borrowings
     WHERE issue_date BETWEEN ? AND ?"
);

$fineStatement->execute([$startDate, $endDate]);
$totalFines = $fineStatement->fetchColumn();

$reportTotal = count($transactions);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #102a43;
            --blue: #176b9f;
            --purple: #6d3fc7;
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

        .filter-card,
        .table-card,
        .stat-card {
            border: none;
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .filter-card {
            padding: 22px;
        }

        .form-control {
            padding: 11px 13px;
            border-radius: 10px;
        }

        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.22rem rgba(109, 63, 199, 0.12);
        }

        .stat-card {
            height: 100%;
            padding: 20px;
            transition: 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 13px;
            color: white;
            font-size: 1.15rem;
        }

        .blue { background: linear-gradient(135deg, #176b9f, #54a8d9); }
        .purple { background: linear-gradient(135deg, #6d3fc7, #a178e8); }
        .green { background: linear-gradient(135deg, #159957, #38ef7d); }
        .orange { background: linear-gradient(135deg, #f39c12, #f7c65c); }
        .red { background: linear-gradient(135deg, #dc3545, #ff7a86); }
        .pink { background: linear-gradient(135deg, #e84393, #ff8cc1); }

        .stat-number {
            margin: 12px 0 2px;
            color: var(--navy);
            font-size: 1.65rem;
            font-weight: 800;
        }

        .status-badge {
            padding: 6px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .borrowed { color: #a16207; background: #fef3c7; }
        .returned { color: #166534; background: #dcfce7; }

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

    <p class="sidebar-label">ADMIN MENU</p>

    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard.php">
            <i class="fa-solid fa-chart-pie"></i> Dashboard
        </a>

        <a class="nav-link" href="books.php">
            <i class="fa-solid fa-book"></i> Books
        </a>

        <a class="nav-link" href="members.php">
            <i class="fa-solid fa-users"></i> Members
        </a>

        <a class="nav-link" href="issue_book.php">
            <i class="fa-solid fa-hand-holding"></i> Issue Book
        </a>

        <a class="nav-link" href="return_book.php">
            <i class="fa-solid fa-rotate-left"></i> Return Book
        </a>

        <a class="nav-link" href="overdue_books.php">
            <i class="fa-solid fa-clock"></i> Overdue Books
        </a>

        <a class="nav-link active" href="reports.php">
            <i class="fa-solid fa-chart-column"></i> Reports
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
            <small class="text-muted">Smart Library / Reports</small>
            <h6 class="mb-0 fw-bold">Library Reports</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="page-title mb-1">Library Reports</h2>
                <p class="text-muted mb-0">
                    Review borrowing activity and library performance.
                </p>
            </div>

            <div class="no-print d-flex gap-2">
                <a
                    href="reports.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&export=csv"
                    class="btn btn-success"
                >
                    <i class="fa-solid fa-file-csv me-1"></i>
                    Export CSV
                </a>

                <button onclick="window.print()" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-print me-1"></i>
                    Print
                </button>
            </div>
        </div>

        <!-- Date filter -->
        <div class="filter-card mb-4 no-print">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Start Date</label>
                    <input
                        type="date"
                        class="form-control"
                        name="start_date"
                        value="<?php echo e($startDate); ?>"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">End Date</label>
                    <input
                        type="date"
                        class="form-control"
                        name="end_date"
                        value="<?php echo e($endDate); ?>"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fa-solid fa-filter me-1"></i>
                        Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary cards -->
        <div class="row g-4 mb-4">

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-book"></i>
                    </div>
                    <div class="stat-number"><?php echo $totalBooks; ?></div>
                    <div class="text-muted">Book Titles</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $totalMembers; ?></div>
                    <div class="text-muted">Registered Members</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fa-solid fa-arrow-right-arrow-left"></i>
                    </div>
                    <div class="stat-number"><?php echo $borrowedBooks; ?></div>
                    <div class="text-muted">Current Borrowings</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fa-solid fa-rotate-left"></i>
                    </div>
                    <div class="stat-number"><?php echo $returnedBooks; ?></div>
                    <div class="text-muted">Returned Books</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $overdueBooks; ?></div>
                    <div class="text-muted">Overdue Books</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon pink">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number">GHS <?php echo number_format($totalFines, 2); ?></div>
                    <div class="text-muted">Fines in Selected Period</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div class="stat-number"><?php echo $reportTotal; ?></div>
                    <div class="text-muted">Transactions in Report</div>
                </div>
            </div>

        </div>

        <!-- Transaction report table -->
        <div class="card table-card">
            <div class="card-body p-4">

                <div class="mb-3">
                    <h5 class="fw-bold mb-1">Borrowing Transaction Report</h5>
                    <small class="text-muted">
                        Period:
                        <?php echo date("d M Y", strtotime($startDate)); ?>
                        —
                        <?php echo date("d M Y", strtotime($endDate)); ?>
                    </small>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th>Book</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Fine</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($transactions) > 0): ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo $transaction["id"]; ?></td>

                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($transaction["member_name"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($transaction["member_code"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($transaction["book_title"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($transaction["author"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($transaction["issue_date"])); ?>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($transaction["due_date"])); ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo $transaction["return_date"]
                                                ? date("d M Y", strtotime($transaction["return_date"]))
                                                : "-";
                                            ?>
                                        </td>

                                        <td>
                                            GHS <?php echo number_format($transaction["fine_amount"], 2); ?>
                                        </td>

                                        <td>
                                            <?php if ($transaction["status"] === "borrowed"): ?>
                                                <span class="status-badge borrowed">Borrowed</span>
                                            <?php else: ?>
                                                <span class="status-badge returned">Returned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-file-circle-xmark fs-3 d-block mb-2"></i>
                                        No borrowing records found for this period.
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