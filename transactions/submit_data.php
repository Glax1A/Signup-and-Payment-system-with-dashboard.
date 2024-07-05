<?php
require_once 'config.php';
require_once '../stripe-php-15.0.0/init.php';

session_start();

if (!isset($_SESSION['user_data']) || !isset($_SESSION['payment_intent_id']) || !isset($_SESSION['stripe_customer_id']) || !isset($_SESSION['payment_intent_description'])) {
    header('Location: index.php');
    exit();
}

$user_data = $_SESSION['user_data'];
$payment_intent_id = $_SESSION['payment_intent_id'];
$stripe_customer_id = $_SESSION['stripe_customer_id'];
$payment_intent_description = $_SESSION['payment_intent_description'];

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM users WHERE name = :name AND email = :email");
    $stmt_check->bindParam(':name', $user_data['name']);
    $stmt_check->bindParam(':email', $user_data['email']);
    $stmt_check->execute();
    $count = $stmt_check->fetchColumn();

    if ($count > 0) {
        echo "<div class='container'>";
        echo "<h1>Error</h1>";
        echo "<p class='error-message'>A user with the name '{$user_data['name']}' and email '{$user_data['email']}' already exists.</p>";
        echo "</div>";
    } else {
        $stmt_insert = $conn->prepare("INSERT INTO users (name, email, stripe_customer_id, stripe_payment_intent_id, description) VALUES (:name, :email, :stripe_customer_id, :stripe_payment_intent_id, :description)");
        $stmt_insert->bindParam(':name', $user_data['name']);
        $stmt_insert->bindParam(':email', $user_data['email']);
        $stmt_insert->bindParam(':stripe_customer_id', $stripe_customer_id);
        $stmt_insert->bindParam(':stripe_payment_intent_id', $payment_intent_id);
        $stmt_insert->bindParam(':description', $payment_intent_description);

        if ($stmt_insert->execute()) {
            $sender_email = "noreply@yourcompany.com";
            $headers = "From: Your Company <{$sender_email}>\r\n";
            $headers .= "Reply-To: {$sender_email}\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            $admin_email = "admin@example.com";
            $admin_subject = "New User Registration";
            $admin_message = "A new user has registered:\n\nName: {$user_data['name']}\nEmail: {$user_data['email']}\nStripe Customer ID: {$stripe_customer_id}\nPayment Intent ID: {$payment_intent_id}\nDescription: {$payment_intent_description}";
            mail($admin_email, $admin_subject, $admin_message, $headers);

            $user_subject = "Welcome to Our Website";
            $user_message = "Dear {$user_data['name']},\n\nThank you for registering on our website!";
            mail($user_data['email'], $user_subject, $user_message, $headers);

            echo "<div class='container'>";
            echo "<h1>Registration Successful</h1>";
            echo "<p>Check your email for a welcome message.</p>";
            echo "</div>";
        }
    }
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo "<div class='container'>";
    echo "<h1>Error</h1>";
    echo "<p class='error-message'>An error occurred. Please try again later.</p>";
    echo "</div>";
} catch(\Stripe\Exception\ApiErrorException $e) {
    error_log("Stripe API error: " . $e->getMessage());
    echo "<div class='container'>";
    echo "<h1>Error</h1>";
    echo "<p class='error-message'>An error occurred while processing your payment. Please try again later.</p>";
    echo "</div>";
}

unset($_SESSION['user_data']);
unset($_SESSION['payment_intent_id']);
unset($_SESSION['stripe_customer_id']);
unset($_SESSION['payment_intent_description']);
?>
