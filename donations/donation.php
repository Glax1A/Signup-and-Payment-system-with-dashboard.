<?php
require_once 'config.php';
require_once '../stripe-php-15.0.0/init.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = intval($_POST['amount'] * 100);
    $comment = $conn->real_escape_string($_POST['comment']);

    try {
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'gbp',
            'description' => 'Donation',
        ]);

        $customer = \Stripe\Customer::create();

        $sql = "INSERT INTO donations (stripe_payment_intent_id, stripe_customer_id, amount, comment, refund_status, payment_made) 
                VALUES ('{$paymentIntent->id}', '{$customer->id}', $amount, '$comment', 'not_refunded', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            $donationId = $conn->insert_id;
            echo json_encode(['clientSecret' => $paymentIntent->client_secret, 'donationId' => $donationId]);
        } else {
            echo json_encode(['error' => 'Error saving donation: ' . $conn->error]);
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$nonce = base64_encode(random_bytes(16));

header("Content-Security-Policy: default-src 'self'; script-src 'self' https://js.stripe.com 'nonce-$nonce'; connect-src 'self' https://api.stripe.com https://*.stripe.com; frame-src 'self' https://js.stripe.com https://hooks.stripe.com; img-src 'self' https://*.stripe.com;");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a Donation</title>
    <script src="https://js.stripe.com/v3/"></script>
    <script nonce="<?php echo $nonce; ?>" src="donation.js" defer></script>
</head>
<body>
    <h1>Make a Donation</h1>
    <form id="payment-form">
        <div>
            <label for="amount">Donation Amount (£):</label>
            <input type="number" id="amount" name="amount" min="1" step="0.01" required>
        </div>
        <div>
            <label for="comment">Comment:</label>
            <textarea id="comment" name="comment" rows="4"></textarea>
        </div>
        <div id="card-element"></div>
        <div id="card-errors" role="alert"></div>
        <button type="submit">Donate</button>
    </form>
</body>
</html>