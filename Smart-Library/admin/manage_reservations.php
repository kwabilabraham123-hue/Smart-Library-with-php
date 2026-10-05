<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin", "librarian"]);

$message = "";
$messageType = "success";

/* Update reservation status */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $reservationId = $_POST["reservation_id"] ?? "";
    $action = $_POST["action"] ?? "";

    try {
        $pdo->beginTransaction();

        $reservationStatement = $pdo->prepare(
            "SELECT
                reservations.id,
                reservations.book_id,
                reservations.status,
                books.title,
                books.available_quantity,
                books.quantity
             FROM reservations
             INNER JOIN books ON reservations.book_id = books.id
             WHERE reservations.id = ?
             FOR UPDATE"
        );

        $reservationStatement->execute([$reservationId]);
        $reservation = $reservationStatement->fetch();

        if (!$reservation) {
            throw new Exception("Reservation record was not found.");
        }

        /* Mark reservation as ready */
        if ($action === "ready") {
            if ($reservation["status"] !== "pending") {
                throw new Exception("Only pending reservations can be marked ready.");
            }

            if ($reservation["available_quantity"] < 1) {
                throw new Exception("No copy is currently available to set aside for this reservation.");
            }

            $readyStatement = $pdo->prepare(
                "UPDATE reservations
                 SET status = 'ready'
                 WHERE id = ?"
            );

            $readyStatement->execute([$reservationId]);

            /* Keep one copy aside for this member */
            $stockStatement = $pdo->prepare(
                "UPDATE books
                 SET available_quantity = available_quantity - 1
                 WHERE id = ?"
            );

            $stockStatement->execute([$reservation["book_id"]]);

            $pdo->commit();

            header("Location: manage_reservations.php?message=ready");
            exit;
        }

        /* Cancel reservation */
        if ($action === "cancel") {
            if ($reservation["status"] === "cancelled") {
                throw new Exception("This reservation has already been cancelled.");
            }

            /* Return reserved stock if book was already marked ready */
            if ($reservation["status"] === "ready") {
                $returnStock = $pdo->prepare(
                    "UPDATE books
                     SET available_quantity =
                        CASE
                            WHEN available_quantity < quantity
                            THEN available_quantity + 1
                            ELSE quantity
                        END
                     WHERE id = ?"
                );

                $returnStock->execute([$reservation["book_id"]]);
            }

            $cancelStatement = $pdo->prepare(
                "UPDATE reservations
                 SET status = 'cancelled'
                 WHERE id = ?"
            );

            $cancelStatement->execute([$reservationId]);

            $pdo->commit();

            header("Location: manage_reservations.php?message=cancelled");
            exit;
        }

        throw new Exception("Invalid reservation action.");

    } catch (Exception $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $message = $error->getMessage();
        $messageType = "danger";
    }
}

if (isset($_GET["message"])) {
    if ($_GET["message"] === "ready") {
        $message = "Reservation marked ready. One copy has been set aside for the member.";
    }

    if ($_GET["message"] === "cancelled") {
        $message = "Reservation cancelled successfully.";
    }
}

/* Filter reservations by status */
$statusFilter = $_GET["status"] ?? "";

$reservationsStatement = $pdo->prepare(
    "SELECT
        reservations.id,
        reservations.status,
        reservations.reservation_date,
        books.title AS book_title,
        books.author,
        books.available_quantity,
        members.member_code,
        members.phone,
        users.full_name AS member_name,
        users.email AS member_email
     FROM reservations
     INNER JOIN books ON reservations.book_id = books.id
     INNER JOIN members ON reservations.member_id = members.id
     INNER JOIN users ON members.user_id = users.id
     WHERE (? = '' OR reservations.status = ?)
     ORDER BY
        CASE reservations.status
            WHEN 'pending' THEN 1
            WHEN 'ready' THEN 2
            WHEN 'collected' THEN 3
            WHEN 'cancelled' THEN 4
        END,
        reservations.reservation_date DESC"
);

$reservationsStatement->execute([$statusFilter, $statusFilter]);
$reservations = $reservationsStatement->fetchAll();

/* Summary cards */
$pendingCount = $pdo->query(
    "SELECT COUNT(*) FROM reservations WHERE status = 'pending'"
)->fetchColumn();

$readyCount = $pdo->query(
    "SELECT COUNT(*) FROM reservations WHERE status = 'ready'"
)->fetchColumn();

$totalReservations = $pdo->query(
    "SELECT COUNT(*) FROM reservations"
)->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #102a43;
            --blue: #176b9f;
            --purple: #6d3fc7;
            --pink: #e84393;
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

        .summary-card {
            height: 100%;
            padding: 22px;
            border: none;
            border-radius: 16px;
            background: white;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            color: white;
            font-size: 1.2rem;
        }

        .icon-blue { background: linear-gradient(135deg, #176b9f, #54a8d9); }
        .icon-orange { background: linear-gradient(135deg, #f39c12, #f7c65c); }
        .icon-green { background: linear-gradient(135deg, #159957, #38ef7d); }

        .summary-number {
            margin: 12px 0 2px;
            color: var(--navy);
            font-size: 1.85rem;
            font-weight: 800;
        }

        .table-card {
            border: none;
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .status-badge {
            padding: 6px 10px;
            border-radius: 50px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .pending { color: #a16207; background: #fef3c7; }
        .ready { color: #166534; background: #dcfce7; }
        .collected { color: #1d4ed8; background: #dbeafe; }
        .cancelled { color: #b42318; background: #fee4e2; }

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

        <a class="nav-link" href="return_book.php">
            <i class="fa-solid fa-rotate-left"></i> Return Book
        </a>

        <a class="nav-link active" href="manage_reservations.php">
            <i class="fa-solid fa-bookmark"></i> Reservations
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
            <small class="text-muted">Smart Library / Reservations</small>
            <h6 class="mb-0 fw-bold">Reservation Management</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">Book Reservation Requests</h2>
            <p class="text-muted mb-0">
                Prepare books for members when they become available.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">

            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon icon-blue">
                        <i class="fa-solid fa-bookmark"></i>
                    </div>

                    <div class="summary-number"><?php echo $totalReservations; ?></div>
                    <div class="text-muted">Total Reservations</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon icon-orange">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>

                    <div class="summary-number"><?php echo $pendingCount; ?></div>
                    <div class="text-muted">Pending Requests</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon icon-green">
                        <i class="fa-solid fa-check"></i>
                    </div>

                    <div class="summary-number"><?php echo $readyCount; ?></div>
                    <div class="text-muted">Ready for Pickup</div>
                </div>
            </div>

        </div>

        <div class="card table-card">
            <div class="card-body p-4">

                <form method="GET" class="row g-2 mb-4">
                    <div class="col-md-10">
                        <select class="form-select" name="status">
                            <option value="">All Reservation Statuses</option>
                            <option value="pending" <?php echo $statusFilter === "pending" ? "selected" : ""; ?>>
                                Pending
                            </option>
                            <option value="ready" <?php echo $statusFilter === "ready" ? "selected" : ""; ?>>
                                Ready for Pickup
                            </option>
                            <option value="collected" <?php echo $statusFilter === "collected" ? "selected" : ""; ?>>
                                Collected
                            </option>
                            <option value="cancelled" <?php echo $statusFilter === "cancelled" ? "selected" : ""; ?>>
                                Cancelled
                            </option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            Filter
                        </button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Member</th>
                                <th>Book</th>
                                <th>Requested</th>
                                <th>Book Stock</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (count($reservations) > 0): ?>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($reservation["member_name"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($reservation["member_code"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <strong class="d-block">
                                                <?php echo e($reservation["book_title"]); ?>
                                            </strong>

                                            <small class="text-muted">
                                                <?php echo e($reservation["author"]); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?php echo date("d M Y", strtotime($reservation["reservation_date"])); ?>
                                        </td>

                                        <td>
                                            <?php echo $reservation["available_quantity"]; ?> available
                                        </td>

                                        <td>
                                            <span class="status-badge <?php echo e($reservation["status"]); ?>">
                                                <?php echo e(ucfirst($reservation["status"])); ?>
                                            </span>
                                        </td>

                                        <td class="text-end">
                                            <?php if ($reservation["status"] === "pending"): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation["id"]; ?>">
                                                    <input type="hidden" name="action" value="ready">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-success"
                                                        onclick="return confirm('Mark this reservation ready for pickup?');"
                                                    >
                                                        <i class="fa-solid fa-check me-1"></i>
                                                        Mark Ready
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($reservation["status"] === "pending" || $reservation["status"] === "ready"): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation["id"]; ?>">
                                                    <input type="hidden" name="action" value="cancel">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Cancel this reservation?');"
                                                    >
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-bookmark fs-3 d-block mb-2"></i>
                                        No reservations found.
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