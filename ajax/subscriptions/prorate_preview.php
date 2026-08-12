<?php
// ajax/subscriptions/prorate_preview.php - Async Proration Preview Endpoint
require_once __DIR__ . '/../../includes/bootstrap.php';
RBAC::require('subscriptions.adjust');

$input = json_decode(file_get_contents('php://input'), true);
$itemId = (int)($input['item_id'] ?? 0);
$newQty = (int)($input['new_quantity'] ?? 0);

if ($itemId <= 0 || $newQty <= 0) {
    sendJSONResponse(false, 'Invalid payload parameters.', [], 400);
}

try {
    $preview = ProrationEngine::previewAddition($itemId, $newQty);
    sendJSONResponse(true, 'Proration preview calculated successfully.', $preview);
} catch (Exception $e) {
    sendJSONResponse(false, $e->getMessage(), [], 400);
}
