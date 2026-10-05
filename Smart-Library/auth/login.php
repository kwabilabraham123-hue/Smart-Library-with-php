<?php
require_once "../config/database.php";
require_once "../includes/functions.php";

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect("../admin/dashboard.php");
    } elseif (isLibrarian()) {
        redirect("../librarian/dashboard.php");
    } elseif (isMember()) {
        redirect("../member/dashboard.php");
    }
}

$errorMessage = "";
$successMessage = "";
$email = "";

if (isset($_GET["registered"])) {
    $successMessage = "Your account was created successfully. Please log in.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $errorMessage = "Please enter your email address and password.";
    } else {
        $statement = $pdo->prepare(
            "SELECT id, full_name, email, password, role, status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $statement->execute([$email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user["password"])) {
            $errorMessage = "Incorrect email address or password.";
        } elseif ($user["status"] !== "active") {
            $errorMessage = "Your account is inactive. Please contact the administrator.";
        } else {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "admin") {
                redirect("../admin/dashboard.php");
            } elseif ($user["role"] === "librarian") {
                redirect("../librarian/dashboard.php");
            } elseif ($user["role"] === "member") {
                redirect("../member/dashboard.php");
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
    <title>Login | Smart Library</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #102a43;
            --blue: #176b9f;
            --purple: #6d3fc7;
            --pink: #e84393;
            --orange: #f39c12;
            --light: #f6f8fc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            background: var(--light);
            font-family: Arial, Helvetica, sans-serif;
        }

        .login-page {
            min-height: 100vh;
        }

        /* Left panel with your library background image */
        .login-banner {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            overflow: hidden;
            padding: 65px;
            color: #ffffff;

            /* Colour overlay on top of the image */
            background-image:
                linear-gradient(
                    135deg,
                    rgba(12, 42, 67, 0.92),
                    rgba(23, 107, 159, 0.80),
                    rgba(109, 63, 199, 0.72)
                ),
                url("../assets/images/login-library.jpg");

            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(2px);
            opacity: 0.34;
        }

        .shape-one {
            width: 300px;
            height: 300px;
            top: -100px;
            right: -80px;
            background: #f4b942;
            animation: floatShape 8s ease-in-out infinite;
        }

        .shape-two {
            width: 230px;
            height: 230px;
            bottom: -70px;
            left: -70px;
            background: #e84393;
            animation: floatShape 10s ease-in-out infinite reverse;
        }

        .shape-three {
            width: 120px;
            height: 120px;
            top: 45%;
            right: 10%;
            background: #00d2ff;
            animation: floatShape 7s ease-in-out infinite;
        }

        @keyframes floatShape {
            0%, 100% {
                transform: translateY(0) translateX(0) rotate(0deg);
            }

            50% {
                transform: translateY(-35px) translateX(20px) rotate(15deg);
            }
        }

        .banner-content {
            position: relative;
            z-index: 2;
            max-width: 520px;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.15);
            font-size: 1.5rem;
            backdrop-filter: blur(8px);
        }

        .welcome-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 45px;
            padding: 9px 15px;
            border: 1px solid rgba(255, 255, 255, 0.20);
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            backdrop-filter: blur(8px);
        }

        .banner-title {
            margin-top: 20px;
            font-size: clamp(2.6rem, 4vw, 4.4rem);
            font-weight: 800;
            line-height: 1.08;
            text-shadow: 0 3px 15px rgba(0, 0, 0, 0.28);
        }

        .banner-title span {
            color: #ffd166;
        }

        .banner-text {
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.28);
        }

        .role-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 28px;
        }

        .role-tag {
            padding: 9px 13px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            font-size: 0.85rem;
            backdrop-filter: blur(8px);
        }

        .library-orbit {
            position: relative;
            width: 220px;
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 42px;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.10);
            animation: rotateRing 18s linear infinite;
        }

        .library-orbit::before,
        .library-orbit::after {
            position: absolute;
            border-radius: 50%;
            content: "";
        }

        .library-orbit::before {
            width: 16px;
            height: 16px;
            top: 16px;
            left: 32px;
            background: #ffd166;
            box-shadow: 140px 155px 0 #ff7eb3;
        }

        .library-orbit::after {
            width: 10px;
            height: 10px;
            top: 65px;
            right: 22px;
            background: #65e6ff;
        }

        .library-icon {
            width: 128px;
            height: 128px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #ffffff;
            background: linear-gradient(135deg, #e84393, #f39c12);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
            font-size: 3.8rem;
            animation: rotateReverse 18s linear infinite;
        }

        @keyframes rotateRing {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @keyframes rotateReverse {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(-360deg);
            }
        }

        /* Right login area */
        .login-form-side {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 35px;
            background:
                radial-gradient(circle at 90% 8%, rgba(109, 63, 199, 0.10), transparent 25%),
                radial-gradient(circle at 8% 92%, rgba(11, 163, 96, 0.10), transparent 25%),
                #f7f9fc;
        }

        .login-card {
            width: 100%;
            max-width: 465px;
            overflow: hidden;
            border: 1px solid rgba(23, 107, 159, 0.10);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 18px 45px rgba(16, 42, 67, 0.14);
            animation: cardAppear 0.7s ease-out;
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-top-line {
            height: 6px;
            background: linear-gradient(90deg, #176b9f, #6d3fc7, #e84393, #f39c12);
            background-size: 300% 100%;
            animation: movingLine 5s linear infinite;
        }

        @keyframes movingLine {
            from {
                background-position: 0% 0%;
            }

            to {
                background-position: 300% 0%;
            }
        }

        .login-card-body {
            padding: 40px;
        }

        .back-link {
            color: var(--blue);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .back-link:hover {
            color: var(--purple);
        }

        .login-title {
            margin-top: 28px;
            color: var(--navy);
            font-size: 2rem;
            font-weight: 800;
        }

        .login-subtitle {
            color: #6b7280;
        }

        .form-label {
            color: #344054;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .input-group {
            transition: transform 0.2s ease;
        }

        .input-group:focus-within {
            transform: translateY(-2px);
        }

        .input-group-text {
            border-color: #dce3ec;
            border-radius: 12px 0 0 12px;
            background: #ffffff;
        }

        .form-control {
            padding: 13px 12px;
            border-color: #dce3ec;
            border-radius: 0 12px 12px 0;
        }

        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.23rem rgba(109, 63, 199, 0.12);
        }

        .password-button {
            border-color: #dce3ec;
            border-left: 0;
            border-radius: 0 12px 12px 0;
            background: #ffffff;
            color: var(--blue);
        }

        .password-button:hover {
            color: var(--purple);
            background: #ffffff;
        }

        .login-button {
            padding: 13px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #176b9f, #6d3fc7);
            box-shadow: 0 8px 18px rgba(61, 73, 158, 0.25);
            font-weight: 700;
            transition: 0.25s ease;
        }

        .login-button:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, #0c527f, #512aa3);
            box-shadow: 0 12px 25px rgba(61, 73, 158, 0.30);
        }

        .account-link {
            color: var(--pink);
            font-weight: 700;
            text-decoration: none;
        }

        .account-link:hover {
            color: var(--purple);
            text-decoration: underline;
        }

        @media (max-width: 991px) {
            .login-banner {
                display: none;
            }

            .login-form-side {
                padding: 25px 15px;
            }

            .login-card-body {
                padding: 30px 24px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">
    <div class="row login-page">

        <!-- Left animated library panel -->
        <div class="col-lg-6 login-banner">
            <div class="shape shape-one"></div>
            <div class="shape shape-two"></div>
            <div class="shape shape-three"></div>

            <div class="banner-content">
                <div class="d-flex align-items-center gap-3">
                    <span class="brand-icon">
                        <i class="fa-solid fa-book-open-reader"></i>
                    </span>

                    <h3 class="mb-0 fw-bold">Smart Library</h3>
                </div>

                <div class="welcome-pill">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    LIBRARY MANAGEMENT MADE EASY
                </div>

                <h1 class="banner-title">
                    Discover. Learn. <span>Connect.</span>
                </h1>

                <p class="lead text-white-50 mt-3 mb-0 banner-text">
                    Access your account to manage books, borrowing, returns,
                    fines, and reservations from one smart platform.
                </p>

                <div class="role-list">
                    <span class="role-tag">
                        <i class="fa-solid fa-user-shield me-1"></i> Admin
                    </span>

                    <span class="role-tag">
                        <i class="fa-solid fa-user-tie me-1"></i> Librarian
                    </span>

                    <span class="role-tag">
                        <i class="fa-solid fa-user-graduate me-1"></i> Member
                    </span>
                </div>

                <div class="library-orbit">
                    <div class="library-icon">
                        <i class="fa-solid fa-book-bookmark"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right login form -->
        <div class="col-lg-6 login-form-side">
            <div class="login-card">
                <div class="card-top-line"></div>

                <div class="login-card-body">
                    <a href="../index.php" class="back-link">
                        <i class="fa-solid fa-arrow-left me-1"></i>
                        Back to homepage
                    </a>

                    <h1 class="login-title">Welcome back! 👋</h1>

                    <p class="login-subtitle mb-4">
                        Sign in and continue your library journey.
                    </p>

                    <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-2"></i>
                            <?php echo e($errorMessage); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($successMessage)): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="fa-solid fa-circle-check me-2"></i>
                            <?php echo e($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm">

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>

                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-envelope text-primary"></i>
                                </span>

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
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>

                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa-solid fa-lock text-primary"></i>
                                </span>

                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    required
                                >

                                <button
                                    class="btn password-button"
                                    type="button"
                                    id="togglePassword"
                                    title="Show or hide password"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary login-button w-100" id="loginButton">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>
                            Login to Smart Library
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-2 text-muted">
                            Do not have a Member account?
                            <a href="register.php" class="account-link">
                                Create an account
                            </a>
                        </p>

                        <small class="text-muted">
                            <i class="fa-solid fa-shield-halved me-1 text-success"></i>
                            Secure Smart Library Access
                        </small>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");
    const loginForm = document.getElementById("loginForm");
    const loginButton = document.getElementById("loginButton");

    togglePassword.addEventListener("click", function () {
        const passwordHidden = passwordInput.type === "password";

        passwordInput.type = passwordHidden ? "text" : "password";

        this.innerHTML = passwordHidden
            ? '<i class="fa-solid fa-eye-slash"></i>'
            : '<i class="fa-solid fa-eye"></i>';
    });

    loginForm.addEventListener("submit", function () {
        loginButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin me-2"></i>Signing in...';

        loginButton.disabled = true;
    });
</script>

</body>
</html>