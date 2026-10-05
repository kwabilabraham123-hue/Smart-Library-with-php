<?php
require_once __DIR__ . "/functions.php";

/* Sends visitors to login if they are not signed in */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect("/Smart-Library/auth/login.php");
    }
}

/*
   Restricts a page to one or more user roles.

   Example:
   requireRole(["admin"]);

   or:
   requireRole(["admin", "librarian"]);
*/
function requireRole($allowedRoles) {
    requireLogin();

    if (!in_array($_SESSION["role"], $allowedRoles)) {
        redirect("/Smart-Library/auth/login.php");
    }
}