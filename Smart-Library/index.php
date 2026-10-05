<?php
require_once "includes/functions.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Library | Library Management System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --navy: #0c2d4d;
            --blue: #176b9f;
            --gold: #f4b942;
            --light-bg: #f5f7fb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--light-bg);
            font-family: Arial, Helvetica, sans-serif;
        }

        /* Navigation bar */
        .navbar {
            background: linear-gradient(135deg, var(--navy), var(--blue));
            box-shadow: 0 3px 18px rgba(12, 45, 77, 0.22);
        }

        .brand-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.16);
        }

        /* Hero section */
        .hero {
            min-height: 560px;
            display: flex;
            align-items: center;
            overflow: hidden;
            color: #ffffff;
            background:
                radial-gradient(circle at 85% 20%, rgba(244, 185, 66, 0.25), transparent 25%),
                linear-gradient(135deg, #0c2d4d, #176b9f);
        }

        .hero-badge {
            display: inline-block;
            padding: 8px 14px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.10);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .login-button {
            padding: 12px 23px;
            border-radius: 10px;
            color: var(--navy);
            font-weight: bold;
            transition: 0.2s ease;
        }

        .login-button:hover {
            color: var(--navy);
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.20);
        }

        /* Library image circle */
        .hero-image-box {
            width: 300px;
            height: 300px;
            margin: auto;
            overflow: hidden;
            border: 8px solid rgba(255, 255, 255, 0.18);
            border-radius: 50%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            animation: float 4s ease-in-out infinite;
        }

        .hero-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .hero-image-box:hover img {
            transform: scale(1.10);
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-14px);
            }
        }

        /* Feature cards */
        .feature-card {
            height: 100%;
            padding: 28px;
            border: none;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(16, 24, 40, 0.08);
            transition: 0.25s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 28px rgba(16, 24, 40, 0.15);
        }

        .feature-icon {
            width: 58px;
            height: 58px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 14px;
            color: #ffffff;
            background: linear-gradient(135deg, var(--blue), #2b91cb);
            font-size: 1.4rem;
        }

        /* Footer */
        footer {
            background: var(--navy);
            color: rgba(255, 255, 255, 0.80);
        }

        /* Mobile design */
        @media (max-width: 991px) {
            .hero {
                padding: 75px 0;
                text-align: center;
            }

            .hero-image-box {
                width: 210px;
                height: 210px;
                margin-top: 15px;
            }
        }
    </style>
</head>

<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark py-3">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <span class="brand-icon">
                <i class="fa-solid fa-book-open-reader"></i>
            </span>
            Smart Library
        </a>

        <a href="auth/login.php" class="btn btn-light btn-sm px-3 py-2 fw-semibold">
            <i class="fa-solid fa-right-to-bracket me-1"></i>
            Login
        </a>
    </div>
</nav>

<!-- Main hero section -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-7">
                <span class="hero-badge">
                    <i class="fa-solid fa-sparkles me-1"></i>
                    Modern Library Management
                </span>

                <h1 class="display-4 fw-bold mt-3 mb-3">
                    Manage your library with confidence.
                </h1>

                <p class="lead text-white-50 mb-4">
                    Smart Library helps administrators, librarians, and members
                    manage books, borrowing, returns, fines, and reservations
                    through one secure and easy-to-use system.
                </p>

                <a href="auth/login.php" class="btn btn-warning login-button">
                    Access Your Account
                    <i class="fa-solid fa-arrow-right ms-2"></i>
                </a>
            </div>

            <!-- Library image on the right -->
            <div class="col-lg-5">
                <div class="hero-image-box">
                    <img src="assets/images/library.jpg" alt="Smart Library Interior">
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Features -->
<section class="container py-5">
    <div class="text-center mb-5">
        <p class="text-primary fw-semibold mb-2">SMART LIBRARY FEATURES</p>
        <h2 class="fw-bold">Everything your library needs</h2>
        <p class="text-muted">
            A professional system for managing daily library activities.
        </p>
    </div>

    <div class="row g-4">

        <div class="col-md-4">
            <div class="card feature-card">
                <div class="feature-icon mb-4">
                    <i class="fa-solid fa-book"></i>
                </div>

                <h4 class="fw-bold">Book Management</h4>

                <p class="text-muted mb-0">
                    Manage books, authors, ISBNs, categories, shelf numbers,
                    stock quantity, and availability.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card feature-card">
                <div class="feature-icon mb-4">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                </div>

                <h4 class="fw-bold">Borrowing & Returns</h4>

                <p class="text-muted mb-0">
                    Track loans, due dates, returned books, overdue records,
                    and automatically calculated fines.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card feature-card">
                <div class="feature-icon mb-4">
                    <i class="fa-solid fa-users"></i>
                </div>

                <h4 class="fw-bold">Member Portal</h4>

                <p class="text-muted mb-0">
                    Members can view borrowed books, loan history, fine status,
                    book availability, and reservations.
                </p>
            </div>
        </div>

    </div>
</section>

<!-- Footer -->
<footer class="py-4 mt-4">
    <div class="container text-center">
        <small>
            <i class="fa-solid fa-book-open-reader me-1"></i>
            Smart Library Management System &copy; <?php echo date("Y"); ?>
        </small>
    </div>
</footer>

</body>
</html>