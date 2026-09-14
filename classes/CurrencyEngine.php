<?php
// classes/CurrencyEngine.php - Enterprise Dual-Currency Architecture (USD & NGN)

class CurrencyEngine {
    private static ?float $cachedGlobalRate = null;

    /**
     * Fetch baseline global exchange rate (1 USD = X NGN) from app_settings
     */
    public static function getGlobalRate(): float {
        if (self::$cachedGlobalRate !== null) {
            return self::$cachedGlobalRate;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'global_exchange_rate' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();

        self::$cachedGlobalRate = ($val && (float)$val > 0) ? (float)$val : 1550.00;
        return self::$cachedGlobalRate;
    }

    /**
     * Convert an amount between currencies
     */
    public static function convert(float $amount, string $from, string $to, ?float $rate = null): float {
        $from = strtoupper(trim($from));
        $to   = strtoupper(trim($to));

        if ($from === $to || $amount == 0) {
            return round($amount, 4);
        }

        $rate = ($rate && $rate > 0) ? $rate : self::getGlobalRate();

        if ($from === 'USD' && $to === 'NGN') {
            return round($amount * $rate, 4);
        }

        if ($from === 'NGN' && $to === 'USD') {
            return $rate > 0 ? round($amount / $rate, 4) : 0.0;
        }

        // For other currencies (EUR, GBP) return as-is unless specific cross-rate added
        return round($amount, 4);
    }

    /**
     * Aggregate multi-currency rows into segregated totals and unified baseline equivalents
     */
    public static function aggregate(array $items, string $amountKey = 'amount', string $currencyKey = 'currency', ?string $rateKey = 'exchange_rate'): array {
        $byCurrency = [
            'USD' => 0.0,
            'NGN' => 0.0
        ];
        $unifiedUsd = 0.0;
        $globalRate = self::getGlobalRate();

        foreach ($items as $item) {
            $amt = (float)($item[$amountKey] ?? 0.0);
            $curr = strtoupper(trim($item[$currencyKey] ?? 'USD'));
            $itemRate = (!empty($item[$rateKey]) && (float)$item[$rateKey] > 0) ? (float)$item[$rateKey] : $globalRate;

            if (!isset($byCurrency[$curr])) {
                $byCurrency[$curr] = 0.0;
            }
            $byCurrency[$curr] += $amt;

            // Convert to base USD for unified accounting
            if ($curr === 'USD') {
                $unifiedUsd += $amt;
            } else {
                $unifiedUsd += self::convert($amt, $curr, 'USD', $itemRate);
            }
        }

        $unifiedNgn = round($unifiedUsd * $globalRate, 2);

        $hasUsd = ($byCurrency['USD'] ?? 0.0) > 0;
        $hasNgn = ($byCurrency['NGN'] ?? 0.0) > 0;
        $nonZeroCurrencies = array_filter($byCurrency, fn($v) => $v > 0);

        return [
            'currencies'   => $byCurrency,
            'unified_usd'  => round($unifiedUsd, 2),
            'unified_ngn'  => round($unifiedNgn, 2),
            'has_usd'      => $hasUsd,
            'has_ngn'      => $hasNgn,
            'has_multiple' => count($nonZeroCurrencies) > 1,
            'global_rate'  => $globalRate
        ];
    }

    /**
     * Render dual-currency KPI HTML display widget
     */
    public static function renderDualKpiHtml(array $aggregate, ?string $filteredCurrency = null, string $colorClass = 'text-dark', bool $compact = false): string {
        $filteredCurrency = !empty($filteredCurrency) ? strtoupper(trim($filteredCurrency)) : null;

        // If explicitly filtered to a single currency (e.g. 'USD' or 'NGN')
        if ($filteredCurrency && $filteredCurrency !== 'ALL') {
            $amt = $aggregate['currencies'][$filteredCurrency] ?? 0.0;
            return '<div class="kpi-value ' . $colorClass . '">' . formatCurrency($amt, $filteredCurrency) . '</div>';
        }

        $hasMultiple = $aggregate['has_multiple'] ?? false;
        $usdVal = $aggregate['currencies']['USD'] ?? 0.0;
        $ngnVal = $aggregate['currencies']['NGN'] ?? 0.0;

        // If only NGN exists
        if (!$hasMultiple && $ngnVal > 0 && $usdVal == 0) {
            $html = '<div class="kpi-value ' . $colorClass . '">' . formatCurrency($ngnVal, 'NGN') . '</div>';
            if (!$compact) {
                $html .= '<div class="text-xs text-muted mt-1 font-mono">≈ ' . formatCurrency($aggregate['unified_usd'], 'USD') . ' USD</div>';
            }
            return $html;
        }

        // If only USD exists (or empty)
        if (!$hasMultiple && $ngnVal == 0) {
            $html = '<div class="kpi-value ' . $colorClass . '">' . formatCurrency($usdVal, 'USD') . '</div>';
            if (!$compact && $usdVal > 0) {
                $html .= '<div class="text-xs text-muted mt-1 font-mono">≈ ' . formatCurrency($aggregate['unified_ngn'], 'NGN', 0) . ' NGN</div>';
            }
            return $html;
        }

        // Both USD and NGN are present in the dataset!
        if ($compact) {
            return '
                <div class="d-flex align-items-baseline gap-2 flex-wrap">
                    <span class="fw-bold fs-6 ' . $colorClass . '">' . formatCurrency($usdVal, 'USD') . '</span>
                    <span class="text-muted text-xs">&bull;</span>
                    <span class="fw-bold fs-6 ' . $colorClass . '">' . formatCurrency($ngnVal, 'NGN') . '</span>
                </div>
            ';
        }

        return '
            <div class="d-flex flex-column">
                <div class="d-flex align-items-baseline gap-2 flex-wrap mb-1">
                    <span class="h4 font-bold mb-0 ' . $colorClass . '">' . formatCurrency($usdVal, 'USD') . '</span>
                    <span class="text-secondary text-sm font-semibold">+</span>
                    <span class="h4 font-bold mb-0 ' . $colorClass . '">' . formatCurrency($ngnVal, 'NGN') . '</span>
                </div>
                <div class="text-xs text-muted font-mono d-flex align-items-center gap-1">
                    <span class="badge bg-light text-secondary border px-1.5 py-0.5">Unified</span>
                    <span>≈ ' . formatCurrency($aggregate['unified_usd'], 'USD') . '</span>
                    <span class="text-muted">(≈ ' . formatCurrency($aggregate['unified_ngn'], 'NGN', 0) . ')</span>
                </div>
            </div>
        ';
    }
}
