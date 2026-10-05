<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin", "librarian"]);

$message = "";
$messageType = "success";

/* Record fine payment */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $borrowingId = $_POST["borrowing_id"] ?? "";
    $amountPaid = (float) ($_POST["amount_paid"] ?? 0);
    $paymentMethod = $_POST["payment_method"] ?? "";
    $paymentReference = trim($_POST["payment_reference"] ?? "");

    if (empty($borrowingId) || $amountPaid <= 0 || empty($paymentMethod)) {
        $message = "Please enter a valid payment amount and payment method.";
        $messageType = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            $fineStatement = $pdo->prepare(
                "SELECT id, fine_amount, fine_status, status
                 FROM borrowings
                 WHERE id = ?
                 FOR UPDATE"
            );

            $fineStatement->execute([$borrowingId]);
            $fine = $fineStatement->fetch();

            if (!$fine || $fine["status"] !== "returned") {
                throw new Exception("Fine record was not found.");
            }

            if ($fine["fine_amount"] <= 0) {
                throw new Exception("This borrowing record has no fine.");
            }

            $paidStatement = $pdo->prepare(
                "SELECT COALESCE(SUM(amount_paid), 0)
                 FROM fine_payments
                 WHERE borrowing_id = ?"
            );

            $paidStatement->execute([$borrowingId]);
            $previouslyPaid = (float) $paidStatement->fetchColumn();

            $outstandingBalance = (float) $fine["fine_amount"] - $previouslyPaid;

            if ($amountPaid > $outstandingBalance) {
                throw new Exception(
                    "Payment cannot exceed the outstanding balance of GHS "
                    . number_format($outstandingBalance, 2)
                    . "."
                );
            }

            $paymentStatement = $pdo->prepare(
                "INSERT INTO fine_payments
                (borrowing_id, amount_paid, payment_method, payment_reference, received_by)
                VALUES (?, ?, ?, ?, ?)"
            );

            $paymentStatement->execute([
                $borrowingId,
                $amountPaid,
                $paymentMethod,
                $paymentReference ?: null,
                $_SESSION["user_id"]
            ]);

            $newTotalPaid = $previouslyPaid + $amountPaid;

            if ($newTotalPaid >= (float) $fine["fine_amount"]) {
                $newFineStatus = "paid";
            } else {
                $newFineStatus = "partial";
            }

            $updateFineStatus = $pdo->prepare(
                "UPDATE borrowings
                 SET fine_status = ?
                 WHERE id = ?"
            );

            $updateFineStatus->execute([
                $newFineStatus,
                $borrowingId
            ]);

            $pdo->commit();

            header("Location: fines.php?message=payment_added");
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

if (isset($_GET["message"]) && $_GET["message"] === "payment_added") {
    $message = "Fine payment recorded successfully.";
}

/* Fine records */
$finesStatement = $pdo->query(
    "SELECT
        borrowings.id,
        borrowings.issue_date,
        borrowings.return_date,
        borrowings.fine_amount,
        borrowings.fine_status,
        books.title AS book_title,
        books.author,
        users.full_name AS member_name,
        members.member_code,
        COALESCE(SUM(fine_payments.amount_paid), 0) AS total_paid
     FROM borrowings
     INNER JOIN books ON borrowings.book_id = books.id
     INNER JOIN members ON borrowings.member_id = members.id
     INNER JOIN users ON members.user_id = users.id
     LEFT JOIN fine_payments ON borrowings.id = fine_payments.borrowing_id
     WHERE borrowings.status = 'returned'
     AND borrowings.fine_amount > 0
     GROUP BY borrowings.id
     ORDER BY borrowings.return_date DESC"
);

$fines = $finesStatement->fetchAll();

$totalFines = 0;
$totalPaid = 0;
$totalOutstanding = 0;

foreach ($fines as $fine) {
    $totalFines += $fine["fine_amount"];
    $totalPaid += $fine["total_paid"];
    $totalOutstanding += $fine["fine_amount"] - $fine["total_paid"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fine Payments | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #102a43;
            --blue: #176b9f;
            --purple: #6d3fc7;
            --green: #159957;
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

        .orange { background: linear-gradient(135deg, #f39c12, #f7c65c); }
        .green { background: linear-gradient(135deg, #159957, #38ef7d); }
        .red { background: linear-gradient(135deg, #dc3545, #ff7a86); }

        .summary-number {
            margin: 12px 0 2px;
            color: var(--navy);
            font-size: 1.8rem;
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
            font-size: 0.75rem;
            font-weight: 700;
        }

        .unpaid { color: #b42318; background: #fee4e2; }
        .partial { color: #a16207; background: #fef3c7; }
        .paid { color: #166534; background: #dcfce7; }
        .waived { color: #1d4ed8; background: #dbeafe; }

        .payment-button {
            border: none;
            border-radius: 9px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-weight: 700;
        }

        .modal-header {
            color: white;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
        }

        .form-control,
        .form-select {
            padding: 11px 13px;
            border-radius: 10px;
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

        <a class="nav-link" href="issue_book.php">
            <i class="fa-solid fa-hand-holding"></i> Issue Book
        </a>

        <a class="nav-link" href="return_book.php">
            <i class="fa-solid fa-rotate-left"></i> Return Book
        </a>

        <a class="nav-link" href="overdue_books.php">
            <i class="fa-solid fa-clock"></i> Overdue Books
        </a>

        <a class="nav-link active" href="fines.php">
            <i class="fa-solid fa-money-bill-wave"></i> Fine Payments
        </a>

        <a class="nav-link" href="reports.php">
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
            <small class="text-muted">Smart Library / Fine Payments</small>
            <h6 class="mb-0 fw-bold">Fine Payment Management</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">Fine Payments</h2>
            <p class="text-muted mb-0">
                Record fine payments from members and track outstanding balances.
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
                    <div class="summary-icon orange">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>

                    <div class="summary-number">
                        GHS <?php echo number_format($totalFines, 2); ?>
                    </div>

                    <div class="text-muted">Total Fines</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div class="summary-number">
                        GHS <?php echo number_format($totalPaid, 2); ?>
                    </div>

                    <div class="text-muted">Total Payments Received</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon red">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div class="summary-number">
                        GHS <?php echo number_format($totalOutstanding, 2); ?>
                    </div>