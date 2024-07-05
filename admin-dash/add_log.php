<?php
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

$timestamp = $data['timestamp'];
$action = $data['action'];
$details = $data['details'];

$stmt = $conn->prepare("INSERT INTO activity_logs (timestamp, action, details) VALUES (?, ?, ?)");
$stmt->execute([$timestamp, $action, $details]);

echo json_encode(['success' => true]);

// not done