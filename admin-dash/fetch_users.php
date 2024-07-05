<?php
require_once 'config.php';
require_once '../stripe-php-15.0.0/init.php';
checkPermission('can_view_db');

$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_STRING) ?? 'name';
$order = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_STRING) ?? 'ASC';
$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT) ?? 1;
$limit = 5;
$offset = ($page - 1) * $limit;

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
$stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUsersStmt = $conn->query("SELECT FOUND_ROWS()");
$totalUsers = $totalUsersStmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

$response = [
    'users' => $users,
    'totalPages' => $totalPages,
    'currentPage' => $page
];

header('Content-Type: application/json');
echo json_encode($response);