<?php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'test');
define('DB_USER', 'root');
define('DB_PASS', '');
define('STRIPE_SECRET_KEY', 'sk_redacted_for_obvious_reasons');
define('STRIPE_PUBLISHABLE_KEY', 'pk_redacted_for_obvious_reasons');

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

session_start();

function checkPermission($permission) {
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        header('Location: login.php');
        exit();
    }

    if ($_SESSION['role'] !== 'ultimate_admin' && !$_SESSION[$permission]) {
        header('Location: unauthorized.php');
        exit();
    }
}
?>