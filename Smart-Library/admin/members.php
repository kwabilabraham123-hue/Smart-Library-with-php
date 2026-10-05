<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin"]);

$message = "";
$messageType = "success";

$editMember = [
    "member_id" => "",
    "user_id" => "",
    "full_name" => "",
    "email" => "",
    "phone" => "",
    "address" => "",
    "department_class" => "",
    "status" => "active"
];

/* Add, update, or delete a member */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add") {
        $fullName = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $department = trim($_POST["department_class"] ?? "");
        $password = $_POST["password"] ?? "Member@123";

        if (empty($fullName) || empty($email) || empty($phone) || empty($department)) {
            $message = "Full name, email, phone number, and department/class are required.";
            $messageType = "danger";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Enter a valid email address.";
            $messageType = "danger";
        } else {
            try {
                $pdo->beginTransaction();

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $userStatement = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password, role, status)
                     VALUES (?, ?, ?, 'member', 'active')"
                );

                $userStatement->execute([
                    $fullName,
                    $email,
                    $passwordHash
                ]);

                $userId = $pdo->lastInsertId();
                $memberCode = "MEM-" . str_pad($userId, 4, "0", STR_PAD_LEFT);

                $memberStatement = $pdo->prepare(
                    "INSERT INTO members
                     (user_id, member_code, phone, address, department_class)
                     VALUES (?, ?, ?, ?, ?)"
                );

                $memberStatement->execute([
                    $userId,
                    $memberCode,
                    $phone,
                    $address ?: null,
                    $department
                ]);

                $pdo->commit();

                header("Location: members.php?message=added&member_code=" . urlencode($memberCode));
                exit;

            } catch (Exception $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $message = "Member could not be added. The email may already exist.";
                $messageType = "danger";
            }
        }
    }

    if ($action === "update") {
        $memberId = $_POST["member_id"] ?? "";
        $userId = $_POST["user_id"] ?? "";
        $fullName = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $department = trim($_POST["department_class"] ?? "");
        $status = $_POST["status"] ?? "active";

        if (empty($fullName) || empty($email) || empty($phone) || empty($department)) {
            $message = "Please complete all required fields.";
            $messageType = "danger";
        } else {
            try {
                $pdo->beginTransaction();

                $userUpdate = $pdo->prepare(
                    "UPDATE users
                     SET full_name = ?, email = ?, status = ?
                     WHERE id = ?"
                );

                $userUpdate->execute([
                    $fullName,
                    $email,
                    $status,
                    $userId
                ]);

                $memberUpdate = $pdo->prepare(
                    "UPDATE members
                     SET phone = ?, address = ?, department_class = ?
                     WHERE id = ?"
                );

                $memberUpdate->execute([
                    $phone,
                    $address ?: null,
                    $department,
                    $memberId
                ]);

                $pdo->commit();

                header("Location: members.php?message=updated");
                exit;

            } catch (Exception $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $message = "Member could not be updated. The email may already exist.";
                $messageType = "danger";
            }
        }
    }

    if ($action === "delete") {
        $memberId = $_POST["member_id"] ?? "";
        $userId = $_POST["user_id"] ?? "";

        $loanCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM borrowings WHERE member_id = ?"
        );

        $loanCheck->execute([$memberId]);

        $reservationCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM reservations WHERE member_id = ?"
        );

        $reservationCheck->execute([$memberId]);

        if ($loanCheck->fetchColumn() > 0 || $reservationCheck->fetchColumn() > 0) {
            $message = "This member cannot be deleted because they have borrowing or reservation records.";
            $messageType = "danger";
        } else {
            $deleteUser = $pdo->prepare(
                "DELETE FROM users WHERE id = ?"
            );

            $deleteUser->execute([$userId]);

            header("Location: members.php?message=deleted");
            exit;
        }
    }
}

/* Messages after redirect */
if (isset($_GET["message"])) {
    if ($_GET["message"] === "added") {
        $memberCode = $_GET["member_code"] ?? "";
        $message = "Member added successfully. Member ID: " . $memberCode . ".";
    }

    if ($_GET["message"] === "updated") {
        $message = "Member updated successfully.";
    }

    if ($_GET["message"] === "deleted") {
        $message = "Member deleted successfully.";
    }
}

/* Load a member for editing */
if (isset($_GET["edit"])) {
    $editStatement = $pdo->prepare(
        "SELECT
            members.id AS member_id,
            members.user_id,
            members.member_code,
            members.phone,
            members.address,
            members.department_class,
            users.full_name,
            users.email,
            users.status
         FROM members
         INNER JOIN users ON members.user_id = users.id
         WHERE members.id = ?"
    );

    $editStatement->execute([$_GET["edit"]]);
    $foundMember = $editStatement->fetch();

    if ($foundMember) {
        $editMember = $foundMember;
    }
}

/* Search member list */
$search = trim($_GET["search"] ?? "");
$searchValue = "%" . $search . "%";

$membersStatement = $pdo->prepare(
    "SELECT
        members.id AS member_id,
        members.user_id,
        members.member_code,
        members.phone,
        members.address,
        members.department_class,
        members.date_registered,
        users.full_name,
        users.email,
        users.status,
        (
            SELECT COUNT(*)
            FROM borrowings
            WHERE borrowings.member_id = members.id
            AND borrowings.status = 'borrowed'
        ) AS active_loans
     FROM members
     INNER JOIN users ON members.user_id = users.id
     WHERE
        users.full_name LIKE ?
        OR users.email LIKE ?
        OR members.member_code LIKE ?
        OR members.phone LIKE ?
     ORDER BY members.id DESC"
);

$membersStatement->execute([
    $searchValue,
    $searchValue,
    $searchValue,
    $searchValue
]);

$members = $membersStatement->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members | Smart Library</title>

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

        .form-control,
        .form-select {
            padding: 11px 13px;
            border-radius: 10px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.22rem rgba(109, 63, 199, 0.12);
        }

        .save-button {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-weight: 700;
        }

        .member-avatar {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-weight: 700;
        }

        .status-active {
            padding: 6px 10px;
            border-radius: 50px;
            color: #166534;
            background: #dcfce7;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .status-inactive {
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

    <p class="sidebar-label">MAIN MENU</p>

    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard.php">
            <i class="fa-solid fa-chart-pie"></i> Dashboard
        </a>

        <a class="nav-link" href="books.php">
            <i class="fa-solid fa-book"></i> Books
        </a>

        <a class="nav-link" href="categories.php">
            <i class="fa-solid fa-layer-group"></i> Categories
        </a>

        <a class="nav-link active" href="members.php">
            <i class="fa-solid fa-users"></i> Members
        </a>

        <a class="nav-link" href="issue_book.php">
            <i class="fa-solid fa-hand-holding"></i> Issue Book
        </a>

        <a class="nav-link" href="return_book.php">
            <i class="fa-solid fa-rotate-left"></i> Return Book
        </a>

        <a class="nav-link" href="manage_reservations.php">
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
            <small class="text-muted">Smart Library / Admin / Members</small>
            <h6 class="mb-0 fw-bold">Member Management</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">Library Members</h2>
            <p class="text-muted mb-0">
                Register, update, activate, and manage library member accounts.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Add/Edit member -->
            <div class="col-lg-4">
                <div class="card form-card">
                    <div class="form-card-header">
                        <h5 class="mb-1">
                            <i class="fa-solid fa-user-plus me-2"></i>
                            <?php echo !empty($editMember["member_id"]) ? "Edit Member" : "Add Member"; ?>
                        </h5>

                        <small class="text-white-50">
                            Member accounts can log in to the Member Portal.
                        </small>
                    </div>

                    <div class="card-body p-4">
                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="<?php echo !empty($editMember["member_id"]) ? "update" : "add"; ?>"
                            >

                            <?php if (!empty($editMember["member_id"])): ?>
                                <input type="hidden" name="member_id" value="<?php echo e($editMember["member_id"]); ?>">
                                <input type="hidden" name="user_id" value="<?php echo e($editMember["user_id"]); ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Full Name</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="full_name"
                                    value="<?php echo e($editMember["full_name"]); ?>"
                                    placeholder="Enter full name"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input
                                    type="email"
                                    class="form-control"
                                    name="email"
                                    value="<?php echo e($editMember["email"]); ?>"
                                    placeholder="member@email.com"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Phone Number</label>
                                <input
                                    type="tel"
                                    class="form-control"
                                    name="phone"
                                    value="<?php echo e($editMember["phone"]); ?>"
                                    placeholder="0240000000"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Department / Class</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="department_class"
                                    value="<?php echo e($editMember["department_class"]); ?>"
                                    placeholder="Example: Information Technology"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Address</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    name="address"
                                    value="<?php echo e($editMember["address"]); ?>"
                                    placeholder="Example: Accra"
                                >
                            </div>

                            <?php if (empty($editMember["member_id"])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Temporary Password
                                    </label>
                                    <input
                                        type="password"
                                        class="form-control"
                                        name="password"
                                        value="Member@123"
                                        required
                                    >
                                    <small class="text-muted">
                                        The member can change it later.
                                    </small>
                                </div>
                            <?php else: ?>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Account Status</label>

                                    <select class="form-select" name="status">
                                        <option
                                            value="active"
                                            <?php echo $editMember["status"] === "active" ? "selected" : ""; ?>
                                        >
                                            Active
                                        </option>

                                        <option
                                            value="inactive"
                                            <?php echo $editMember["status"] === "inactive" ? "selected" : ""; ?>
                                        >
                                            Inactive
                                        </option>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-primary save-button w-100">
                                <i class="fa-solid fa-floppy-disk me-2"></i>
                                <?php echo !empty($editMember["member_id"]) ? "Update Member" : "Add Member"; ?>
                            </button>

                            <?php if (!empty($editMember["member_id"])): ?>
                                <a href="members.php" class="btn btn-light border w-100 mt-2">
                                    Cancel Edit
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Member table -->
            <div class="col-lg-8">
                <div class="card table-card">
                    <div class="card-body p-4">

                        <form method="GET" class="mb-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fa-solid fa-magnifying-glass text-primary"></i>
                                </span>

                                <input
                                    type="text"
                                    class="form-control"
                                    name="search"
                                    value="<?php echo e($search); ?>"
                                    placeholder="Search by name, member ID, email, or phone..."
                                >

                                <button type="submit" class="btn btn-primary">
                                    Search
                                </button>
                            </div>
                        </form>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">
                                All Members
                                <span class="badge text-bg-light"><?php echo count($members); ?></span>
                            </h5>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member</th>
                                        <th>Contact</th>
                                        <th>Department</th>
                                        <th>Loans</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (count($members) > 0): ?>
                                        <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="member-avatar">
                                                            <?php echo e(strtoupper(substr($member["full_name"], 0, 1))); ?>
                                                        </span>

                                                        <div>
                                                            <strong class="d-block">
                                                                <?php echo e($member["full_name"]); ?>
                                                            </strong>

                                                            <small class="text-muted">
                                                                <?php echo e($member["member_code"]); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>

                                                <td>
                                                    <small class="d-block">
                                                        <?php echo e($member["email"]); ?>
                                                    </small>

                                                    <small class="text-muted">
                                                        <?php echo e($member["phone"]); ?>
                                                    </small>
                                                </td>

                                                <td><?php echo e($member["department_class"]); ?></td>

                                                <td>
                                                    <span class="badge text-bg-light">
                                                        <?php echo $member["active_loans"]; ?> active
                                                    </span>
                                                </td>

                                                <td>
                                                    <?php if ($member["status"] === "active"): ?>
                                                        <span class="status-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="status-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="text-end">
                                                    <a
                                                        href="members.php?edit=<?php echo $member["member_id"]; ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Edit member"
                                                    >
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>

                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="member_id" value="<?php echo $member["member_id"]; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $member["user_id"]; ?>">

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Delete member"
                                                            onclick="return confirm('Delete this member account?');"
                                                        >
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="fa-solid fa-users fs-3 d-block mb-2"></i>
                                                No members found.
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