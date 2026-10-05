<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["member"]);

$message = "";
$messageType = "success";

/* Get member information */
$profileStatement = $pdo->prepare(
    "SELECT
        members.id AS member_id,
        members.phone,
        members.address,
        members.department_class,
        members.member_code,
        users.id AS user_id,
        users.full_name,
        users.email,
        users.password
     FROM members
     INNER JOIN users ON members.user_id = users.id
     WHERE users.id = ?
     LIMIT 1"
);

$profileStatement->execute([$_SESSION["user_id"]]);
$profile = $profileStatement->fetch();

if (!$profile) {
    die("Member profile not found.");
}

/* Update details or password */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "update_profile") {
        $phone = trim($_POST["phone"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $department = trim($_POST["department_class"] ?? "");

        if (empty($phone) || empty($department)) {
            $message = "Phone number and department/class are required.";
            $messageType = "danger";
        } else {
            $updateProfile = $pdo->prepare(
                "UPDATE members
                 SET phone = ?, address = ?, department_class = ?
                 WHERE id = ?"
            );

            $updateProfile->execute([
                $phone,
                $address ?: null,
                $department,
                $profile["member_id"]
            ]);

            header("Location: profile.php?message=profile_updated");
            exit;
        }
    }

    if ($action === "change_password") {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = "Please complete all password fields.";
            $messageType = "danger";
        } elseif (!password_verify($currentPassword, $profile["password"])) {
            $message = "Your current password is incorrect.";
            $messageType = "danger";
        } elseif (strlen($newPassword) < 8) {
            $message = "Your new password must contain at least 8 characters.";
            $messageType = "danger";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "New passwords do not match.";
            $messageType = "danger";
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $passwordUpdate = $pdo->prepare(
                "UPDATE users
                 SET password = ?
                 WHERE id = ?"
            );

            $passwordUpdate->execute([
                $passwordHash,
                $profile["user_id"]
            ]);

            header("Location: profile.php?message=password_updated");
            exit;
        }
    }
}

if (isset($_GET["message"])) {
    if ($_GET["message"] === "profile_updated") {
        $message = "Profile updated successfully.";
    }

    if ($_GET["message"] === "password_updated") {
        $message = "Password changed successfully.";
    }
}

/* Reload profile after updates */
$profileStatement->execute([$_SESSION["user_id"]]);
$profile = $profileStatement->fetch();

$initial = strtoupper(substr($profile["full_name"], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Smart Library</title>

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

        .profile-card,
        .form-card {
            border: none;
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 22px rgba(16, 42, 67, 0.08);
        }

        .profile-header {
            padding: 30px;
            border-radius: 18px 18px 0 0;
            color: white;
            background:
                radial-gradient(circle at 82% 20%, rgba(244, 185, 66, 0.25), transparent 25%),
                linear-gradient(135deg, #176b9f, #6d3fc7);
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid rgba(255, 255, 255, 0.30);
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.16);
            font-size: 2rem;
            font-weight: 800;
        }

        .form-card {
            padding: 25px;
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

        .info-row {
            padding: 13px 0;
            border-bottom: 1px solid #eef1f5;
        }

        .info-row:last-child {
            border-bottom: none;
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

        <a class="nav-link" href="my_loans.php">
            <i class="fa-solid fa-book-open"></i> My Borrowed Books
        </a>

        <a class="nav-link" href="reservations.php">
            <i class="fa-solid fa-bookmark"></i> My Reservations
        </a>

        <a class="nav-link active" href="profile.php">
            <i class="fa-solid fa-user"></i> My Profile
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
            <h6 class="mb-0 fw-bold">Profile Settings</h6>
        </div>

        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </header>

    <section class="page-content">

        <div class="mb-4">
            <h2 class="page-title mb-1">My Profile</h2>
            <p class="text-muted mb-0">
                Keep your library account information up to date.
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo e($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Profile summary -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar mb-3">
                            <?php echo e($initial); ?>
                        </div>

                        <h4 class="fw-bold mb-1"><?php echo e($profile["full_name"]); ?></h4>
                        <p class="mb-0 text-white-50"><?php echo e($profile["member_code"]); ?></p>
                    </div>

                    <div class="card-body p-4">
                        <div class="info-row">
                            <small class="text-muted d-block">Email Address</small>
                            <strong><?php echo e($profile["email"]); ?></strong>
                        </div>

                        <div class="info-row">
                            <small class="text-muted d-block">Phone Number</small>
                            <strong><?php echo e($profile["phone"]); ?></strong>
                        </div>

                        <div class="info-row">
                            <small class="text-muted d-block">Department / Class</small>
                            <strong><?php echo e($profile["department_class"]); ?></strong>
                        </div>

                        <div class="info-row">
                            <small class="text-muted d-block">Address</small>
                            <strong><?php echo e($profile["address"] ?: "Not provided"); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile forms -->
            <div class="col-lg-8">

                <div class="form-card mb-4">
                    <h5 class="fw-bold mb-1">
                        <i class="fa-solid fa-user-pen text-primary me-2"></i>
                        Update Contact Details
                    </h5>

                    <p class="text-muted small mb-4">
                        Your name and email are managed by the library administrator.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Phone Number</label>

                                <input
                                    type="tel"
                                    class="form-control"
                                    name="phone"
                                    value="<?php echo e($profile["phone"]); ?>"
                                    required
                                >
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Department / Class</label>

                                <input
                                    type="text"
                                    class="form-control"
                                    name="department_class"
                                    value="<?php echo e($profile["department_class"]); ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Address</label>

                            <input
                                type="text"
                                class="form-control"
                                name="address"
                                value="<?php echo e($profile["address"]); ?>"
                                placeholder="Example: Accra, Ghana"
                            >
                        </div>

                        <button type="submit" class="btn btn-primary save-button">
                            <i class="fa-solid fa-floppy-disk me-2"></i>
                            Save Profile Changes
                        </button>
                    </form>
                </div>

                <div class="form-card">
                    <h5 class="fw-bold mb-1">
                        <i class="fa-solid fa-lock text-primary me-2"></i>
                        Change Password
                    </h5>

                    <p class="text-muted small mb-4">
                        Use at least 8 characters and keep your password private.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Current Password</label>

                            <input
                                type="password"
                                class="form-control"
                                name="current_password"
                                placeholder="Enter your current password"
                                required
                            >
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">New Password</label>

                                <input
                                    type="password"
                                    class="form-control"
                                    name="new_password"
                                    placeholder="At least 8 characters"
                                    required
                                >
                            </div>

                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-semibold">Confirm New Password</label>

                                <input
                                    type="password"
                                    class="form-control"
                                    name="confirm_password"
                                    placeholder="Enter new password again"
                                    required
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary save-button">
                            <i class="fa-solid fa-key me-2"></i>
                            Update Password
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>