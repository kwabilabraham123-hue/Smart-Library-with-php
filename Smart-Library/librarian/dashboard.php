<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["librarian"]);

$totalBooks = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$availableCopies = $pdo->query("SELECT COALESCE(SUM(available_quantity), 0) FROM books")->fetchColumn();
$totalMembers = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$borrowedBooks = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed'")->fetchColumn();
$overdueBooks = $pdo->query(
    "SELECT COUNT(*) FROM borrowings
     WHERE status = 'borrowed' AND due_date < CURDATE()"
)->fetchColumn();

$firstName = explode(" ", $_SESSION["full_name"])[0];
$initial = strtoupper(substr($_SESSION["full_name"], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Dashboard | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root { --navy:#102a43; --teal:#0f9b8e; --blue:#176b9f; --purple:#6d3fc7; --bg:#f5f7fb; }
        body { margin:0; background:var(--bg); color:#243447; font-family:Arial,Helvetica,sans-serif; }
        .sidebar { position:fixed; top:0; left:0; width:255px; min-height:100vh; padding:22px 16px; color:#fff; background:linear-gradient(180deg,#102a43,#0d665d); }
        .brand { display:flex; align-items:center; gap:10px; padding:8px 12px 24px; border-bottom:1px solid rgba(255,255,255,.12); font-size:1.15rem; font-weight:800; }
        .brand-icon { width:38px; height:38px; display:flex; align-items:center; justify-content:center; border-radius:10px; background:linear-gradient(135deg,#f39c12,#f7c65c); }
        .sidebar-label { margin:25px 12px 10px; color:rgba(255,255,255,.5); font-size:.7rem; font-weight:700; letter-spacing:1px; }
        .sidebar .nav-link { margin-bottom:5px; padding:11px 12px; border-radius:10px; color:rgba(255,255,255,.8); }
        .sidebar .nav-link i { width:23px; }
        .sidebar .nav-link:hover,.sidebar .nav-link.active { color:#fff; background:rgba(255,255,255,.13); }
        .main { min-height:100vh; margin-left:255px; }
        .topbar { display:flex; justify-content:space-between; align-items:center; padding:17px 30px; background:#fff; box-shadow:0 2px 14px rgba(16,42,67,.06); }
        .content { padding:30px; }
        .avatar { width:42px; height:42px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; color:#fff; background:linear-gradient(135deg,var(--teal),var(--blue)); font-weight:700; }
        .title { color:var(--navy); font-weight:800; }
        .quick { display:block; height:100%; padding:20px; border-radius:15px; color:#fff; text-decoration:none; transition:.2s; }
        .quick:hover { color:#fff; transform:translateY(-5px); box-shadow:0 12px 25px rgba(16,42,67,.18); }
        .q1{background:linear-gradient(135deg,#176b9f,#54a8d9)} .q2{background:linear-gradient(135deg,#0f9b8e,#37c5b4)}
        .q3{background:linear-gradient(135deg,#6d3fc7,#a178e8)} .q4{background:linear-gradient(135deg,#f39c12,#f7c65c)}
        .stat { height:100%; padding:20px; border:0; border-radius:16px; background:#fff; box-shadow:0 7px 20px rgba(16,42,67,.07); }
        .icon { width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:14px; color:#fff; font-size:1.15rem; }
        .i1{background:#176b9f}.i2{background:#159957}.i3{background:#6d3fc7}.i4{background:#f39c12}
        .number { margin:12px 0 2px; color:var(--navy); font-size:1.7rem; font-weight:800; }
        @media(max-width:991px){.sidebar{position:relative;width:100%;min-height:auto}.main{margin-left:0}.content{padding:20px}.topbar{padding:15px 20px}}
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <span class="brand-icon"><i class="fa-solid fa-book-open-reader"></i></span>
        Smart Library
    </div>

    <p class="sidebar-label">LIBRARIAN MENU</p>

    <nav class="nav flex-column">
        <a class="nav-link active" href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <a class="nav-link" href="../admin/issue_book.php"><i class="fa-solid fa-hand-holding"></i> Issue Book</a>
        <a class="nav-link" href="../admin/return_book.php"><i class="fa-solid fa-rotate-left"></i> Return Book</a>
        <a class="nav-link" href="../admin/overdue_books.php"><i class="fa-solid fa-clock"></i> Overdue Books</a>
        <a class="nav-link" href="../admin/manage_reservations.php"><i class="fa-solid fa-bookmark"></i> Reservations</a>
        <a class="nav-link" href="../admin/fines.php"><i class="fa-solid fa-money-bill-wave"></i> Fine Payments</a>
        <a class="nav-link" href="../admin/reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
    </nav>

    <p class="sidebar-label">ACCOUNT</p>

    <nav class="nav flex-column">
        <a class="nav-link text-warning" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </nav>
</aside>

<main class="main">
    <header class="topbar">
        <div>
            <small class="text-muted">Smart Library / Librarian</small>
            <h6 class="mb-0 fw-bold">Librarian Workspace</h6>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="avatar"><?php echo e($initial); ?></span>
            <div>
                <strong class="d-block small"><?php echo e($_SESSION["full_name"]); ?></strong>
                <small class="text-muted">Librarian</small>
            </div>
        </div>
    </header>

    <section class="content">
        <div class="mb-4">
            <h2 class="title mb-1">Good day, <?php echo e($firstName); ?>! 📚</h2>
            <p class="text-muted mb-0">Manage daily library activities from your workspace.</p>
        </div>

        <?php if ($overdueBooks > 0): ?>
            <div class="alert alert-warning">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <?php echo $overdueBooks; ?> book(s) are overdue.
                <a href="../admin/overdue_books.php" class="alert-link">View now</a>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <a class="quick q1" href="../admin/issue_book.php">
                    <i class="fa-solid fa-hand-holding fs-3"></i>
                    <h5 class="fw-bold mt-3 mb-1">Issue Book</h5>
                    <small>Create a borrowing transaction</small>
                </a>
            </div>

            <div class="col-sm-6 col-xl-3">
                <a class="quick q2" href="../admin/return_book.php">
                    <i class="fa-solid fa-rotate-left fs-3"></i>
                    <h5 class="fw-bold mt-3 mb-1">Return Book</h5>
                    <small>Process a returned book</small>
                </a>
            </div>

            <div class="col-sm-6 col-xl-3">
                <a class="quick q3" href="../admin/manage_reservations.php">
                    <i class="fa-solid fa-bookmark fs-3"></i>
                    <h5 class="fw-bold mt-3 mb-1">Reservations</h5>
                    <small>Prepare reserved books</small>
                </a>
            </div>

            <div class="col-sm-6 col-xl-3">
                <a class="quick q4" href="../admin/fines.php">
                    <i class="fa-solid fa-money-bill-wave fs-3"></i>
                    <h5 class="fw-bold mt-3 mb-1">Fine Payments</h5>
                    <small>Record member payments</small>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat"><div class="icon i1"><i class="fa-solid fa-book"></i></div><div class="number"><?php echo $totalBooks; ?></div><div class="text-muted">Book Titles</div></div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat"><div class="icon i2"><i class="fa-solid fa-book-open"></i></div><div class="number"><?php echo $availableCopies; ?></div><div class="text-muted">Available Copies</div></div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat"><div class="icon i3"><i class="fa-solid fa-users"></i></div><div class="number"><?php echo $totalMembers; ?></div><div class="text-muted">Registered Members</div></div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat"><div class="icon i4"><i class="fa-solid fa-arrow-right-arrow-left"></i></div><div class="number"><?php echo $borrowedBooks; ?></div><div class="text-muted">Books on Loan</div></div>
            </div>
        </div>
    </section>
</main>

</body>
</html>