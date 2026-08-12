<?php
// includes/functions.php
// Global Helper Utilities, Sanitization & Formatting

require_once __DIR__ . '/../config/config.php';

/**
 * Sanitize text output for safe HTML rendering
 */
function sanitize(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency amounts with proper currency symbol and decimals
 */
function formatCurrency(float $amount, string $currency = 'USD', int $decimals = 2): string {
    $symbols = [
        'USD' => '$',
        'NGN' => '₦',
        'EUR' => '€',
        'GBP' => '£'
    ];
    $symbol = $symbols[$currency] ?? ($currency . ' ');
    return $symbol . number_format($amount, $decimals);
}

/**
 * Format dates into clean human readable representation
 */
function formatDate(?string $dateString, string $format = 'M d, Y'): string {
    if (!$dateString || $dateString === '0000-00-00') return 'N/A';
    return date($format, strtotime($dateString));
}

/**
 * Calculate days remaining between today and target date
 */
function calculateDaysRemaining(string $targetDate): int {
    $today = new DateTime('today');
    $target = new DateTime($targetDate);
    $diff = $today->diff($target);
    return $diff->invert ? -$diff->days : $diff->days;
}

/**
 * Return visual badge HTML string based on status
 */
function renderStatusBadge(string $status): string {
    $status = strtolower($status);
    $badges = [
        'active'     => '<span class="badge badge-success"><i data-lucide="check-circle" class="badge-icon"></i> Active</span>',
        'approved'   => '<span class="badge badge-success"><i data-lucide="check" class="badge-icon"></i> Approved</span>',
        'paid'       => '<span class="badge badge-success"><i data-lucide="credit-card" class="badge-icon"></i> Paid</span>',
        'renewed'    => '<span class="badge badge-success"><i data-lucide="rotate-cw" class="badge-icon"></i> Renewed</span>',
        'expiring'   => '<span class="badge badge-warning"><i data-lucide="alert-triangle" class="badge-icon"></i> Expiring Soon</span>',
        'pending'    => '<span class="badge badge-warning"><i data-lucide="clock" class="badge-icon"></i> Pending</span>',
        'due'        => '<span class="badge badge-warning"><i data-lucide="calendar" class="badge-icon"></i> Due</span>',
        'queued'     => '<span class="badge badge-info"><i data-lucide="layers" class="badge-icon"></i> Queued</span>',
        'overdue'    => '<span class="badge badge-danger"><i data-lucide="alert-octagon" class="badge-icon"></i> Overdue</span>',
        'lapsed'     => '<span class="badge badge-danger"><i data-lucide="x-circle" class="badge-icon"></i> Lapsed</span>',
        'rejected'   => '<span class="badge badge-danger"><i data-lucide="x" class="badge-icon"></i> Rejected</span>',
        'cancelled'  => '<span class="badge badge-secondary"><i data-lucide="minus-circle" class="badge-icon"></i> Cancelled</span>',
        'draft'      => '<span class="badge badge-secondary"><i data-lucide="file-text" class="badge-icon"></i> Draft</span>'
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Encode integer database ID to obfuscated URL token
 */
function encodeId($id): string {
    $id = (int)$id;
    if ($id <= 0) return '';
    $scrambled = ($id * 15823) + 904812;
    $hex = dechex($scrambled);
    $prefix = substr(md5('renewly_hash_key_' . $id), 0, 8);
    return $prefix . $hex;
}

/**
 * Decode obfuscated URL token back to integer database ID
 */
function decodeId($token): int {
    if (empty($token)) return 0;
    if (is_numeric($token)) return (int)$token;
    
    if (strlen($token) >= 9) {
        $hex = substr($token, 8);
        if (ctype_xdigit($hex)) {
            $scrambled = hexdec($hex);
            $id = ($scrambled - 904812) / 15823;
            if (is_numeric($id) && $id > 0 && floor($id) == $id) {
                return (int)$id;
            }
        }
    }
    return 0;
}

/**
 * Helper to build clean obfuscated contract view URL
 */
function getContractUrl($contractOrId): string {
    $id = is_array($contractOrId) ? ($contractOrId['id'] ?? 0) : (int)$contractOrId;
    return APP_URL . '/contracts/' . encodeId($id);
}

/**
 * Standard JSON Response Helper for AJAX Endpoints
 */
function sendJSONResponse(bool $success, string $message = '', array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}
