<?php
require_once 'database/db.php';
$id = 6;

echo "--- Subscription ---\n";
$sub = $pdo->query("SELECT * FROM subscriptions WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
print_r($sub);

echo "\n--- Items ---\n";
$items = $pdo->query("SELECT * FROM subscription_items WHERE subscription_id=$id")->fetchAll(PDO::FETCH_ASSOC);
print_r($items);

echo "\n--- Payments ---\n";
$pays = $pdo->query("SELECT * FROM subscription_payments WHERE subscription_id=$id ORDER BY due_date")->fetchAll(PDO::FETCH_ASSOC);
print_r($pays);
