<?php
require_once 'config.php';

$stmt = $conn->query("SELECT * FROM activity_logs ORDER BY timestamp DESC LIMIT 100");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($logs);

// Not done