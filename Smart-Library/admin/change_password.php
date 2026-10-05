<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/auth_check.php";

requireRole(["admin"]);

$errorMessage = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMessage = "Please complete all password fields.";
    } elseif (strlen($newPassword) < 8) {
        $errorMessage = "Your new password must be at least 8 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = "New passwords do not match.";
    } else {
        $statement = $pdo->prepare(
            "SELECT password FROM users WHERE id = ? LIMIT 1"
        );

        $statement->execute([$_SESSION["user_id"]]);
        $user = $statement->fetch();

        if (!$user || !password_verify($currentPassword, $user["password"])) {
            $errorMessage = "Your current password is incorrect.";
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $update = $pdo->prepare(
                "UPDATE users SET password = ? WHERE id = ?"
            );

            $update->execute([$newHash, $_SESSION["user_id"]]);

            $successMessage = "Your password has been changed successfully.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fb, #e7eff7);
            font-family: Arial, Helvetica, sans-serif;
        }

        .password-card {
            max-width: 550px;
            border: none;
            border-radius: 18px;
            box-shadow: 0 14px 35px rgba(16, 42, 67, 0.14);
        }

        .card-header-custom {
            padding: 30px;
            border-radius: 18px 18px 0 0;
            color: #ffffff;
            background: linear-gradient(135deg, #102a43, #176b9f, #6d3fc7);
        }

        .form-control {
            padding: 12px;
            border-radius: 10px;
        }

        .form-control:focus {
            border-color: #6d3fc7;
            box-shadow: 0 0 0 0.22rem rgba(109, 63, 199, 0.14);
        }

        .save-button {
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="container py-5">
    <div class="card password-card mx-auto">
        <div class="card-header-custom">
            <a href="dashboard.php" class="text-white text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Back to Dashboard
            </a>

            <h2 class="fw-bold mt-4 mb-2">
                <i class="fa-solid fa-lock me-2"></i>
                Change Password
            </h2>

            <p class="mb-0 text-white-50">
                Choose a strong password to keep your Admin account secure.
            </p>
        </div>

        <div class="card-body p-4 p-md-5">

            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <?php echo e($errorMessage); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    <?php echo e($successMessage); ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="mb-3">
                    <label for="current_password" class="form-label fw-semibold">
                        Current Password
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="current_password"
                        name="current_password"
                        placeholder="Enter your current password"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label fw-semibold">
                        New Password
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="new_password"
                        name="new_password"
                        placeholder="At least 8 characters"
                        required
                    >
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label fw-semibold">
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Enter the new password again"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary save-button w-100">
                    <i class="fa-solid fa-key me-2"></i>
                    Update Password
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>