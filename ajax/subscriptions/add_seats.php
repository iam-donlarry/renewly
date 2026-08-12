<?php
// ajax/subscriptions/add_seats.php - Commit Immediate Seat Addition
require_once __DIR__ . '/../../includes/bootstrap.php';
RBAC::require('subscriptions.adjust');

$input = json_decode(file_get_contents('php://input'), true);
$itemId = (int)($input['item_id'] ?? 0);
$newQty = (int)($input['new_quantity'] ?? 0);
$reason = trim($input['reason'] ?? '');

if ($itemId <= 0 || $newQty <= 0) {
    sendJSONResponse(false, 'Invalid payload parameters.', [], 400);
}

try {
    ProrationEngine::commitAddition($itemId, $newQty, $reason);
    sendJSONResponse(true, 'Seat addition committed and prorated charge generated.');
} catch (Exception $e) {
    sendJSONResponse(false, $e->getMessage(), [], 400);
}
