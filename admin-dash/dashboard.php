<?php
require_once 'config.php';
require_once '../stripe-php-15.0.0/init.php';
checkPermission('can_view_db');

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$emailTemplates = [
    'Refund Issued' => [
        'subject' => 'A refund was issued to you',
        'message' => 'Dear [Name],\n\nA refund was issued to you as requested.\n\nBest regards,\nAdministrator'
    ],
    'Cancelled' => [
        'subject' => 'Information Deleted [or] Cancelled',
        'message' => 'Dear [Name],\n\nAs requested, you have been refunded, and we have acknowledged that you will not be here.\n\nBest regards,\nReatreat admin'
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    checkPermission('can_send_emails');
    $to = filter_var($_POST['email_recipients'], FILTER_SANITIZE_EMAIL);
    $from = filter_var($_POST['email_from'], FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars($_POST['email_subject']);
    $message = htmlspecialchars($_POST['email_message']);
    $headers = 'From: ' . $from . "\r\n" .
               'Reply-To: ' . $from . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    mail($to, $subject, $message, $headers);
}

$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_STRING) ?? 'name';
$order = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_STRING) ?? 'ASC';
$usersPage = filter_input(INPUT_GET, 'users_page', FILTER_SANITIZE_NUMBER_INT) ?? 1;
$usersLimit = 5;
$usersOffset = ($usersPage - 1) * $usersLimit;

$allowedSortColumns = ['name', 'email', 'stripe_customer_id', 'stripe_payment_intent_id', 'description', 'payment_status', 'created_at', 'updated_at'];
$allowedOrderValues = ['ASC', 'DESC'];

if (!in_array($sort, $allowedSortColumns)) {
    $sort = 'name';
}

if (!in_array($order, $allowedOrderValues)) {
    $order = 'ASC';
}

$stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS *, 
    CASE 
        WHEN refund_status = 'refunded' THEN 'Refunded'
        WHEN stripe_payment_intent_id IS NOT NULL THEN 'Paid'
        ELSE 'Not Paid'
    END AS payment_status 
    FROM users 
    WHERE name LIKE :search OR email LIKE :search
    ORDER BY $sort $order
    LIMIT :limit OFFSET :offset");

$searchParam = '%' . $search . '%';
$stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
$stmt->bindValue(':limit', (int) $usersLimit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int) $usersOffset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUsersStmt = $conn->query("SELECT FOUND_ROWS()");
$totalUsers = $totalUsersStmt->fetchColumn();
$totalUsersPages = ceil($totalUsers / $usersLimit);

$stripeDetails = [];
if ($_SESSION['can_view_stripe']) {
    foreach ($users as $user) {
        if ($user['stripe_payment_intent_id']) {
            $paymentIntent = \Stripe\PaymentIntent::retrieve($user['stripe_payment_intent_id']);
            $stripeDetails[$user['stripe_payment_intent_id']] = [
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'customer' => $paymentIntent->customer,
                'status' => $paymentIntent->status
            ];
        }
    }
}

$totalAmountReceived = 0;
$totalRefundsGiven = 0;
$totalTransactions = 0;

$stripePayments = $conn->query("SELECT stripe_payment_intent_id, refund_status FROM users WHERE stripe_payment_intent_id IS NOT NULL");
foreach ($stripePayments as $payment) {
    $paymentIntent = \Stripe\PaymentIntent::retrieve($payment['stripe_payment_intent_id']);
    $totalAmountReceived += $paymentIntent->amount_received;
    $totalTransactions++;
    if ($payment['refund_status'] === 'refunded') {
        $totalRefundsGiven += $paymentIntent->amount_received;
    }
}

$averageTransactionValue = $totalTransactions > 0 ? $totalAmountReceived / $totalTransactions : 0;

$totalDonationsAmount = 0;
$totalDonationsCount = 0;
$totalRefundedDonations = 0;

$donations = $conn->query("SELECT amount FROM donations");
foreach ($donations as $donation) {
    $totalDonationsAmount += $donation['amount'];
    $totalDonationsCount++;
}

$averageDonationValue = $totalDonationsCount > 0 ? $totalDonationsAmount / $totalDonationsCount : 0;

$refundedDonations = $conn->query("SELECT amount FROM donations WHERE refund_status = 'refunded'");
foreach ($refundedDonations as $donation) {
    $totalRefundedDonations += $donation['amount'];
}

$grandTotalReceived = $totalAmountReceived + $totalDonationsAmount - $totalRefundsGiven - $totalRefundedDonations;

$totalAmountReceivedFormatted = number_format($totalAmountReceived / 100, 2);
$totalRefundsGivenFormatted = number_format($totalRefundsGiven / 100, 2);
$averageTransactionValueFormatted = number_format($averageTransactionValue / 100, 2);
$totalDonationsAmountFormatted = number_format($totalDonationsAmount / 100, 2);
$averageDonationValueFormatted = number_format($averageDonationValue / 100, 2);
$grandTotalReceivedFormatted = number_format($grandTotalReceived / 100, 2);
$totalRefundedDonationsFormatted = number_format($totalRefundedDonations / 100, 2);

$donationsPage = filter_input(INPUT_GET, 'donations_page', FILTER_SANITIZE_NUMBER_INT) ?? 1;
$donationsLimit = 5;
$donationsOffset = ($donationsPage - 1) * $donationsLimit;

$donationsStmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM donations 
    ORDER BY payment_made DESC
    LIMIT :limit OFFSET :offset");

$donationsStmt->bindValue(':limit', (int) $donationsLimit, PDO::PARAM_INT);
$donationsStmt->bindValue(':offset', (int) $donationsOffset, PDO::PARAM_INT);
$donationsStmt->execute();
$donations = $donationsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalDonationsStmt = $conn->query("SELECT FOUND_ROWS()");
$totalDonations = $totalDonationsStmt->fetchColumn();
$totalDonationsPages = ceil($totalDonations / $donationsLimit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/styles.css">
    <script src="scripts.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="dark-mode">
    <div class="container">
        <header class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <div class="user-info">
                <span class="signed-in-as">Signed in as: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>

        <section id="statistics" class="dashboard-section">
            <h2>Statistics</h2>
            <div class="stat">
                <h3><i class="fas fa-users"></i> Total Users Signed Up</h3>
                <p><?php echo $totalUsers; ?></p>
            </div>
            <div class="stat">
                <h3><i class="fas fa-dollar-sign"></i> Total Received from Users</h3>
                <p>£<?php echo $totalAmountReceivedFormatted; ?></p>
                <br>
                <i>Refund amount not counted.</i>
            </div>
            <div class="stat">
                <h3><i class="fas fa-chart-line"></i> Average Transaction Value</h3>
                <p>£<?php echo $averageTransactionValueFormatted; ?></p>
            </div>
            <div class="stat">
                <h3><i class="fas fa-file-invoice-dollar"></i> Total Transactions</h3>
                <p><?php echo $totalTransactions; ?></p>
            </div>
            <div class="stat">
                <h3><i class="fas fa-undo-alt"></i> Total Refunds Given</h3>
                <p>£<?php echo $totalRefundsGivenFormatted; ?></p>
                <br>
                <i>This figure shows the refunds for users, not donations.</i>
            </div>
            <div class="stat">
                <h3><i class="fas fa-donate"></i> Total Donations</h3>
                <p><?php echo $totalDonationsCount; ?></p>
            </div>
            <div class="stat">
                <h3><i class="fas fa-hand-holding-usd"></i> Total Donations Received</h3>
                <p>£<?php echo $totalDonationsAmountFormatted; ?></p>
                <br>
                <i>Refund amount not counted.</i>
            </div>
            <div class="stat">
                <h3><i class="fas fa-hand-holding-heart"></i> Average Donation Value</h3>
                <p>£<?php echo $averageDonationValueFormatted; ?></p>
            </div>
            <div class="stat">
                <h3><i class="fas fa-hand-holding-heart"></i> Refunded Donations</h3>
                <p>£<?php echo $totalRefundedDonationsFormatted; ?></p>
            </div>
            <div class="stat">
                <h3><i class="fas fa-coins"></i> Grand Total Received</h3>
                <p>£<?php echo $grandTotalReceivedFormatted; ?></p>
                <br>
                <i>All refunds are taken into account here. This figure is the complete balance.</i>
            </div>
        </section>

        <section id="statistics-charts" class="dashboard-section">
    <h2>Statistics Charts</h2>
    <i>Contact the dev to add more charts</i>
    <br><br>
    <div class="chart-container">
        <canvas id="transactionValueChart"></canvas>
        <canvas id="donationsChart"></canvas>
        <!--<canvas id="totalReceivedChart"></canvas>-->
        <canvas id="transactionCountChart"></canvas>
    </div>
</section>

<section id="search-form" class="dashboard-section">
    <h2>Search Database Users</h2>
    <form id="user-search-form">
        <input type="text" id="user-search" name="search" placeholder="Search users by name or email..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
    </form>
    <p>To reset the search, please click Reset search below.</p>
    <a class="reset-a-search" href="dashboard.php">Reset search</a>
</section>

        <section id="user-table" class="dashboard-section">
            <h2>Users</h2>
            <div id="column-filter">
    <div id="column-checkboxes">
        <label><input type="checkbox" value="name" checked> Name</label>
        <label><input type="checkbox" value="email" checked> Email</label>
        <label><input type="checkbox" value="stripe_customer_id" checked> Stripe Customer ID</label>
        <label><input type="checkbox" value="stripe_payment_intent_id" checked> Payment Intent ID</label>
        <label><input type="checkbox" value="description" checked> Description</label>
        <label><input type="checkbox" value="payment_status" checked> Payment Status</label>
        <label><input type="checkbox" value="created_at"> Created At</label>
        <label><input type="checkbox" value="updated_at"> Updated At</label>
    </div>
</div>
            <table class="sortable">
                <thead>
                    <tr>
                        <th class="sortable-header" data-sort="name">Name ↕</th>
                        <th class="sortable-header" data-sort="email">Email ↕</th>
                        <th class="sortable-header" data-sort="stripe_customer_id">Stripe Customer ID ↕</th>
                        <th class="sortable-header" data-sort="stripe_payment_intent_id">Payment Intent ID ↕</th>
                        <th class="sortable-header" data-sort="description">Description ↕</th>
                        <th class="sortable-header" data-sort="payment_status">Payment Status ↕</th>
                        <th class="sortable-header" data-sort="created_at">Created At ↕</th>
                        <th class="sortable-header" data-sort="updated_at">Updated At ↕</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>

                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="pagination">

            </div>
        </section>

        <?php if ($_SESSION['can_view_stripe']): ?>
            <section id="stripe-transactions" class="dashboard-section">
                <h2>Stripe Transactions</h2>
                <p>Please note that the payment status below only shows if the initial payment succeeded, so if they were refunded recently, please check the database above for that.</p>
                <form id="stripe-search-form">
                    <input type="text" id="stripe-search" placeholder="Search Stripe transactions...">
                </form>
                <div id="transactions-container">
                    <!-- Transactions will be loaded here dynamically -->
                </div>
                <div class="pagination">
                    <button id="prev-page" style="display: none;">Previous</button>
                    <span id="page-info" style="display: none;">Page <span id="current-page">1</span> of&nbsp;<span id="total-pages">1</span></span>
                    <button id="next-page" style="display: none;">Next</button>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($_SESSION['can_send_emails']): ?>
            <section id="email-form" class="dashboard-section">
                <h2>Send Email</h2>
                <form method="POST">
                    <label for="email_template">Select Template:</label>
                    <select id="email_template" name="email_template" class="styled-dropdown" onchange="fillEmailFields()">
                        <option value="">--Select a Template--</option>
                        <?php foreach ($emailTemplates as $key => $template): ?>
                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($template['subject']); ?></option>
                        <?php endforeach; ?>
                    </select><br>
                    <label for="email_from">From:</label>
                    <input type="text" id="email_from" name="email_from" required>
                    <label for="email_recipients">To (comma-separated emails):</label>
                    <input type="text" id="email_recipients" name="email_recipients" required>
                    <label for="email_subject">Subject:</label>
                    <input type="text" id="email_subject" name="email_subject" required>
                    <label for="email_message">Message:</label>
                    <textarea id="email_message" name="email_message" required></textarea>
                    <button type="submit" name="send_email">Send Email</button>
                </form>
                <button onclick="fillEmailRecipients()">Fill Recipient Addresses from Database</button>
            </section>
        <?php endif; ?>

        <section id="donations-table" class="dashboard-section">
            <h2 class="section-title">Donations</h2>
            <table class="donations-table sortable">
                <thead>
                    <tr>
                        <th class="sortable-header" data-sort="payment-intent">Payment Intent ID ↕</th>
                        <th class="sortable-header" data-sort="customer-id">Customer ID ↕</th>
                        <th class="sortable-header" data-sort="amount">Amount ↕</th>
                        <th class="sortable-header" data-sort="comment">Comment ↕</th>
                        <th class="sortable-header" data-sort="refund-status">Refund Status ↕</th>
                        <th class="sortable-header" data-sort="payment-date">Payment Made ↕</th>
                        <th class="actions-header">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $donation): ?>
                        <tr class="donation-row <?php echo $donation['refund_status'] === 'refunded' ? 'refunded-row' : ''; ?>">
                            <td class="payment-intent"><?php echo htmlspecialchars($donation['stripe_payment_intent_id']); ?></td>
                            <td class="customer-id"><?php echo htmlspecialchars($donation['stripe_customer_id']); ?></td>
                            <td class="amount">£<?php echo number_format($donation['amount'] / 100, 2); ?></td>
                            <td class="comment"><?php echo htmlspecialchars($donation['comment']); ?></td>
                            <td class="refund-status"><?php echo htmlspecialchars($donation['refund_status']); ?></td>
                            <td class="payment-date"><?php echo htmlspecialchars($donation['payment_made']); ?></td>
                            <td class="actions">
                                <?php if ($_SESSION['can_view_stripe'] && $donation['refund_status'] !== 'refunded'): ?>
                                    <button class="refund-button" onclick="confirmDonationRefund('<?php echo htmlspecialchars($donation['stripe_payment_intent_id']); ?>')">Refund</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="pagination">
                <form method="GET" class="pagination-form">
                    <?php if ($donationsPage > 1): ?>
                        <button type="submit" name="donations_page" value="<?php echo $donationsPage - 1; ?>" class="pagination-button prev-button">Previous</button>
                    <?php endif; ?>
                    <span class="page-info">Page <?php echo $donationsPage; ?> of <?php echo $totalDonationsPages; ?></span>
                    <?php if ($donationsPage < $totalDonationsPages): ?>
                        <button type="submit" name="donations_page" value="<?php echo $donationsPage + 1; ?>" class="pagination-button next-button">Next</button>
                    <?php endif; ?>
                </form>
            </div>
        </section>
    </div>

    <script>
    var stripeDetails = <?php echo json_encode($stripeDetails); ?>;
    var emailTemplates = <?php echo json_encode($emailTemplates); ?>;
    </script>



<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const transactionValueCtx = document.getElementById('transactionValueChart').getContext('2d');
    new Chart(transactionValueCtx, {
        type: 'doughnut',
        data: {
            labels: ['Grand total received', 'Remaining to £100 target'],
            datasets: [{
                data: [<?php echo $grandTotalReceivedFormatted; ?>, <?php echo 100 - ($grandTotalReceivedFormatted); ?>],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(200, 200, 200, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Total amount gained out of target (£)'
                }
            }
        }
    });

    const donationsCtx = document.getElementById('donationsChart').getContext('2d');
    new Chart(donationsCtx, {
        type: 'bar',
        data: {
            labels: ['Total Received', 'Average', 'Refunded', "Grand Total"],
            datasets: [{
                label: 'Amount (£)',
                data: [
                    <?php echo $totalDonationsAmount / 100; ?>,
                    <?php echo $averageDonationValue / 100; ?>,
                    <?php echo $totalRefundedDonations / 100; ?>,
                    <?php echo $totalDonationsAmount / 100; ?>
                ],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(169, 169, 176, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Donations Overview'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount (£)'
                    }
                }
            }
        }
    });


    const transactionCountCtx = document.getElementById('transactionCountChart').getContext('2d');
    new Chart(transactionCountCtx, {
        type: 'bar',
        data: {
            labels: ['Total Transactions', 'Total Donations'],
            datasets: [{
                label: 'Count',
                data: [<?php echo $totalTransactions; ?>, <?php echo $totalDonationsCount; ?>],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Transaction and Donation Counts'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Count'
                    }
                }
            }
        }
    });
});
</script>
</body>
</html>