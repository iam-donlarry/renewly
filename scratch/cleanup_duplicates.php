<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = getDB();

// 1. Remove duplicate payment_schedules rows with identical contract_id, installment_number, amount
$pdo->exec("
    DELETE ps1 FROM payment_schedules ps1
    INNER JOIN payment_schedules ps2 
    ON ps1.contract_id = ps2.contract_id 
    AND ps1.installment_number = ps2.installment_number 
    AND ps1.id > ps2.id
");

// 2. Remove duplicate renewals rows for identical contract_id
$pdo->exec("
    DELETE r1 FROM renewals r1
    INNER JOIN renewals r2
    ON r1.contract_id = r2.contract_id
    AND r1.id > r2.id
");

echo "Database cleanup completed cleanly.";
