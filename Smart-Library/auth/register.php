<?php
require_once "../config/database.php";
require_once "../includes/functions.php";

$errorMessage = "";
$successMessage = "";

$fullName = "";
$email = "";
$phone = "";
$address = "";
$departmentClass = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $departmentClass = trim($_POST["department_class"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (
        empty($fullName) ||
        empty($email) ||
        empty($phone) ||
        empty($departmentClass) ||
        empty($password) ||
        empty($confirmPassword)
    ) {
        $errorMessage = "Please complete all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $errorMessage = "Your password must contain at least 6 characters.";
    } elseif ($password !== $confirmPassword) {
        $errorMessage = "Passwords do not match.";
    } else {
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkUser->execute([$email]);

        if ($checkUser->fetch()) {
            $errorMessage = "An account with this email address already exists.";
        } else {
            try {
                $pdo->beginTransaction();

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $addUser = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password, role, status)
                     VALUES (?, ?, ?, 'member', 'active')"
                );

                $addUser->execute([
                    $fullName,
                    $email,
                    $hashedPassword
                ]);

                $userId = $pdo->lastInsertId();

                // Creates a membership number such as MEM-0004.
                $memberCode = "MEM-" . str_pad($userId, 4, "0", STR_PAD_LEFT);

                $addMember = $pdo->prepare(
                    "INSERT INTO members
                     (user_id, member_code, phone, address, department_class)
                     VALUES (?, ?, ?, ?, ?)"
                );

                $addMember->execute([
                    $userId,
                    $memberCode,
                    $phone,
                    $address,
                    $departmentClass
                ]);

                $pdo->commit();

                $successMessage =
                    "Account created successfully. Your Member ID is " .
                    $memberCode .
                    ". You can now log in.";

                $fullName = "";
                $email = "";
                $phone = "";
                $address = "";
                $departmentClass = "";

            } catch (Exception $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errorMessage = "Account creation failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Member Account | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #0c2d4d;
            --blue: #176b9f;
            --gold: #f4b942;
            --light: #f5f7fb;
        }

        body {
            min-height: 100vh;
            background: var(--light);
            font-family: Arial, Helvetica, sans-serif;
        }

        .register-page {
            min-height: 100vh;
            padding: 45px 0;
            background:
                radial-gradient(circle at 10% 10%, rgba(23, 107, 159, 0.12), transparent 25%),
                var(--light);
        }

        .register-card {
            max-width: 820px;
            margin: auto;
            border: none;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 35px rgba(16, 24, 40, 0.12);
        }

        .register-header {
            padding: 32px;
            color: white;
            background: linear-gradient(135deg, var(--navy), var(--blue));
        }

        .brand-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 43px;
            height: 43px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.16);
        }

        .form-control {
            padding: 12px 14px;
            border-radius: 10px;
        }

        .form-control:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 0.22rem rgba(23, 107, 159, 0.15);
        }

        .register-button {
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            font-weight: bold;
        }

        .register-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(12, 45, 77, 0.22);
        }

        .login-link {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link:hover {
            color: var(--navy);
        }
    </style>
</head>

<body>

<section class="register-page">
    <div class="container">

        <div class="card register-card">
            <div class="register-header">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="brand-icon">
                        <i class="fa-solid fa-book-open-reader"></i>
                    </span>

                    <strong>Smart Library</strong>
                </div>

                <h2 class="fw-bold mb-2">Create a Member Account</h2>

                <p class="mb-0 text-white-50">
                    Join the library to search books, track borrowing, and make reservations.
                </p>
            </div>

            <div class="card-body p-4 p-md-5">

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <?php echo e($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-circle-check me-2"></i>
                            <strong><?php echo e($successMessage); ?></strong>
                        </div>

                        <a href="login.php" class="btn btn-success btn-sm mt-3">
                            <i class="fa-solid fa-right-to-bracket me-1"></i>
                            Go to Login
                        </a>
                    </div>
                <?php endif; ?>

                <form method="POST" id="registerForm">

                    <h5 class="fw-bold mb-3">
                        <i class="fa-solid fa-user me-2 text-primary"></i>
                        Personal Information
                    </h5>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label fw-semibold">
                                Full Name <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="full_name"
                                name="full_name"
                                value="<?php echo e($fullName); ?>"
                                placeholder="Enter your full name"
                                required
                            >
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label fw-semibold">
                                Email Address <span class="text-danger">*</span>
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?php echo e($email); ?>"
                                placeholder="you@example.com"
                                required
                            >
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label fw-semibold">
                                Phone Number <span class="text-danger">*</span>
                            </label>

                            <input
                                type="tel"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?php echo e($phone); ?>"
                                placeholder="0240000000"
                                required
                            >
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="department_class" class="form-label fw-semibold">
                                Department / Class <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="department_class"
                                name="department_class"
                                value="<?php echo e($departmentClass); ?>"
                                placeholder="Example: Information Technology"
                                required
                            >
                        </div>

                        <div class="col-12 mb-4">
                            <label for="address" class="form-label fw-semibold">
                                Address
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="address"
                                name="address"
                                value="<?php echo e($address); ?>"
                                placeholder="Example: Accra, Ghana"
                            >
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3">
                        <i class="fa-solid fa-lock me-2 text-primary"></i>
                        Create Password
                    </h5>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label fw-semibold">
                                Password <span class="text-danger">*</span>
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="At least 6 characters"
                                required
                            >
                        </div>

                        <div class="col-md-6 mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">
                                Confirm Password <span class="text-danger">*</span>
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Re-enter your password"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary register-button w-100" id="registerButton">
                        <i class="fa-solid fa-user-plus me-2"></i>
                        Create Member Account
                    </button>

                </form>

                <p class="text-center text-muted mt-4 mb-0">
                    Already have an account?
                    <a href="login.php" class="login-link">Login here</a>
                </p>
            </div>
        </div>

    </div>
</section>

<script>
    const registerForm = document.getElementById("registerForm");
    const registerButton = document.getElementById("registerButton");

    registerForm.addEventListener("submit", function () {
        registerButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin me-2"></i>Creating account...';

        registerButton.disabled = true;
    });
</script>

</body>
</html>