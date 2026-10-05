<?php
require_once "config/database.php";

$newPassword = "password";
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$statement = $pdo->prepare(
    "UPDATE users
     SET password = ?
     WHERE email IN (
        'admin@smartlibrary.com',
        'librarian@smartlibrary.com',
        'ama@smartlibrary.com'
     )"
);

$statement->execute([$hashedPassword]);

echo "<h2>Demo account passwords reset successfully.</h2>";
echo "<p><strong>Admin:</strong> admin@smartlibrary.com</p>";
echo "<p><strong>Librarian:</strong> librarian@smartlibrary.com</p>";
echo "<p><strong>Member:</strong> ama@smartlibrary.com</p>";
echo "<p><strong>Password for all accounts:</strong> password</p>";