<?php
/**
 * Mikhmon V3 Reseller System - Reseller Model
 * 
 * Handles all reseller CRUD, authentication, hierarchy management,
 * and account operations.
 */

if (substr($_SERVER["REQUEST_URI"], -16) == "ResellerModel.php") {
    header("Location:../");
    exit;
}

require_once(__DIR__ . '/database.php');

class ResellerModel
{
    // ========================================================================
    // AUTHENTICATION
    // ========================================================================

    /**
     * Authenticate a reseller by username and password.
     * Returns reseller data on success, null on failure.
     */
    public static function authenticate(string $username, string $password): ?array
    {
        $reseller = ResellerDB::fetchOne(
            "SELECT * FROM resellers WHERE username = ? AND status = 'active'",
            [$username]
        );

        if ($reseller && password_verify($password, $reseller['password_hash'])) {
            // Update last login
            ResellerDB::execute(
                "UPDATE resellers SET last_login = datetime('now','localtime') WHERE id = ?",
                [$reseller['id']]
            );
            unset($reseller['password_hash']); // Never expose hash
            return $reseller;
        }

        return null;
    }

    /**
     * Create a session token for the authenticated reseller.
     */
    public static function createSession(int $resellerId, string $ip = '', string $userAgent = ''): string
    {
        $token = bin2hex(random_bytes(32));
        $timeoutMinutes = (int) self::getSystemSetting('session_timeout_minutes', 120);
        $expiresAt = date('Y-m-d H:i:s', time() + ($timeoutMinutes * 60));

        ResellerDB::insert(
            "INSERT INTO reseller_sessions (reseller_id, session_token, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?)",
            [$resellerId, $token, $ip, $userAgent, $expiresAt]
        );

        return $token;
    }

    /**
     * Validate and refresh a session token. Returns reseller data or null.
     */
    public static function validateSession(string $token): ?array
    {
        $session = ResellerDB::fetchOne(
            "SELECT rs.*, r.username, r.status as reseller_status
             FROM reseller_sessions rs
             JOIN resellers r ON r.id = rs.reseller_id
             WHERE rs.session_token = ? AND rs.is_active = 1",
            [$token]
        );

        if (!$session) return null;

        // Check expiry
        if (strtotime($session['expires_at']) < time()) {
            ResellerDB::execute(
                "UPDATE reseller_sessions SET is_active = 0 WHERE id = ?",
                [$session['id']]
            );
            return null;
        }

        // Check reseller is still active
        if ($session['reseller_status'] !== 'active') return null;

        // Extend session
        $timeoutMinutes = (int) self::getSystemSetting('session_timeout_minutes', 120);
        $newExpiry = date('Y-m-d H:i:s', time() + ($timeoutMinutes * 60));
        ResellerDB::execute(
            "UPDATE reseller_sessions SET expires_at = ? WHERE id = ?",
            [$newExpiry, $session['id']]
        );

        return self::getById($session['reseller_id']);
    }

    /**
     * Destroy a session (logout).
     */
    public static function destroySession(string $token): void
    {
        ResellerDB::execute(
            "UPDATE reseller_sessions SET is_active = 0 WHERE session_token = ?",
            [$token]
        );
    }

    /**
     * Destroy all sessions for a reseller.
     */
    public static function destroyAllSessions(int $resellerId): void
    {
        ResellerDB::execute(
            "UPDATE reseller_sessions SET is_active = 0 WHERE reseller_id = ?",
            [$resellerId]
        );
    }

    // ========================================================================
    // CRUD OPERATIONS
    // ========================================================================

    /**
     * Create a new reseller account.
     */
    public static function create(array $data): int
    {
        // Hash the password
        $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        unset($data['password']);

        // Generate API key if API is enabled
        if (!empty($data['api_enabled'])) {
            $data['api_key'] = self::generateApiKey();
        }

        // Set level based on parent
        if (!empty($data['parent_id'])) {
            $parent = self::getById($data['parent_id']);
            if ($parent) {
                $data['level'] = $parent['level'] + 1;
                $maxLevels = (int) self::getSystemSetting('max_reseller_levels', 3);
                if ($data['level'] > $maxLevels) {
                    throw new Exception("Maximum reseller hierarchy depth ({$maxLevels}) reached.");
                }
            }
        }

        $columns = [
            'username', 'password_hash', 'fullname', 'email', 'phone',
            'company', 'address', 'parent_id', 'level', 'status',
            'balance', 'credit_limit', 'markup_pct', 'discount_pct',
            'allowed_sessions', 'allowed_profiles', 'daily_limit',
            'monthly_limit', 'logo_path', 'theme', 'api_key',
            'api_enabled', 'notes'
        ];

        $insertData = [];
        foreach ($columns as $col) {
            if (array_key_exists($col, $data)) {
                $insertData[$col] = $data[$col];
            }
        }

        return ResellerDB::insertArray('resellers', $insertData);
    }

    /**
     * Update a reseller account.
     */
    public static function update(int $id, array $data): bool
    {
        // Handle password change
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        unset($data['password']);

        // Regenerate API key if requested
        if (isset($data['regenerate_api_key']) && $data['regenerate_api_key']) {
            $data['api_key'] = self::generateApiKey();
            unset($data['regenerate_api_key']);
        }

        // Only update allowed fields
        $allowed = [
            'password_hash', 'fullname', 'email', 'phone', 'company',
            'address', 'parent_id', 'level', 'status', 'balance',
            'credit_limit', 'markup_pct', 'discount_pct',
            'allowed_sessions', 'allowed_profiles', 'daily_limit',
            'monthly_limit', 'logo_path', 'theme', 'api_key',
            'api_enabled', 'notes'
        ];

        $updateData = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $updateData[$col] = $data[$col];
            }
        }

        if (empty($updateData)) return false;

        return ResellerDB::updateArray('resellers', $updateData, 'id = ?', [$id]) > 0;
    }

    /**
     * Delete a reseller (soft delete by setting status to 'disabled').
     */
    public static function delete(int $id): bool
    {
        // Re-assign children to this reseller's parent
        $reseller = self::getById($id);
        if ($reseller) {
            ResellerDB::execute(
                "UPDATE resellers SET parent_id = ? WHERE parent_id = ?",
                [$reseller['parent_id'], $id]
            );
        }

        return ResellerDB::execute(
            "UPDATE resellers SET status = 'disabled' WHERE id = ?",
            [$id]
        ) > 0;
    }

    /**
     * Permanently remove a reseller and all associated data.
     */
    public static function purge(int $id): bool
    {
        return ResellerDB::execute("DELETE FROM resellers WHERE id = ?", [$id]) > 0;
    }

    /**
     * Get a reseller by ID.
     */
    public static function getById(int $id): ?array
    {
        $row = ResellerDB::fetchOne("SELECT * FROM resellers WHERE id = ?", [$id]);
        if ($row) unset($row['password_hash']);
        return $row;
    }

    /**
     * Get a reseller by username.
     */
    public static function getByUsername(string $username): ?array
    {
        $row = ResellerDB::fetchOne(
            "SELECT * FROM resellers WHERE username = ?",
            [$username]
        );
        if ($row) unset($row['password_hash']);
        return $row;
    }

    /**
     * Get a reseller by API key.
     */
    public static function getByApiKey(string $apiKey): ?array
    {
        $row = ResellerDB::fetchOne(
            "SELECT * FROM resellers WHERE api_key = ? AND api_enabled = 1 AND status = 'active'",
            [$apiKey]
        );
        if ($row) unset($row['password_hash']);
        return $row;
    }

    /**
     * List all resellers with optional filters.
     */
    public static function getAll(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (isset($filters['status'])) {
            $where[] = "r.status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === null) {
                $where[] = "r.parent_id IS NULL";
            } else {
                $where[] = "r.parent_id = ?";
                $params[] = $filters['parent_id'];
            }
        }
        if (isset($filters['level'])) {
            $where[] = "r.level = ?";
            $params[] = $filters['level'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(r.username LIKE ? OR r.fullname LIKE ? OR r.email LIKE ? OR r.phone LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search, $search]);
        }

        $orderBy = $filters['order_by'] ?? 'r.created_at DESC';
        $limit   = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        $offset  = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $sql = "SELECT r.*, COALESCE(p.username, 'admin') AS parent_name
                FROM resellers r
                LEFT JOIN resellers p ON p.id = r.parent_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$orderBy}
                LIMIT {$limit} OFFSET {$offset}";

        $results = ResellerDB::fetchAll($sql, $params);
        foreach ($results as &$row) {
            unset($row['password_hash']);
        }
        return $results;
    }

    /**
     * Count resellers matching filters.
     */
    public static function count(array $filters = []): int
    {
        $where = ["1=1"];
        $params = [];

        if (isset($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === null) {
                $where[] = "parent_id IS NULL";
            } else {
                $where[] = "parent_id = ?";
                $params[] = $filters['parent_id'];
            }
        }

        return (int) ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM resellers WHERE " . implode(' AND ', $where),
            $params
        );
    }

    // ========================================================================
    // HIERARCHY MANAGEMENT
    // ========================================================================

    /**
     * Get the full hierarchy tree (all descendants) for a reseller.
     */
    public static function getDescendants(int $resellerId): array
    {
        $sql = "WITH RECURSIVE tree AS (
                    SELECT id, username, fullname, parent_id, level, balance, status, 0 AS depth
                    FROM resellers WHERE parent_id = ?
                    UNION ALL
                    SELECT r.id, r.username, r.fullname, r.parent_id, r.level, r.balance, r.status, t.depth + 1
                    FROM resellers r INNER JOIN tree t ON r.parent_id = t.id
                )
                SELECT * FROM tree ORDER BY depth, username";

        return ResellerDB::fetchAll($sql, [$resellerId]);
    }

    /**
     * Get the ancestor chain (path to root) for a reseller.
     */
    public static function getAncestors(int $resellerId): array
    {
        $ancestors = [];
        $currentId = $resellerId;
        $seen = [];

        while ($currentId !== null && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            $reseller = ResellerDB::fetchOne(
                "SELECT id, username, fullname, parent_id, level FROM resellers WHERE id = ?",
                [$currentId]
            );
            if (!$reseller) break;
            $ancestors[] = $reseller;
            $currentId = $reseller['parent_id'];
        }

        return array_reverse($ancestors);
    }

    /**
     * Get direct children of a reseller.
     */
    public static function getChildren(int $resellerId): array
    {
        return ResellerDB::fetchAll(
            "SELECT id, username, fullname, email, phone, status, balance, level, created_at
             FROM resellers WHERE parent_id = ? ORDER BY username",
            [$resellerId]
        );
    }

    /**
     * Check if reseller A is an ancestor of reseller B.
     */
    public static function isAncestorOf(int $ancestorId, int $descendantId): bool
    {
        $ancestors = self::getAncestors($descendantId);
        foreach ($ancestors as $a) {
            if ($a['id'] == $ancestorId) return true;
        }
        return false;
    }

    // ========================================================================
    // BALANCE MANAGEMENT
    // ========================================================================

    /**
     * Deposit funds into a reseller's balance.
     */
    public static function deposit(int $resellerId, int $amount, string $performedBy = 'admin', string $description = ''): bool
    {
        if ($amount <= 0) throw new Exception("Deposit amount must be positive.");

        ResellerDB::beginTransaction();
        try {
            // Lock row by fetching current balance
            $current = ResellerDB::fetchOne("SELECT balance FROM resellers WHERE id = ?", [$resellerId]);
            if (!$current) throw new Exception("Reseller not found.");

            $newBalance = $current['balance'] + $amount;

            ResellerDB::execute(
                "UPDATE resellers SET balance = ? WHERE id = ?",
                [$newBalance, $resellerId]
            );

            ResellerDB::insert(
                "INSERT INTO balance_transactions (reseller_id, type, amount, balance_after, performed_by, description)
                 VALUES (?, 'deposit', ?, ?, ?, ?)",
                [$resellerId, $amount, $newBalance, $performedBy, $description]
            );

            ResellerDB::commit();
            return true;
        } catch (Exception $e) {
            ResellerDB::rollBack();
            throw $e;
        }
    }

    /**
     * Withdraw funds from a reseller's balance.
     */
    public static function withdraw(int $resellerId, int $amount, string $performedBy = 'admin', string $description = ''): bool
    {
        if ($amount <= 0) throw new Exception("Withdrawal amount must be positive.");

        ResellerDB::beginTransaction();
        try {
            $current = ResellerDB::fetchOne(
                "SELECT balance, credit_limit FROM resellers WHERE id = ?",
                [$resellerId]
            );
            if (!$current) throw new Exception("Reseller not found.");

            $newBalance = $current['balance'] - $amount;
            if ($newBalance < -$current['credit_limit']) {
                throw new Exception("Insufficient balance. Available: " . ($current['balance'] + $current['credit_limit']));
            }

            ResellerDB::execute(
                "UPDATE resellers SET balance = ? WHERE id = ?",
                [$newBalance, $resellerId]
            );

            ResellerDB::insert(
                "INSERT INTO balance_transactions (reseller_id, type, amount, balance_after, performed_by, description)
                 VALUES (?, 'withdrawal', ?, ?, ?, ?)",
                [$resellerId, -$amount, $newBalance, $performedBy, $description]
            );

            ResellerDB::commit();
            return true;
        } catch (Exception $e) {
            ResellerDB::rollBack();
            throw $e;
        }
    }

    /**
     * Deduct balance for a purchase (internal - used by voucher generation).
     * Returns false if insufficient balance.
     */
    public static function deductForPurchase(int $resellerId, int $amount, int $referenceId, string $description = ''): bool
    {
        if ($amount <= 0) return true; // Free voucher

        $current = ResellerDB::fetchOne(
            "SELECT balance, credit_limit FROM resellers WHERE id = ?",
            [$resellerId]
        );
        if (!$current) return false;

        $newBalance = $current['balance'] - $amount;
        if ($newBalance < -$current['credit_limit']) {
            return false; // Insufficient balance
        }

        ResellerDB::execute(
            "UPDATE resellers SET balance = ? WHERE id = ?",
            [$newBalance, $resellerId]
        );

        ResellerDB::insert(
            "INSERT INTO balance_transactions (reseller_id, type, amount, balance_after, reference_type, reference_id, performed_by, description)
             VALUES (?, 'purchase', ?, ?, 'voucher_sale', ?, 'system', ?)",
            [$resellerId, -$amount, $newBalance, $referenceId, $description]
        );

        return true;
    }

    /**
     * Refund balance for a cancelled/refunded voucher.
     */
    public static function refundPurchase(int $resellerId, int $amount, int $referenceId, string $description = ''): bool
    {
        if ($amount <= 0) return true;

        $current = ResellerDB::fetchOne("SELECT balance FROM resellers WHERE id = ?", [$resellerId]);
        if (!$current) return false;

        $newBalance = $current['balance'] + $amount;

        ResellerDB::execute(
            "UPDATE resellers SET balance = ? WHERE id = ?",
            [$newBalance, $resellerId]
        );

        ResellerDB::insert(
            "INSERT INTO balance_transactions (reseller_id, type, amount, balance_after, reference_type, reference_id, performed_by, description)
             VALUES (?, 'refund', ?, ?, 'voucher_sale', ?, 'system', ?)",
            [$resellerId, $amount, $newBalance, $referenceId, $description]
        );

        return true;
    }

    /**
     * Get balance transaction history for a reseller.
     */
    public static function getTransactions(int $resellerId, array $filters = []): array
    {
        $where = ["reseller_id = ?"];
        $params = [$resellerId];

        if (!empty($filters['type'])) {
            $where[] = "type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $limit  = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        return ResellerDB::fetchAll(
            "SELECT * FROM balance_transactions
             WHERE " . implode(' AND ', $where) . "
             ORDER BY created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    // ========================================================================
    // PERMISSION CHECKS
    // ========================================================================

    /**
     * Check if a reseller can access a given MikroTik session.
     */
    public static function canAccessSession(int $resellerId, string $sessionName): bool
    {
        $reseller = self::getById($resellerId);
        if (!$reseller || $reseller['status'] !== 'active') return false;
        if ($reseller['allowed_sessions'] === '*') return true;

        $allowed = array_map('trim', explode(',', $reseller['allowed_sessions']));
        return in_array($sessionName, $allowed);
    }

    /**
     * Check if a reseller can sell a given hotspot profile.
     */
    public static function canSellProfile(int $resellerId, string $profileName): bool
    {
        $reseller = self::getById($resellerId);
        if (!$reseller || $reseller['status'] !== 'active') return false;
        if ($reseller['allowed_profiles'] === '*') return true;

        $allowed = array_map('trim', explode(',', $reseller['allowed_profiles']));
        return in_array($profileName, $allowed);
    }

    /**
     * Check daily voucher limit for a reseller.
     */
    public static function checkDailyLimit(int $resellerId, int $quantity = 1): bool
    {
        $reseller = self::getById($resellerId);
        if (!$reseller) return false;

        $limit = $reseller['daily_limit'];
        if ($limit == 0) return true; // Unlimited

        $todayCount = (int) ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM voucher_sales
             WHERE reseller_id = ? AND date(created_at) = date('now','localtime')",
            [$resellerId]
        );

        return ($todayCount + $quantity) <= $limit;
    }

    /**
     * Check monthly voucher limit for a reseller.
     */
    public static function checkMonthlyLimit(int $resellerId, int $quantity = 1): bool
    {
        $reseller = self::getById($resellerId);
        if (!$reseller) return false;

        $limit = $reseller['monthly_limit'];
        if ($limit == 0) return true; // Unlimited

        $monthCount = (int) ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM voucher_sales
             WHERE reseller_id = ? AND strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now','localtime')",
            [$resellerId]
        );

        return ($monthCount + $quantity) <= $limit;
    }

    // ========================================================================
    // UTILITY
    // ========================================================================

    /**
     * Generate a unique API key.
     */
    private static function generateApiKey(): string
    {
        return 'mk3r_' . bin2hex(random_bytes(24));
    }

    /**
     * Get a system setting value.
     */
    public static function getSystemSetting(string $key, string $default = ''): string
    {
        $value = ResellerDB::fetchValue(
            "SELECT setting_value FROM reseller_system_settings WHERE setting_key = ?",
            [$key]
        );
        return $value !== false && $value !== null ? $value : $default;
    }

    /**
     * Set a system setting value.
     */
    public static function setSystemSetting(string $key, string $value): void
    {
        ResellerDB::execute(
            "INSERT INTO reseller_system_settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, datetime('now','localtime'))
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value,
             updated_at = datetime('now','localtime')",
            [$key, $value]
        );
    }

    /**
     * Get all system settings as key-value pairs.
     */
    public static function getAllSystemSettings(): array
    {
        $rows = ResellerDB::fetchAll("SELECT setting_key, setting_value FROM reseller_system_settings");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Log a reseller activity.
     */
    public static function logActivity(
        ?int $resellerId,
        string $action,
        string $entityType = '',
        ?int $entityId = null,
        string $details = '',
        string $performedBy = 'system'
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        ResellerDB::insert(
            "INSERT INTO reseller_activity_log (reseller_id, action, entity_type, entity_id, details, ip_address, performed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$resellerId, $action, $entityType, $entityId, $details, $ip, $performedBy]
        );
    }

    /**
     * Check if username is available.
     */
    public static function isUsernameAvailable(string $username, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            return ResellerDB::fetchValue(
                "SELECT COUNT(*) FROM resellers WHERE username = ? AND id != ?",
                [$username, $excludeId]
            ) == 0;
        }
        return ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM resellers WHERE username = ?",
            [$username]
        ) == 0;
    }
}
