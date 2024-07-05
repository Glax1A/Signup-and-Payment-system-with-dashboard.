<?php
require_once 'config.php';
require_once '../stripe-php-15.0.0/init.php';

session_start();

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$payment_intent = null;
$nonce = generate_nonce();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'])) {
    foreach ($_POST as $key => $value) {
        $_SESSION['user_data'][$key] = sanitize_input($value);
    }

    try {
        $customer = \Stripe\Customer::create([
            'email' => $_SESSION['user_data']['email'],
            'name' => $_SESSION['user_data']['name'],
        ]);
        $_SESSION['stripe_customer_id'] = $customer->id;

        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => 1000,
            'currency' => 'gbp',
            'customer' => $customer->id,
            'description' => 'Payment for something etc',
        ]);

        $_SESSION['payment_intent_id'] = $payment_intent->id;
        $_SESSION['payment_intent_description'] = $payment_intent->description;

    } catch (\Stripe\Exception\ApiErrorException $e) {
        $error = $e->getMessage();
    }
}

header("Content-Security-Policy: default-src 'self'; script-src 'self' https://js.stripe.com 'nonce-{$nonce}'; frame-src https://js.stripe.com");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment</title>
    <link rel="stylesheet" href="assets/process_payment.css">
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
    <div class="container">
        <h1>Complete Your Payment</h1>
        <?php if (isset($error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif ($payment_intent): ?>
            <form id="payment-form">
                <div id="card-element"></div>
                <button id="submit">Pay Now</button>
            </form>

            <p class="secure-message"><i class="fas fa-lock secure-icon"></i>Your payment is secure.</p>
            <?php if (isset($_SESSION['payment_intent_description'])): ?>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($_SESSION['payment_intent_description']); ?></p>
            <?php endif; ?>

            <script nonce="<?php echo $nonce; ?>">
                var stripe = Stripe('<?php echo STRIPE_PUBLISHABLE_KEY; ?>');
                var elements = stripe.elements();
                var cardElement = elements.create('card', {
                    style: {
                        base: {
                            fontSize: '16px',
                            color: '#32325d',
                        }
                    }
                });
                cardElement.mount('#card-element');

                var form = document.getElementById('payment-form');
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    stripe.confirmCardPayment('<?php echo $payment_intent->client_secret; ?>', {
                        payment_method: {
                            card: cardElement,
                            billing_details: {
                                name: '<?php echo htmlspecialchars($_SESSION['user_data']['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
                                email: '<?php echo htmlspecialchars($_SESSION['user_data']['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'
                            }
                        }
                    }).then(function(result) {
                        if (result.error) {
                            console.error(result.error.message);
                        } else {
                            window.location.href = 'submit_data.php';
                        }
                    });
                });
            </script>
        <?php else: ?>
            <p class="error-message">There was an error processing your request. Please try again.</p>
        <?php endif; ?>
    </div>
</body>
</html>
