<?php
require_once __DIR__ . "/../includes/functions.php";

/* Remove all saved session information */
$_SESSION = [];

/* Remove the session cookie */
if (ini_get("session.use_cookies")) {
    $parameters = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $parameters["path"],
        $parameters["domain"],
        $parameters["secure"],
        $parameters["httponly"]
    );
}

/* Destroy the session and return to login */
session_destroy();

header("Location: /Smart-Library/auth/login.php");
exit;