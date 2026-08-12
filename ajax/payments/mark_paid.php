<?php
// ajax/payments/mark_paid.php - Mark Payment Installment as Paid
require_once __DIR__ . '/../../includes/bootstrap.php';
RBAC::require('payments.manage');

$input = json_decode(file_get_contents('php://input'), true);
$paymentId = (int)($input['payment_id'] ?? 0);
$reference = trim($input['reference'] ?? '');

if ($paymentId <= 0) {
    sendJSONResponse(false, 'Invalid payment ID.', [], 400);
}

try {
    PaymentScheduleEngine::markPaid($paymentId, $reference);
    sendJSONResponse(true, 'Payment marked as Paid.');
} catch (Exception $e) {
    sendJSONResponse(false, $e->getMessage(), [], 400);
}
