<?php
// scratch/test_workflow.php - Integration test for contract creation, proration, and payment persistence
require_once __DIR__ . '/../includes/bootstrap.php';

echo "Testing Renewly Core Workflow...\n";

try {
    // 1. Create a Test Contract for Baye Business Solutions
    $contractData = [
        'client_id'          => 1,
        'account_manager_id' => 2,
        'start_date'         => date('Y-m-d'),
        'expiry_date'        => date('Y-m-d', strtotime('+1 year')),
        'billing_cycle'      => 'monthly',
        'currency'           => 'USD',
        'exchange_rate'      => 1550.00,
        'notes'              => 'Integration Test Contract'
    ];

    $items = [
        [
            'product_id'    => 1, // Microsoft 365 Business Premium
            'pricing_model' => 'per_seat',
            'quantity'      => 50,
            'unit_price'    => 22.00
        ],
        [
            'product_id'    => 4, // Adobe CC All Apps
            'pricing_model' => 'per_seat',
            'quantity'      => 5,
            'unit_price'    => 55.00
        ]
    ];

    $contractId = ContractEngine::createContract($contractData, $items);
    echo "Contract created successfully! ID: {$contractId}\n";

    // 2. Fetch Details
    $contract = ContractEngine::getById($contractId);
    echo "Total Contract Value: $" . number_format($contract['total_contract_value'], 2) . "\n";
    echo "Line items count: " . count($contract['items']) . "\n";
    echo "Installments count: " . count($contract['payments']) . "\n";

    // 3. Test Mid-Term Seat Addition (+10 seats on M365)
    $m365Item = $contract['items'][0];
    echo "Testing mid-term addition of +10 seats to Item ID {$m365Item['id']}...\n";
    ProrationEngine::commitAddition((int)$m365Item['id'], 60, "Testing expansion");
    echo "Addition committed!\n";

    // 4. Test Mid-Term Seat Reduction Queuing (-5 seats on Adobe)
    $adobeItem = $contract['items'][1];
    echo "Testing seat reduction queuing (-2 seats) on Item ID {$adobeItem['id']}...\n";
    ProrationEngine::queueReduction((int)$adobeItem['id'], 3, "Testing reduction queuing");
    echo "Reduction queued for renewal date!\n";

    // 5. Test Payment Mark Paid
    $firstPayment = $contract['payments'][0];
    PaymentScheduleEngine::markPaid((int)$firstPayment['id'], 'REF-TEST-999');
    echo "First payment marked as Paid!\n";

    echo "ALL WORKFLOW TESTS PASSED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "TEST FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
