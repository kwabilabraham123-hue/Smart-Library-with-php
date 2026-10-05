<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin", "librarian"]);

$borrowingId = $_GET["id"] ?? "";

if (empty($borrowingId)) {
    die("No transaction selected.");
}

$receiptStatement = $pdo->prepare(
    "SELECT
        borrowings.id,
        borrowings.issue_date,
        borrowings.due_date,
        borrowings.return_date,
        borrowings.fine_amount,
        borrowings.fine_status,
        borrowings.status,

        books.title AS book_title,
        books.author,
        books.isbn,
        books.shelf_number,

        member_user.full_name AS member_name,
        member_user.email AS member_email,
        members.member_code,
        members.phone,
        members.department_class,

        librarian.full_name AS librarian_name

     FROM borrowings

     INNER JOIN books
        ON borrowings.book_id = books.id

     INNER JOIN members
        ON borrowings.member_id = members.id

     INNER JOIN users AS member_user
        ON members.user_id = member_user.id

     INNER JOIN users AS librarian
        ON borrowings.issued_by = librarian.id

     WHERE borrowings.id = ?
     LIMIT 1"
);

$receiptStatement->execute([$borrowingId]);
$receipt = $receiptStatement->fetch();

if (!$receipt) {
    die("Transaction record not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $receipt["id"]; ?> | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fb;
            color: #243447;
            font-family: Arial, Helvetica, sans-serif;
        }

        .receipt-wrapper {
            max-width: 850px;
            margin: 45px auto;
        }

        .receipt-card {
            overflow: hidden;
            border: none;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 12px 35px rgba(16, 42, 67, 0.13);
        }

        .receipt-header {
            padding: 35px;
            color: #ffffff;
            background:
                radial-gradient(circle at 85% 20%, rgba(244, 185, 66, 0.25), transparent 25%),
                linear-gradient(135deg, #102a43, #176b9f, #6d3fc7);
        }

        .logo-icon {
            width: 52px;
            height: 52px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 1.5rem;
        }

        .receipt-number {
            padding: 8px 13px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 0.85rem;
            font-weight: 700;
        }

        .section-title {
            margin-bottom: 16px;
            color: #102a43;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .info-box {
            height: 100%;
            padding: 18px;
            border-radius: 13px;
            background: #f8fafc;
        }

        .info-label {
            display: block;
            margin-bottom: 4px;
            color: #6b7280;
            font-size: 0.78rem;
        }

        .timeline-box {
            padding: 18px;
            border-radius: 13px;
            color: #ffffff;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
        }

        .status-badge {
            padding: 7px 12px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .status-borrowed {
            color: #a16207;
            background: #fef3c7;
        }

        .status-returned {
            color: #166534;
            background: #dcfce7;
        }

        .print-button {
            border-radius: 10px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-weight: 700;
        }

        @media print {
            body {
                background: #ffffff;
            }

            .no-print {
                display: none !important;
            }

            .receipt-wrapper {
                margin: 0;
                max-width: 100%;
            }

            .receipt-card {
                box-shadow: none;
            }
        }
    </style>
</head>

<body>

<div class="receipt-wrapper">

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="dashboard.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>

        <button onclick="window.print()" class="btn btn-primary print-button">
            <i class="fa-solid fa-print me-1"></i>
            Print Receipt
        </button>
    </div>

    <div class="receipt-card">

        <div class="receipt-header">
            <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <span class="logo-icon">
                        <i class="fa-solid fa-book-open-reader"></i>
                    </span>

                    <div>
                        <h3 class="fw-bold mb-1">Smart Library</h3>
                        <p class="mb-0 text-white-50">
                            Borrowing and Return Transaction Receipt
                        </p>
                    </div>
                </div>

                <span class="receipt-number">
                    Receipt #<?php echo str_pad($receipt["id"], 5, "0", STR_PAD_LEFT); ?>
                </span>
            </div>
        </div>

        <div class="card-body p-4 p-md-5">

            <div class="d-flex justify-content-between align-items-center border-bottom pb-4 mb-4">
                <div>
                    <small class="text-muted d-block">Transaction Status</small>

                    <?php if ($receipt["status"] === "borrowed"): ?>
                        <span class="status-badge status-borrowed">
                            <i class="fa-solid fa-book-open me-1"></i>
                            Book Currently Borrowed
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-returned">
                            <i class="fa-solid fa-circle-check me-1"></i>
                            Book Returned
                        </span>
                    <?php endif; ?>
                </div>

                <div class="text-end">
                    <small class="text-muted d-block">Issued By</small>
                    <strong><?php echo e($receipt["librarian_name"]); ?></strong>
                </div>
            </div>

            <div class="row g-4 mb-4">

                <div class="col-md-6">
                    <h5 class="section-title">
                        <i class="fa-solid fa-user me-2 text-primary"></i>
                        Member Information
                    </h5>

                    <div class="info-box">
                        <strong class="d-block mb-2">
                            <?php echo e($receipt["member_name"]); ?>
                        </strong>

                        <span class="info-label">Member ID</span>
                        <p><?php echo e($receipt["member_code"]); ?></p>

                        <span class="info-label">Email Address</span>
                        <p><?php echo e($receipt["member_email"]); ?></p>

                        <span class="info-label">Phone Number</span>
                        <p class="mb-0"><?php echo e($receipt["phone"]); ?></p>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="section-title">
                        <i class="fa-solid fa-book me-2 text-primary"></i>
                        Book Information
                    </h5>

                    <div class="info-box">
                        <strong class="d-block mb-2">
                            <?php echo e($receipt["book_title"]); ?>
                        </strong>

                        <span class="info-label">Author</span>
                        <p><?php echo e($receipt["author"]); ?></p>

                        <span class="info-label">ISBN</span>
                        <p><?php echo e($receipt["isbn"] ?: "Not available"); ?></p>

                        <span class="info-label">Shelf Number</span>
                        <p class="mb-0"><?php echo e($receipt["shelf_number"] ?: "Not specified"); ?></p>
                    </div>
                </div>

            </div>

            <h5 class="section-title">
                <i class="fa-solid fa-calendar-days me-2 text-primary"></i>
                Transaction Dates
            </h5>

            <div class="timeline-box mb-4">
                <div class="row text-center g-3">

                    <div class="col-md-4">
                        <small class="text-white-50 d-block">Issue Date</small>
                        <strong>
                            <?php echo date("d M Y", strtotime($receipt["issue_date"])); ?>
                        </strong>
                    </div>

                    <div class="col-md-4">
                        <small class="text-white-50 d-block">Due Date</small>
                        <strong>
                            <?php echo date("d M Y", strtotime($receipt["due_date"])); ?>
                        </strong>
                    </div>

                    <div class="col-md-4">
                        <small class="text-white-50 d-block">Return Date</small>
                        <strong>
                            <?php
                            echo $receipt["return_date"]
                                ? date("d M Y", strtotime($receipt["return_date"]))
                                : "Not Returned";
                            ?>
                        </strong>
                    </div>

                </div>
            </div>

            <?php if ($receipt["status"] === "returned"): ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-label">Overdue Fine</span>
                            <strong class="fs-5">
                                GHS <?php echo number_format($receipt["fine_amount"], 2); ?>
                            </strong>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-label">Fine Payment Status</span>
                            <strong class="fs-5">
                                <?php echo e(ucfirst($receipt["fine_status"])); ?>
                            </strong>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <hr class="my-4">

            <p class="text-center text-muted small mb-0">
                Thank you for using Smart Library Management System.
                Please keep this receipt for your records.
            </p>

        </div>
    </div>
</div>

</body>
</html>