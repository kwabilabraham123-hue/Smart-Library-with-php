<?php
require_once __DIR__ . "/functions.php";

/* Redirect users who have not logged in */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect("/Smart-Library/auth/login.php");
    }
}

/* Allow access only to specified user roles */
function requireRole($allowedRoles) {
    requireLogin();

    if (!in_array($_SESSION["role"], $allowedRoles)) {
        redirect("/Smart-Library/auth/login.php");
    }
}