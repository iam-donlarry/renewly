<?php
require_once 'database/db.php';
$stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($logs);
