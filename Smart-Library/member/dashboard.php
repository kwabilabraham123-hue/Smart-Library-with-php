<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["member"]);

$memberStatement = $pdo->prepare(
    "SELECT members.id, members.member_code, users.full_name
     FROM members
     INNER JOIN users ON members.user_id = users.id
     WHERE members.user_id = ?
     LIMIT 1"
);

$memberStatement->execute([$_SESSION["user_id"]]);
$member = $memberStatement->fetch();

if (!$member) {
    die("Member profile not found.");
}

$memberId = $member["id"];
$firstName = explode(" ", $member["full_name"])[0];
$initial = strtoupper(substr($member["full_name"], 0, 1));

$currentLoansStatement = $pdo->prepare(
    "SELECT COUNT(*) FROM borrowings
     WHERE member_id = ? AND status = 'borrowed'"
);

$currentLoansStatement->execute([$memberId]);
$currentLoans = $currentLoansStatement->fetchColumn();

$overdueStatement = $pdo->prepare(
    "SELECT COUNT(*) FROM borrowings
     WHERE member_id = ? AND status = 'borrowed' AND due_date < CURDATE()"
);

$overdueStatement->execute([$memberId]);
$overdueBooks = $overdueStatement->fetchColumn();

$reservationStatement = $pdo->prepare(
    "SELECT COUNT(*) FROM reservations
     WHERE member_id = ? AND status IN ('pending', 'ready')"
);

$reservationStatement->execute([$memberId]);
$activeReservations = $reservationStatement->fetchColumn();

$fineStatement = $pdo->prepare(
    "SELECT COALESCE(SUM(
        CASE
            WHEN status = 'borrowed' AND due_date < CURDATE()
            THEN DATEDIFF(CURDATE(), due_date) * 2
            ELSE 0
        END
    ), 0)
    FROM borrowings
    WHERE member_id = ?"
);

$fineStatement->execute([$memberId]);
$estimatedFine = $fineStatement->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root { --navy:#102a43; --blue:#176b9f; --purple:#6d3fc7; --pink:#e84393; --bg:#f5f7fb; }
        body { margin:0; background:var(--bg); color:#243447; font-family:Arial,Helvetica,sans-serif; }
        .sidebar { position:fixed; top:0; left:0; width:255px; min-height:100vh; padding:22px 16px; color:#fff; background:linear-gradient(180deg,#102a43,#123d63); }
        .brand { display:flex; align-items:center; gap:10px; padding:8px 12px 24px; border-bottom:1px solid rgba(255,255,255,.12); font-size:1.15rem; font-weight:800; }
        .brand-icon { width:38px; height:38px; display:flex; justify-content:center; align-items:center; border-radius:10px; background:linear-gradient(135deg,#e84393,#f39c12); }
        .sidebar-label { margin:25px 12px 10px; color:rgba(255,255,255,.5); font-size:.7rem; font-weight:700; letter-spacing:1px; }
        .sidebar .nav-link { margin-bottom:5px; padding:11px 12px; border-radius:10px; color:rgba(255,255,255,.8); }
        .sidebar .nav-link i { width:23px; }
        .sidebar .nav-link:hover,.sidebar .nav-link.active { color:#fff; background:rgba(255,255,255,.13); }
        .main { min-height:100vh; margin-left:255px; }
        .topbar { display:flex; justify-content:space-between; align-items:center; padding:17px 30px; background:#fff; box-shadow:0 2px 14px rgba(16,42,67,.06); }
        .content { padding:30px; }
        .avatar { width:42px; height:42px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; color:#fff; background:linear-gradient(135deg,var(--pink),var(--purple)); font-weight:700; }
        .welcome { position:relative; overflow:hidden; padding:30px; border-radius:18px; color:#fff; background:radial-gradient(circle at 88% 20%,rgba(244,185,66,.28),transparent 25%),linear-gradient(135deg,#176b9f,#6d3fc7); }
        .welcome:after { position:absolute; right:35px; bottom:-42px; color:rgba(255,255,255,.12); content:"\f02d"; font-family:"Font Awesome 6 Free"; font-size:10rem; font-weight:900; }
        .welcome h2,.welcome p{position:relative;z-index:1}
        .stat { height:100%; padding:22px; border:0; border-radius:16px; background:#fff; box-shadow:0 7px 20px rgba(16,42,67,.07); transition:.2s; }
        .stat:hover{transform:translateY(-5px)}
        .icon { width:50px; height:50px; display:flex; align-items:center; justify-content:center; border-radius:14px; color:#fff; font-size:1.2rem; }
        .i1{background:linear-gradient(135deg,#176b9f,#54a8d9)} .i2{background:linear-gradient(135deg,#f39c12,#f7c65c)}
        .i3{background:linear-gradient(135deg,#e84393,#ff8cc1)} .i4{background:linear-gradient(135deg,#159957,#38ef7d)}
        .number { margin:13px 0 2px; color:var(--navy); font-size:1.8rem; font-weight:800; }
        .quick { display:block; padding:20px; border-radius:15px; color:#fff; text-decoration:none; transition:.2s; }
        .quick:hover { color:#fff; transform:translateY(-5px); box-shadow:0 12px 25px rgba(16,42,67,.18); }
        .q1{background:linear-gradient(135deg,#176b9f,#54a8d9)} .q2{background:linear-gradient(135deg,#6d3fc7,#a178e8)}
        .q3{background:linear-gradient(135deg,#e84393,#ff8cc1)} .q4{background:linear-gradient(135deg,#159957,#38ef7d)}
        @media(max-width:991px){.sidebar{position:relative;width:100%;min-height:auto}.main{margin-left:0}.content{padding:20px}.topbar{padding:15px 20px}}
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <span class="brand-icon"><i class="fa-solid fa-book-open-reader"></i></span>
        Smart Library
    </div>

    <p class="sidebar-label">MEMBER MENU</p>

    <nav class="nav flex-column">
        <a class="nav-link active" href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
        <a class="nav-link" href="catalog.php"><i class="fa-solid fa-magnifying-glass"></i> Browse Books</a>
        <a class="nav-link" href="my_loans.php"><i class="fa-solid fa-book-open"></i> My Borrowed Books</a>
        <a class="nav-link" href="my_loans.php"><i class="fa-solid fa-clock-rotate-left"></i> Borrowing History</a>
        <a class="nav-link" href="reservations.php"><i class="fa-solid fa-bookmark"></i> My Reservations</a>
        <a class="nav-link" href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
    </nav>

    <p class="sidebar-label">ACCOUNT</p>

    <nav class="nav flex-column">
        <a class="nav-link text-warning" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </nav>
</aside>

<main class="main">
    <header class="topbar">
        <div>
            <small class="text-muted">Smart Library / Member</small>
            <h6 class="mb-0 fw-bold">Member Portal</h6>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="avatar"><?php echo e($initial); ?></span>
            <div>
                <strong class="d-block small"><?php echo e($member["full_name"]); ?></strong>
                <small class="text-muted"><?php echo e($member["member_code"]); ?></small>
            </div>
        </div>
    </header>

    <section class="content">
        <div class="welcome mb-4">
            <h2 class="fw-bold mb-2">Hello, <?php echo e($firstName); ?>! 👋</h2>
            <p class="mb-0 text-white-50">
                Explore books, manage your loans, and track reservation requests.
            </p>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat">
                    <div class="icon i1"><i class="fa-solid fa-book-open"></i></div>
                    <div class="number"><?php echo $currentLoans; ?></div>
                    <div class="text-muted">Current Loans</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat">
                    <div class="icon i2"><i class="fa-solid fa-clock"></i></div>
                    <div class="number"><?php echo $overdueBooks; ?></div>
                    <div class="text-muted">Overdue Books</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat">
                    <div class="icon i3"><i class="fa-solid fa-money-bill-wave"></i></div>
                    <div class="number">GHS <?php echo number_format($estimatedFine, 2); ?></div>
                    <div class="text-muted">Estimated Fine</div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat">
                    <div class="icon i4"><i class="fa-solid fa-bookmark"></i></div>
                    <div class="number"><?php echo $activeReservations; ?></div>
                    <div class="text-muted">Active Reservations</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-xl-3">
                <a class="quick q1" href="catalog.php">
                    <i class="fa-solid fa-magnifying-glass fs-3"></i>
                    <h5 class="fw-bold mt-3 mb-1">Browse Books</h5>
                    <small>Find books in the library catalog</small>
                </a>
            </div>

            <div class="col-md-6 col-xl-3">
                <a class="quick q2" href="my_loans.php">
                    <i class="fa-solid fa-book-open fs-3"></i>
                    <h5 class="fw-bold mt-3 mb-1">My Loans</h5>
                    <small>Check borrowed books and due dates</small>
                </a>
            </div>

            <div class="col-md-6 col-xl-3">
                <a class="quick q3" href="reservations.php">
                    <i class="fa-solid fa-bookmark fs-3"></i>
                    <h5 class="fw-bold mt-3 mb-1">Reservations</h5>
                    <small>View book reservation requests</small>
                </a>
            </div>

            <div class="col-md-6 col-xl-3">
                <a class="quick q4" href="profile.php">
                    <i class="fa-solid fa-user-gear fs-3"></i>
                    <h5 class="fw-bold mt-3 mb-1">My Profile</h5>
                    <small>Update your details and password</small>
                </a>
            </div>
        </div>
    </section>
</main>

</body>
</html>