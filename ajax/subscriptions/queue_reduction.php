<?php
// ajax/subscriptions/queue_reduction.php - Queue Seat Reduction for Renewal
require_once __DIR__ . '/../../includes/bootstrap.php';
RBAC::require('subscriptions.adjust');

$input = json_decode(file_get_contents('php://input'), true);
$itemId = (int)($input['item_id'] ?? 0);
$desiredQty = (int)($input['desired_quantity'] ?? 0);
$reason = trim($input['reason'] ?? '');

if ($itemId <= 0 || $desiredQty <= 0) {
    sendJSONResponse(false, 'Invalid payload parameters.', [], 400);
}

try {
    ProrationEngine::queueReduction($itemId, $desiredQty, $reason);
    sendJSONResponse(true, 'Seat reduction queued for renewal date.');
} catch (Exception $e) {
    sendJSONResponse(false, $e->getMessage(), [], 400);
}
