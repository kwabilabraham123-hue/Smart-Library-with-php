<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin"]);

$totalBooks = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();

$totalCopies = $pdo->query(
    "SELECT COALESCE(SUM(quantity), 0) FROM books"
)->fetchColumn();

$availableCopies = $pdo->query(
    "SELECT COALESCE(SUM(available_quantity), 0) FROM books"
)->fetchColumn();

$totalMembers = $pdo->query(
    "SELECT COUNT(*) FROM members"
)->fetchColumn();

$borrowedBooks = $pdo->query(
    "SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed'"
)->fetchColumn();

$overdueBooks = $pdo->query(
    "SELECT COUNT(*)
     FROM borrowings
     WHERE status = 'borrowed'
     AND due_date < CURDATE()"
)->fetchColumn();

$recentBorrowings = $pdo->query(
    "SELECT
        users.full_name AS member_name,
        books.title AS book_title,
        borrowings.due_date,
        borrowings.status
     FROM borrowings
     INNER JOIN members ON borrowings.member_id = members.id
     INNER JOIN users ON members.user_id = users.id
     INNER JOIN books ON borrowings.book_id = books.id
     ORDER BY borrowings.id DESC
     LIMIT 6"
)->fetchAll();

$firstName = explode(" ", $_SESSION["full_name"])[0];
$initial = strtoupper(substr($_SESSION["full_name"], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Smart Library</title>

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
            color: #243447;
            background: var(--background);
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
            align-items: center;
            justify-content: space-between;
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

        .admin-avatar {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            font-weight: 700;
        }

        .stat-card {
            height: 100%;
            padding: 22px;
            border: none;
            border-radius: 16px;
            background: white;
            box-shadow: 0 7px 20px rgba(16, 42, 67, 0.07);
            transition: 0.25s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 13px 30px rgba(16, 42, 67, 0.13);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            color: white;
            font-size: 1.25rem;
        }

        .blue-icon {
            background: linear-gradient(135deg, #176b9f, #54a8d9);
        }

        .green-icon {
            background: linear-gradient(135deg, #159957, #38ef7d);
        }

        .purple-icon {
            background: linear-gradient(135deg, #6d3fc7, #a178e8);
        }

        .orange-icon {
            background: linear-gradient(135deg, #f39c12, #f7c65c);
        }

        .stat-number {
            margin: 13px 0 2px;
            color: var(--navy);
            font-size: 1.85rem;
            font-weight: 800;
        }

        .table-card {
            border: none;
            border-radius: 16px;
            background: white;
            box-shadow: 0 7px 20px rgba(16, 42, 67, 0.07);
        }

        .status-badge {
            padding: 6px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .borrowed-status {
            color: #a16207;
            background: #fef3c7;
        }

        .returned-status {
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

            .dashboard-body {
                padding: 20px;
            }

            .topbar {
                padding: 15px 20px;
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

        <a class="nav-link active" href="dashboard.php">
            <i class="fa-solid fa-chart-pie"></i>
            Dashboard
        </a>

        <a class="nav-link" href="books.php">
            <i class="fa-solid fa-book"></i>
            Books
        </a>

        <a class="nav-link" href="categories.php">
            <i class="fa-solid fa-layer-group"></i>
            Categories
        </a>

        <a class="nav-link" href="members.php">
            <i class="fa-solid fa-users"></i>
            Members
        </a>

        <a class="nav-link" href="librarians.php">
            <i class="fa-solid fa-user-tie"></i>
            Librarians
        </a>

        <a class="nav-link" href="issue_book.php">
            <i class="fa-solid fa-hand-holding"></i>
            Issue Book
        </a>

        <a class="nav-link" href="return_book.php">
            <i class="fa-solid fa-rotate-left"></i>
            Return Book
        </a>

        <a class="nav-link" href="overdue_books.php">
            <i class="fa-solid fa-clock"></i>
            Overdue Books
        </a>

        <a class="nav-link" href="manage_reservations.php">
            <i class="fa-solid fa-bookmark"></i>
            Reservations
        </a>

        <a class="nav-link" href="fines.php">
            <i class="fa-solid fa-money-bill-wave"></i>
            Fine Payments
        </a>

        <a class="nav-link" href="reports.php">
            <i class="fa-solid fa-chart-column"></i>
            Reports
        </a>

    </nav>

    <p class="sidebar-label">ACCOUNT</p>

    <nav class="nav flex-column">

        <a class="nav-link" href="change_password.php">
            <i class="fa-solid fa-key"></i>
            Change Password
        </a>

        <a class="nav-link text-warning" href="../auth/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
        </a>

    </nav>

</aside>

<main class="main-content">

    <header class="topbar">
        <div>
            <small class="text-muted">Smart Library / Admin</small>
            <h6 class="mb-0 fw-bold">Administration Panel</h6>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="admin-avatar">
                <?php echo e($initial); ?>
            </span>

            <div>
                <strong class="d-block small">
                    <?php echo e($_SESSION["full_name"]); ?>
                </strong>

                <small class="text-muted">Administrator</small>
            </div>
        </div>
    </header>

    <section class="dashboard-body">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="page-title mb-1">
                    Welcome, <?php echo e($firstName); ?>! 👋
                </h2>

                <p class="text-muted mb-0">
                    Here is an overview of your library today.
                </p>
            </div>

            <small class="text-muted">
                <i class="fa-regular fa-calendar me-1"></i>
                <?php echo date("l, d F Y"); ?>
            </small>
        </div>

        <?php if ($overdueBooks > 0): ?>
            <div class="alert alert-warning">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>

                <strong>Attention:</strong>
                <?php echo $overdueBooks; ?> book(s) are overdue.

                <a href="overdue_books.php" class="alert-link">
                    View overdue books
                </a>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon blue-icon">
                        <i class="fa-solid fa-book"></i>
                    </div>

                    <div class="stat-number"><?php echo $totalBooks; ?></div>

                    <div class="text-muted">Book Titles</div>

                    <small class="text-primary">
                        Total copies: <?php echo $totalCopies; ?>
                    </small>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon green-icon">
                        <i class="fa-solid fa-book-open"></i>
                    </div>

                    <div class="stat-number"><?php echo $availableCopies; ?></div>

                    <div class="text-muted">Available Copies</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon purple-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <div class="stat-number"><?php echo $totalMembers; ?></div>

                    <div class="text-muted">Registered Members</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon orange-icon">
                        <i class="fa-solid fa-arrow-right-arrow-left"></i>
                    </div>

                    <div class="stat-number"><?php echo $borrowedBooks; ?></div>

                    <div class="text-muted">Books on Loan</div>
                </div>
            </div>

        </div>

        <div class="card table-card">
            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">Recent Borrowing Activity</h5>

                        <small class="text-muted">
                            Latest library transactions
                        </small>
                    </div>

                    <a href="reports.php" class="btn btn-sm btn-outline-primary">
                        View Reports
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th>Member</th>
                                <th>Book</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($recentBorrowings) > 0): ?>

                                <?php foreach ($recentBorrowings as $borrowing): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <?php echo e($borrowing["member_name"]); ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?php echo e($borrowing["book_title"]); ?>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($borrowing["due_date"])); ?>
                                        </td>

                                        <td>
                                            <?php if ($borrowing["status"] === "borrowed"): ?>
                                                <span class="status-badge borrowed-status">
                                                    Borrowed
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge returned-status">
                                                    Returned
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No borrowing records available yet.
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

</body>
</html>