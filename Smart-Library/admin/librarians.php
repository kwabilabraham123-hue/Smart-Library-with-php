<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin"]);

$message = "";
$messageType = "success";

/* Add or update librarian */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $fullName = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        if (empty($fullName) || empty($email) || empty($password)) {
            $message = "Please complete all fields.";
            $messageType = "danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = "danger";
        } elseif (strlen($password) < 8) {
            $message = "Password must contain at least 8 characters.";
            $messageType = "danger";
        } else {
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $addLibrarian = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password, role, status)
                     VALUES (?, ?, ?, 'librarian', 'active')"
                );

                $addLibrarian->execute([
                    $fullName,
                    $email,
                    $passwordHash
                ]);

                header("Location: librarians.php?message=added");
                exit;

            } catch (PDOException $error) {
                $message = "This email address already exists.";
                $messageType = "danger";
            }
        }
    }

    if ($action === "status") {
        $userId = $_POST["user_id"] ?? "";
        $newStatus = $_POST["new_status"] ?? "";

        if (!in_array($newStatus, ["active", "inactive"])) {
            $message = "Invalid account status.";
            $messageType = "danger";
        } else {
            $statusStatement = $pdo->prepare(
                "UPDATE users
                 SET status = ?
                 WHERE id = ?
                 AND role = 'librarian'"
            );

            $statusStatement->execute([$newStatus, $userId]);

            header("Location: librarians.php?message=status_changed");
            exit;
        }
    }
}

if (isset($_GET["message"])) {
    if ($_GET["message"] === "added") {
        $message = "Librarian account created successfully.";
    }

    if ($_GET["message"] === "status_changed") {
        $message = "Librarian account status updated successfully.";
    }
}

/* Get all librarians */
$librarians = $pdo->query(
    "SELECT id, full_name, email, status, created_at
     FROM users
     WHERE role = 'librarian'
     ORDER BY id DESC"
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarians | Smart Library</title>

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

        .form-card,
        .table-card {
            border: none;
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .form-card-header {
            padding: 20px 24px;
            border-radius: 18px 18px 0 0;
            color: white;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
        }

        .form-control {
            padding: 12px;
            border-radius: 10px;
        }

        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.22rem rgba(109, 63, 199, 0.12);
        }

        .save-button {
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-weight: 700;
        }

        .librarian-avatar {
            width: 42px;
            height: 42px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            color: white;
            background: linear-gradient(135deg, #176b9f, #54a8d9);
            font-weight: 700;
        }

        .active-status {
            padding: 6px 10px;
            border-radius: 50px;
            color: #166534;
            background: #dcfce7;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .inactive-status {
            padding: 6px 10px;
            border-radius: 50px;
            color: #b42318;
            background: #fee4e2;
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

        <a class="nav-link active" href="librarians.php">
            <i class="fa-solid fa-user-tie"></i> Librarians
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
            <small class="text-muted">Smart Library / Admin / Librarians</small>
            <h6 class="mb-0 fw-bold">Librarian Account Management</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">Librarian Accounts</h2>
            <p class="text-muted mb-0">
                Create and manage accounts for library staff.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Add librarian form -->
            <div class="col-lg-4">
                <div class="card form-card">
                    <div class="form-card-header">
                        <h5 class="mb-1">
                            <i class="fa-solid fa-user-plus me-2"></i>
                            Add Librarian
                        </h5>

                        <small class="text-white-50">
                            The librarian will be able to log in immediately.
                        </small>
                    </div>

                    <div class="card-body p-4">
                        <form method="POST">

                            <input type="hidden" name="action" value="add">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Full Name</label>

                                <input
                                    type="text"
                                    class="form-control"
                                    name="full_name"
                                    placeholder="Enter librarian full name"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email Address</label>

                                <input
                                    type="email"
                                    class="form-control"
                                    name="email"
                                    placeholder="librarian@email.com"
                                    required
                                >
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Password</label>

                                <input
                                    type="password"
                                    class="form-control"
                                    name="password"
                                    placeholder="At least 8 characters"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-primary save-button w-100">
                                <i class="fa-solid fa-user-plus me-2"></i>
                                Create Librarian Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Librarian list -->
            <div class="col-lg-8">
                <div class="card table-card">
                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">
                                All Librarians
                                <span class="badge text-bg-light"><?php echo count($librarians); ?></span>
                            </h5>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Librarian</th>
                                        <th>Email Address</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (count($librarians) > 0): ?>
                                        <?php foreach ($librarians as $librarian): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="librarian-avatar">
                                                            <?php echo e(strtoupper(substr($librarian["full_name"], 0, 1))); ?>
                                                        </span>

                                                        <strong>
                                                            <?php echo e($librarian["full_name"]); ?>
                                                        </strong>
                                                    </div>
                                                </td>

                                                <td><?php echo e($librarian["email"]); ?></td>

                                                <td>
                                                    <?php echo date("d M Y", strtotime($librarian["created_at"])); ?>
                                                </td>

                                                <td>
                                                    <?php if ($librarian["status"] === "active"): ?>
                                                        <span class="active-status">Active</span>
                                                    <?php else: ?>
                                                        <span class="inactive-status">Inactive</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="text-end">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="status">
                                                        <input type="hidden" name="user_id" value="<?php echo $librarian["id"]; ?>">

                                                        <?php if ($librarian["status"] === "active"): ?>
                                                            <input type="hidden" name="new_status" value="inactive">

                                                            <button
                                                                type="submit"
                                                                class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Deactivate this librarian account?');"
                                                            >
                                                                Deactivate
                                                            </button>
                                                        <?php else: ?>
                                                            <input type="hidden" name="new_status" value="active">

                                                            <button
                                                                type="submit"
                                                                class="btn btn-sm btn-outline-success"
                                                            >
                                                                Activate
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
                                                <i class="fa-solid fa-user-tie fs-3 d-block mb-2"></i>
                                                No librarian accounts found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>