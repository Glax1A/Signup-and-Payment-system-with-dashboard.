<?php
require_once 'config.php';
checkPermission('can_send_emails');

$stmt = $conn->prepare("SELECT email FROM users");
$stmt->execute();
$emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($emails);
?>
