<?php
/**
 * Mikhmon V3 Reseller System - Voucher Model
 * 
 * Handles voucher profile management, voucher generation via MikroTik API,
 * sales tracking, batch operations, and pricing.
 */

if (substr($_SERVER["REQUEST_URI"], -16) == "VoucherModel.php") {
    header("Location:../");
    exit;
}

require_once(__DIR__ . '/database.php');
require_once(__DIR__ . '/ResellerModel.php');

class VoucherModel
{
    // ========================================================================
    // VOUCHER PROFILE MANAGEMENT
    // ========================================================================

    /**
     * Create or update a voucher profile (admin defines pricing for a hotspot profile).
     */
    public static function saveProfile(array $data): int
    {
        $existing = ResellerDB::fetchOne(
            "SELECT id FROM voucher_profiles WHERE profile_name = ? AND session_name = ?",
            [$data['profile_name'], $data['session_name']]
        );

        $fields = [
            'profile_name', 'session_name', 'display_name', 'base_price',
            'reseller_price', 'sell_price', 'validity', 'speed_limit',
            'data_limit', 'time_limit', 'shared_users', 'is_active', 'sort_order'
        ];

        $saveData = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $saveData[$f] = $data[$f];
            }
        }

        if ($existing) {
            ResellerDB::updateArray('voucher_profiles', $saveData, 'id = ?', [$existing['id']]);
            return $existing['id'];
        } else {
            return ResellerDB::insertArray('voucher_profiles', $saveData);
        }
    }

    /**
     * Get a voucher profile by ID.
     */
    public static function getProfile(int $id): ?array
    {
        return ResellerDB::fetchOne("SELECT * FROM voucher_profiles WHERE id = ?", [$id]);
    }

    /**
     * Get all voucher profiles, optionally filtered.
     */
    public static function getProfiles(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (isset($filters['session_name'])) {
            $where[] = "session_name = ?";
            $params[] = $filters['session_name'];
        }
        if (isset($filters['is_active'])) {
            $where[] = "is_active = ?";
            $params[] = $filters['is_active'];
        }

        return ResellerDB::fetchAll(
            "SELECT * FROM voucher_profiles WHERE " . implode(' AND ', $where) . " ORDER BY sort_order, profile_name",
            $params
        );
    }

    /**
     * Get profiles available to a specific reseller (considering permissions + pricing).
     */
    public static function getProfilesForReseller(int $resellerId, string $sessionName): array
    {
        $reseller = ResellerModel::getById($resellerId);
        if (!$reseller) return [];

        // Check session access
        if (!ResellerModel::canAccessSession($resellerId, $sessionName)) return [];

        // Get all active profiles for this session
        $profiles = self::getProfiles([
            'session_name' => $sessionName,
            'is_active'    => 1
        ]);

        // Filter by allowed profiles and apply pricing
        $result = [];
        foreach ($profiles as $profile) {
            if (!ResellerModel::canSellProfile($resellerId, $profile['profile_name'])) {
                continue;
            }

            // Check per-reseller pricing override
            $pricing = ResellerDB::fetchOne(
                "SELECT * FROM reseller_profile_pricing
                 WHERE reseller_id = ? AND profile_id = ?",
                [$resellerId, $profile['id']]
            );

            if ($pricing && !$pricing['is_allowed']) continue;

            // Calculate effective prices
            if ($pricing && $pricing['buy_price'] > 0) {
                $profile['effective_buy_price'] = $pricing['buy_price'];
            } elseif ($reseller['discount_pct'] > 0) {
                $profile['effective_buy_price'] = (int) round(
                    $profile['reseller_price'] * (1 - $reseller['discount_pct'] / 100)
                );
            } else {
                $profile['effective_buy_price'] = $profile['reseller_price'];
            }

            if ($pricing && $pricing['sell_price'] > 0) {
                $profile['effective_sell_price'] = $pricing['sell_price'];
            } elseif ($reseller['markup_pct'] > 0) {
                $profile['effective_sell_price'] = (int) round(
                    $profile['sell_price'] * (1 + $reseller['markup_pct'] / 100)
                );
            } else {
                $profile['effective_sell_price'] = $profile['sell_price'];
            }

            $profile['effective_profit'] = $profile['effective_sell_price'] - $profile['effective_buy_price'];

            $result[] = $profile;
        }

        return $result;
    }

    /**
     * Delete a voucher profile.
     */
    public static function deleteProfile(int $id): bool
    {
        return ResellerDB::execute("DELETE FROM voucher_profiles WHERE id = ?", [$id]) > 0;
    }

    /**
     * Set per-reseller pricing for a profile.
     */
    public static function setResellerPricing(int $resellerId, int $profileId, array $data): void
    {
        $existing = ResellerDB::fetchOne(
            "SELECT id FROM reseller_profile_pricing WHERE reseller_id = ? AND profile_id = ?",
            [$resellerId, $profileId]
        );

        if ($existing) {
            ResellerDB::updateArray('reseller_profile_pricing', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['reseller_id'] = $resellerId;
            $data['profile_id']  = $profileId;
            ResellerDB::insertArray('reseller_profile_pricing', $data);
        }
    }

    // ========================================================================
    // VOUCHER GENERATION (Creates users on MikroTik)
    // ========================================================================

    /**
     * Generate a single voucher for a reseller.
     * Creates the hotspot user on MikroTik and records the sale.
     *
     * @param int    $resellerId  Reseller performing the sale
     * @param int    $profileId   Voucher profile to use
     * @param object $API         RouterosAPI instance (already connected)
     * @param array  $options     Optional overrides: username, password, comment, server, customer_*
     * @return array  The created voucher sale record
     */
    public static function generateVoucher(
        int $resellerId,
        int $profileId,
        $API,
        array $options = []
    ): array {
        // Validate reseller
        $reseller = ResellerModel::getById($resellerId);
        if (!$reseller || $reseller['status'] !== 'active') {
            throw new Exception("Reseller account is not active.");
        }

        // Get the profile
        $profile = self::getProfile($profileId);
        if (!$profile || !$profile['is_active']) {
            throw new Exception("Voucher profile is not available.");
        }

        // Check session access
        if (!ResellerModel::canAccessSession($resellerId, $profile['session_name'])) {
            throw new Exception("Access denied to this router session.");
        }

        // Check profile access
        if (!ResellerModel::canSellProfile($resellerId, $profile['profile_name'])) {
            throw new Exception("Not authorized to sell this profile.");
        }

        // Check daily/monthly limits
        if (!ResellerModel::checkDailyLimit($resellerId)) {
            throw new Exception("Daily voucher limit reached.");
        }
        if (!ResellerModel::checkMonthlyLimit($resellerId)) {
            throw new Exception("Monthly voucher limit reached.");
        }

        // Calculate pricing
        $profiles = self::getProfilesForReseller($resellerId, $profile['session_name']);
        $effectiveProfile = null;
        foreach ($profiles as $p) {
            if ($p['id'] == $profileId) {
                $effectiveProfile = $p;
                break;
            }
        }
        if (!$effectiveProfile) {
            throw new Exception("Profile not available for this reseller.");
        }

        $buyPrice  = $effectiveProfile['effective_buy_price'];
        $sellPrice = $effectiveProfile['effective_sell_price'];
        $profit    = $sellPrice - $buyPrice;

        // Generate username/password if not provided
        $username = $options['username'] ?? self::generateUsername($options['prefix'] ?? '');
        $password = $options['password'] ?? self::generatePassword();
        $comment  = $options['comment']  ?? 'reseller-' . $reseller['username'];
        $server   = $options['server']   ?? 'all';

        ResellerDB::beginTransaction();
        try {
            // Check balance
            if (!ResellerModel::deductForPurchase($resellerId, $buyPrice, 0, "Voucher: {$username} [{$profile['profile_name']}]")) {
                throw new Exception("Insufficient balance. Required: {$buyPrice}, Available: " . ($reseller['balance'] + $reseller['credit_limit']));
            }

            // Create hotspot user on MikroTik
            $mikrotikParams = [
                'name'     => $username,
                'password' => $password,
                'profile'  => $profile['profile_name'],
                'comment'  => $comment,
            ];
            if ($server !== 'all') {
                $mikrotikParams['server'] = $server;
            }

            $result = $API->comm("/ip/hotspot/user/add", $mikrotikParams);

            if (isset($result['!trap'])) {
                throw new Exception("MikroTik error: " . ($result['!trap'][0]['message'] ?? 'Unknown error'));
            }

            // Record the sale
            $saleId = ResellerDB::insertArray('voucher_sales', [
                'reseller_id'      => $resellerId,
                'profile_id'       => $profileId,
                'session_name'     => $profile['session_name'],
                'hotspot_username' => $username,
                'hotspot_password' => $password,
                'profile_name'     => $profile['profile_name'],
                'buy_price'        => $buyPrice,
                'sell_price'       => $sellPrice,
                'profit'           => $profit,
                'comment'          => $comment,
                'server'           => $server,
                'status'           => 'created',
                'customer_name'    => $options['customer_name'] ?? '',
                'customer_phone'   => $options['customer_phone'] ?? '',
            ]);

            // Update the balance transaction reference
            ResellerDB::execute(
                "UPDATE balance_transactions SET reference_id = ?
                 WHERE reseller_id = ? AND reference_type = 'voucher_sale' AND reference_id = 0
                 ORDER BY id DESC LIMIT 1",
                [$saleId, $resellerId]
            );

            // Process commissions for parent resellers
            self::processCommissions($resellerId, $saleId, $buyPrice);

            // Log activity
            ResellerModel::logActivity(
                $resellerId, 'generate_voucher', 'voucher_sale', $saleId,
                json_encode(['username' => $username, 'profile' => $profile['profile_name'], 'price' => $buyPrice]),
                $reseller['username']
            );

            ResellerDB::commit();

            return [
                'sale_id'   => $saleId,
                'username'  => $username,
                'password'  => $password,
                'profile'   => $profile['profile_name'],
                'buy_price' => $buyPrice,
                'sell_price'=> $sellPrice,
                'profit'    => $profit,
            ];
        } catch (Exception $e) {
            ResellerDB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate a batch of vouchers.
     */
    public static function generateBatch(
        int $resellerId,
        int $profileId,
        $API,
        int $quantity,
        array $options = []
    ): array {
        // Validate limits
        if (!ResellerModel::checkDailyLimit($resellerId, $quantity)) {
            throw new Exception("Daily voucher limit would be exceeded.");
        }
        if (!ResellerModel::checkMonthlyLimit($resellerId, $quantity)) {
            throw new Exception("Monthly voucher limit would be exceeded.");
        }

        // Get profile for pricing check
        $profile = self::getProfile($profileId);
        if (!$profile) throw new Exception("Profile not found.");

        $profiles = self::getProfilesForReseller($resellerId, $profile['session_name']);
        $effectiveProfile = null;
        foreach ($profiles as $p) {
            if ($p['id'] == $profileId) {
                $effectiveProfile = $p;
                break;
            }
        }
        if (!$effectiveProfile) throw new Exception("Profile not available.");

        $totalCost = $effectiveProfile['effective_buy_price'] * $quantity;
        $reseller = ResellerModel::getById($resellerId);
        if ($reseller['balance'] + $reseller['credit_limit'] < $totalCost) {
            throw new Exception("Insufficient balance for batch. Required: {$totalCost}");
        }

        // Create batch record
        $batchId = ResellerDB::insertArray('voucher_batches', [
            'reseller_id'  => $resellerId,
            'session_name' => $profile['session_name'],
            'profile_name' => $profile['profile_name'],
            'quantity'      => $quantity,
            'prefix'        => $options['prefix'] ?? '',
            'total_cost'    => $totalCost,
            'comment'       => $options['comment'] ?? 'reseller-' . $reseller['username'],
            'status'        => 'pending',
        ]);

        $results = [];
        $successCount = 0;
        $errors = [];

        for ($i = 0; $i < $quantity; $i++) {
            try {
                $voucherOptions = $options;
                $voucherOptions['username'] = self::generateUsername($options['prefix'] ?? '');
                $voucherOptions['password'] = self::generatePassword(
                    $options['password_length'] ?? 6
                );

                $voucher = self::generateVoucher($resellerId, $profileId, $API, $voucherOptions);

                // Link to batch
                ResellerDB::insertArray('batch_voucher_items', [
                    'batch_id' => $batchId,
                    'sale_id'  => $voucher['sale_id'],
                ]);

                $results[] = $voucher;
                $successCount++;
            } catch (Exception $e) {
                $errors[] = "Voucher " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        // Update batch status
        $status = ($successCount == $quantity) ? 'completed' :
                  (($successCount > 0) ? 'partial' : 'failed');

        ResellerDB::updateArray('voucher_batches', [
            'status'        => $status,
            'error_message' => implode('; ', $errors),
        ], 'id = ?', [$batchId]);

        return [
            'batch_id'      => $batchId,
            'total'         => $quantity,
            'success'       => $successCount,
            'failed'        => $quantity - $successCount,
            'vouchers'      => $results,
            'errors'        => $errors,
            'status'        => $status,
        ];
    }

    // ========================================================================
    // SALES MANAGEMENT
    // ========================================================================

    /**
     * Get sales for a reseller with optional filters.
     */
    public static function getSales(int $resellerId, array $filters = []): array
    {
        $where = ["vs.reseller_id = ?"];
        $params = [$resellerId];

        if (!empty($filters['session_name'])) {
            $where[] = "vs.session_name = ?";
            $params[] = $filters['session_name'];
        }
        if (!empty($filters['profile_name'])) {
            $where[] = "vs.profile_name = ?";
            $params[] = $filters['profile_name'];
        }
        if (!empty($filters['status'])) {
            $where[] = "vs.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "vs.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "vs.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $where[] = "(vs.hotspot_username LIKE ? OR vs.customer_name LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$s, $s]);
        }

        $limit  = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        return ResellerDB::fetchAll(
            "SELECT vs.*, vp.display_name AS profile_display_name
             FROM voucher_sales vs
             LEFT JOIN voucher_profiles vp ON vp.id = vs.profile_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY vs.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    /**
     * Get a single sale record.
     */
    public static function getSale(int $saleId): ?array
    {
        return ResellerDB::fetchOne("SELECT * FROM voucher_sales WHERE id = ?", [$saleId]);
    }

    /**
     * Update sale status (e.g., printed, sold, refunded).
     */
    public static function updateSaleStatus(int $saleId, string $status): bool
    {
        $extra = '';
        if ($status === 'printed') $extra = ", printed_at = datetime('now','localtime')";
        if ($status === 'sold')    $extra = ", sold_at = datetime('now','localtime')";

        return ResellerDB::execute(
            "UPDATE voucher_sales SET status = ? {$extra} WHERE id = ?",
            [$status, $saleId]
        ) > 0;
    }

    /**
     * Refund a voucher sale (removes user from MikroTik too).
     */
    public static function refundSale(int $saleId, $API = null): bool
    {
        $sale = self::getSale($saleId);
        if (!$sale || $sale['status'] === 'refunded') return false;

        ResellerDB::beginTransaction();
        try {
            // Refund balance
            ResellerModel::refundPurchase($sale['reseller_id'], $sale['buy_price'], $saleId, "Refund: {$sale['hotspot_username']}");

            // Remove from MikroTik if API provided
            if ($API) {
                $users = $API->comm("/ip/hotspot/user/print", [
                    "?name" => $sale['hotspot_username']
                ]);
                if (!empty($users[0]['.id'])) {
                    $API->comm("/ip/hotspot/user/remove", [
                        ".id" => $users[0]['.id']
                    ]);
                }
            }

            // Update status
            self::updateSaleStatus($saleId, 'refunded');

            // Reverse commissions
            ResellerDB::execute(
                "UPDATE reseller_commissions SET status = 'cancelled' WHERE sale_id = ?",
                [$saleId]
            );

            ResellerDB::commit();
            return true;
        } catch (Exception $e) {
            ResellerDB::rollBack();
            throw $e;
        }
    }

    /**
     * Get sales count for today (for a reseller).
     */
    public static function getTodaySalesCount(int $resellerId): int
    {
        return (int) ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM voucher_sales
             WHERE reseller_id = ? AND date(created_at) = date('now','localtime')",
            [$resellerId]
        );
    }

    /**
     * Get sales summary/stats for a reseller.
     */
    public static function getSalesSummary(int $resellerId, string $period = 'today'): array
    {
        switch ($period) {
            case 'today':
                $dateFilter = "date(created_at) = date('now','localtime')";
                break;
            case 'week':
                $dateFilter = "created_at >= datetime('now','localtime','-7 days')";
                break;
            case 'month':
                $dateFilter = "strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now','localtime')";
                break;
            case 'year':
                $dateFilter = "strftime('%Y', created_at) = strftime('%Y', 'now','localtime')";
                break;
            default:
                $dateFilter = "1=1";
        }

        return ResellerDB::fetchOne(
            "SELECT
                COUNT(*) AS total_vouchers,
                COALESCE(SUM(buy_price), 0) AS total_cost,
                COALESCE(SUM(sell_price), 0) AS total_revenue,
                COALESCE(SUM(profit), 0) AS total_profit,
                COUNT(CASE WHEN status = 'created' THEN 1 END) AS count_created,
                COUNT(CASE WHEN status = 'sold' THEN 1 END) AS count_sold,
                COUNT(CASE WHEN status = 'printed' THEN 1 END) AS count_printed,
                COUNT(CASE WHEN status = 'refunded' THEN 1 END) AS count_refunded
             FROM voucher_sales
             WHERE reseller_id = ? AND {$dateFilter}",
            [$resellerId]
        );
    }

    /**
     * Get batch details with its voucher items.
     */
    public static function getBatchWithItems(int $batchId): ?array
    {
        $batch = ResellerDB::fetchOne("SELECT * FROM voucher_batches WHERE id = ?", [$batchId]);
        if (!$batch) return null;

        $batch['items'] = ResellerDB::fetchAll(
            "SELECT vs.* FROM voucher_sales vs
             INNER JOIN batch_voucher_items bvi ON bvi.sale_id = vs.id
             WHERE bvi.batch_id = ?
             ORDER BY vs.id",
            [$batchId]
        );

        return $batch;
    }

    /**
     * Get all batches for a reseller.
     */
    public static function getBatches(int $resellerId, int $limit = 20): array
    {
        return ResellerDB::fetchAll(
            "SELECT vb.*, 
                    (SELECT COUNT(*) FROM batch_voucher_items WHERE batch_id = vb.id) AS item_count
             FROM voucher_batches vb
             WHERE vb.reseller_id = ?
             ORDER BY vb.created_at DESC
             LIMIT ?",
            [$resellerId, $limit]
        );
    }

    // ========================================================================
    // COMMISSION PROCESSING
    // ========================================================================

    /**
     * Process commissions up the hierarchy when a sale is made.
     */
    private static function processCommissions(int $resellerId, int $saleId, int $saleAmount): void
    {
        $ancestors = ResellerModel::getAncestors($resellerId);
        // Remove the reseller themselves from the ancestor list
        array_pop($ancestors);

        $depth = 1;
        foreach (array_reverse($ancestors) as $ancestor) {
            // Find commission rule
            $rule = ResellerDB::fetchOne(
                "SELECT rate_pct FROM commission_rules
                 WHERE (reseller_id = ? OR reseller_id IS NULL)
                 AND depth = ? AND is_active = 1
                 ORDER BY reseller_id DESC
                 LIMIT 1",
                [$ancestor['id'], $depth]
            );

            if (!$rule || $rule['rate_pct'] <= 0) {
                $depth++;
                continue;
            }

            $commissionAmount = (int) round($saleAmount * $rule['rate_pct'] / 100);
            if ($commissionAmount <= 0) {
                $depth++;
                continue;
            }

            // Record commission
            ResellerDB::insertArray('reseller_commissions', [
                'beneficiary_id'     => $ancestor['id'],
                'source_reseller_id' => $resellerId,
                'sale_id'            => $saleId,
                'amount'             => $commissionAmount,
                'rate_pct'           => $rule['rate_pct'],
                'depth'              => $depth,
                'status'             => 'pending',
            ]);

            $depth++;
            if ($depth > 5) break; // Safety cap
        }
    }

    /**
     * Pay out pending commissions for a reseller.
     */
    public static function payCommissions(int $beneficiaryId): array
    {
        $pending = ResellerDB::fetchAll(
            "SELECT * FROM reseller_commissions WHERE beneficiary_id = ? AND status = 'pending'",
            [$beneficiaryId]
        );

        if (empty($pending)) return ['paid' => 0, 'amount' => 0];

        $totalAmount = 0;
        ResellerDB::beginTransaction();
        try {
            foreach ($pending as $commission) {
                $totalAmount += $commission['amount'];
                ResellerDB::execute(
                    "UPDATE reseller_commissions SET status = 'paid', paid_at = datetime('now','localtime') WHERE id = ?",
                    [$commission['id']]
                );
            }

            // Credit the beneficiary's balance
            $current = ResellerDB::fetchOne("SELECT balance FROM resellers WHERE id = ?", [$beneficiaryId]);
            $newBalance = $current['balance'] + $totalAmount;

            ResellerDB::execute(
                "UPDATE resellers SET balance = ? WHERE id = ?",
                [$newBalance, $beneficiaryId]
            );

            ResellerDB::insert(
                "INSERT INTO balance_transactions (reseller_id, type, amount, balance_after, performed_by, description)
                 VALUES (?, 'commission', ?, ?, 'system', ?)",
                [$beneficiaryId, $totalAmount, $newBalance, 'Commission payout: ' . count($pending) . ' items']
            );

            ResellerDB::commit();
            return ['paid' => count($pending), 'amount' => $totalAmount];
        } catch (Exception $e) {
            ResellerDB::rollBack();
            throw $e;
        }
    }

    // ========================================================================
    // USERNAME / PASSWORD GENERATION
    // ========================================================================

    /**
     * Generate a random voucher username.
     */
    public static function generateUsername(string $prefix = '', int $length = 8): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $name = '';
        for ($i = 0; $i < $length; $i++) {
            $name .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return ($prefix ? $prefix : '') . $name;
    }

    /**
     * Generate a random voucher password.
     */
    public static function generatePassword(int $length = 6): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $pass = '';
        for ($i = 0; $i < $length; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pass;
    }
}
