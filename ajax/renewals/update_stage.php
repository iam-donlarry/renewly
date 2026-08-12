<?php
// ajax/renewals/update_stage.php - Update Renewal Stage Endpoint
require_once __DIR__ . '/../../includes/bootstrap.php';
RBAC::require('renewals.manage');

$input = json_decode(file_get_contents('php://input'), true);
$renewalId = (int)($input['renewal_id'] ?? 0);
$stage = trim($input['stage'] ?? '');

if ($renewalId <= 0 || empty($stage)) {
    sendJSONResponse(false, 'Invalid payload parameters.', [], 400);
}

try {
    RenewalEngine::updateStage($renewalId, $stage);
    sendJSONResponse(true, 'Renewal stage updated.');
} catch (Exception $e) {
    sendJSONResponse(false, $e->getMessage(), [], 400);
}
