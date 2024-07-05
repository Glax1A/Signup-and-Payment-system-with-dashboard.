<?php
ob_start();

require_once 'config.php';
require_once '../stripe-php-15.0.0/init.php';
session_start();

function outputJSON($data) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['can_view_stripe']) || !$_SESSION['can_view_stripe']) {
    outputJSON(['success' => false, 'error' => 'Permission denied']);
}

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    outputJSON(['success' => false, 'error' => 'Invalid request method']);
}

$paymentIntentId = filter_input(INPUT_POST, 'payment_intent_id', FILTER_SANITIZE_STRING);
$userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
$refundType = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);

if (empty($paymentIntentId) || empty($refundType)) {
    outputJSON(['success' => false, 'error' => 'Missing required parameters']);
}

try {
    $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

    $refund = \Stripe\Refund::create([
        'payment_intent' => $paymentIntentId,
    ]);

    if ($refundType === 'user') {
        if (empty($userId)) {
            outputJSON(['success' => false, 'error' => 'Missing user ID for user refund']);
        }
        $stmt = $conn->prepare("UPDATE users SET refund_status = 'refunded' WHERE stripe_payment_intent_id = :payment_intent_id AND id = :user_id");
        $stmt->bindParam(':user_id', $userId);
    } elseif ($refundType === 'donation') {
        $stmt = $conn->prepare("UPDATE donations SET refund_status = 'refunded' WHERE stripe_payment_intent_id = :payment_intent_id");
    } else {
        throw new Exception('Invalid refund type');
    }

    $stmt->bindParam(':payment_intent_id', $paymentIntentId);
    $stmt->execute();

    outputJSON(['success' => true]);
} catch (\Stripe\Exception\ApiErrorException $e) {
    outputJSON(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    outputJSON(['success' => false, 'error' => $e->getMessage()]);
}
