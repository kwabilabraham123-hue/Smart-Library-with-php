<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["member"]);

$message = "";
$messageType = "success";

/* Find current member ID */
$memberStatement = $pdo->prepare(
    "SELECT id FROM members WHERE user_id = ? LIMIT 1"
);

$memberStatement->execute([$_SESSION["user_id"]]);
$member = $memberStatement->fetch();

if (!$member) {
    die("Member profile not found.");
}

$memberId = $member["id"];

/* Cancel a pending reservation */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $reservationId = $_POST["reservation_id"] ?? "";

    $cancelStatement = $pdo->prepare(
        "UPDATE reservations
         SET status = 'cancelled'
         WHERE id = ?
         AND member_id = ?
         AND status = 'pending'"
    );

    $cancelStatement->execute([$reservationId, $memberId]);

    if ($cancelStatement->rowCount() > 0) {
        header("Location: reservations.php?message=cancelled");
        exit;
    }

    $message = "This reservation cannot be cancelled.";
    $messageType = "danger";
}

if (isset($_GET["message"]) && $_GET["message"] === "cancelled") {
    $message = "Reservation cancelled successfully.";
}

/* Load reservations */
$reservationsStatement = $pdo->prepare(
    "SELECT
        reservations.id,
        reservations.reservation_date,
        reservations.status,
        books.title,
        books.author,
        books.shelf_number,
        books.available_quantity,
        categories.category_name
     FROM reservations
     INNER JOIN books ON reservations.book_id = books.id
     INNER JOIN categories ON books.category_id = categories.id
     WHERE reservations.member_id = ?
     ORDER BY reservations.id DESC"
);

$reservationsStatement->execute([$memberId]);
$reservations = $reservationsStatement->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations | Smart Library</title>

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

        .reservation-card {
            height: 100%;
            overflow: hidden;
            border: none;
            border-radius: 17px;
            background: #ffffff;
            box-shadow: 0 7px 20px rgba(16, 42, 67, 0.07);
            transition: 0.25s ease;
        }

        .reservation-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 30px rgba(16, 42, 67, 0.14);
        }

        .card-icon {
            width: 62px;
            height: 62px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 17px;
            color: #ffffff;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-size: 1.6rem;
        }

        .status-badge {
            padding: 7px 11px;
            border-radius: 50px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .status-pending {
            color: #a16207;
            background: #fef3c7;
        }

        .status-ready {
            color: #166534;
            background: #dcfce7;
        }

        .status-collected {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .status-cancelled {
            color: #b42318;
            background: #fee4e2;
        }

        .cancel-button {
            border-radius: 9px;
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

        <a class="nav-link" href="#">
            <i class="fa-solid fa-book-open"></i> My Borrowed Books
        </a>

        <a class="nav-link" href="#">
            <i class="fa-solid fa-clock-rotate-left"></i> Borrowing History
        </a>

        <a class="nav-link active" href="reservations.php">
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
            <h6 class="mb-0 fw-bold">My Reservation Requests</h6>
        </div>

        <a href="catalog.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-magnifying-glass me-1"></i>
            Browse Books
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">My Reservations</h2>
            <p class="text-muted mb-0">
                Track the status of books you have requested.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (count($reservations) > 0): ?>
                <?php foreach ($reservations as $reservation): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card reservation-card">
                            <div class="card-body p-4">

                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <div class="card-icon">
                                        <i class="fa-solid fa-bookmark"></i>
                                    </div>

                                    <?php if ($reservation["status"] === "pending"): ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php elseif ($reservation["status"] === "ready"): ?>
                                        <span class="status-badge status-ready">Ready for Pickup</span>
                                    <?php elseif ($reservation["status"] === "collected"): ?>
                                        <span class="status-badge status-collected">Collected</span>
                                    <?php else: ?>
                                        <span class="status-badge status-cancelled">Cancelled</span>
                                    <?php endif; ?>
                                </div>

                                <span class="badge text-bg-light mb-2">
                                    <?php echo e($reservation["category_name"]); ?>
                                </span>

                                <h5 class="fw-bold mb-1">
                                    <?php echo e($reservation["title"]); ?>
                                </h5>

                                <p class="text-muted small">
                                    <?php echo e($reservation["author"]); ?>
                                </p>

                                <hr>

                                <p class="small mb-2">
                                    <i class="fa-solid fa-calendar me-2 text-primary"></i>
                                    Requested:
                                    <?php echo date("d M Y", strtotime($reservation["reservation_date"])); ?>
                                </p>

                                <p class="small mb-3">
                                    <i class="fa-solid fa-location-dot me-2 text-primary"></i>
                                    Shelf:
                                    <?php echo e($reservation["shelf_number"] ?: "To be confirmed"); ?>
                                </p>

                                <?php if ($reservation["status"] === "pending"): ?>
                                    <form method="POST">
                                        <input
                                            type="hidden"
                                            name="reservation_id"
                                            value="<?php echo $reservation["id"]; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-outline-danger cancel-button w-100"
                                            onclick="return confirm('Cancel this reservation?');"
                                        >
                                            <i class="fa-solid fa-xmark me-1"></i>
                                            Cancel Reservation
                                        </button>
                                    </form>
                                <?php elseif ($reservation["status"] === "ready"): ?>
                                    <div class="alert alert-success small mb-0">
                                        <i class="fa-solid fa-circle-check me-1"></i>
                                        Please visit the library to collect this book.
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center bg-white rounded-4 shadow-sm p-5">
                        <i class="fa-solid fa-bookmark fs-1 text-primary d-block mb-3"></i>
                        <h4>No Reservation Requests</h4>
                        <p class="text-muted">
                            You have not reserved any unavailable books yet.
                        </p>

                        <a href="catalog.php" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>
                            Browse Books
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>