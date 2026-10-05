<?php
/**
 * Mikhmon V3 - Reseller Models (CRUD Operations)
 */

require_once __DIR__ . '/database.php';

// ======================== RESELLER CRUD ========================

function createReseller($data) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO resellers (username, password, name, phone, balance, discount, status, allowed_sessions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['username'],
        password_hash($data['password'], PASSWORD_DEFAULT),
        $data['name'],
        $data['phone'] ?? '',
        $data['balance'] ?? 0,
        $data['discount'] ?? 0,
        $data['status'] ?? 'active',
        $data['allowed_sessions'] ?? ''
    ]);
    return $db->lastInsertId();
}

function updateReseller($id, $data) {
    $db = getDB();
    $fields = [];
    $values = [];
    $allowed = ['name', 'phone', 'discount', 'status', 'allowed_sessions'];

    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }

    if (isset($data['password']) && !empty($data['password'])) {
        $fields[] = "password = ?";
        $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (empty($fields)) return false;

    $fields[] = "updated_at = CURRENT_TIMESTAMP";
    $values[] = $id;

    $sql = "UPDATE resellers SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute($values);
}

function deleteReseller($id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM resellers WHERE id = ?");
    return $stmt->execute([$id]);
}

function getReseller($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM resellers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getResellerByUsername($username) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM resellers WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function getAllResellers() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM resellers ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function getAllowedSessions($reseller_id) {
    $reseller = getReseller($reseller_id);
    if (!$reseller || empty($reseller['allowed_sessions'])) return [];
    return array_filter(array_map('trim', explode(',', $reseller['allowed_sessions'])));
}

// ======================== BALANCE OPERATIONS ========================

function addDeposit($reseller_id, $amount, $description = '') {
    $db = getDB();
    $db->beginTransaction();
    try {
        $reseller = getReseller($reseller_id);
        if (!$reseller) throw new Exception('Reseller tidak ditemukan');

        $balance_before = $reseller['balance'];
        $balance_after = $balance_before + $amount;

        $stmt = $db->prepare("UPDATE resellers SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$balance_after, $reseller_id]);

        $stmt = $db->prepare("INSERT INTO transactions (reseller_id, type, amount, balance_before, balance_after, description) VALUES (?, 'deposit', ?, ?, ?, ?)");
        $stmt->execute([$reseller_id, $amount, $balance_before, $balance_after, $description]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function deductBalance($reseller_id, $amount, $description = '', $voucher_data = '', $session_name = '') {
    $db = getDB();
    $db->beginTransaction();
    try {
        $reseller = getReseller($reseller_id);
        if (!$reseller) throw new Exception('Reseller tidak ditemukan');
        if ($reseller['balance'] < $amount) throw new Exception('Saldo tidak mencukupi');

        $balance_before = $reseller['balance'];
        $balance_after = $balance_before - $amount;

        $stmt = $db->prepare("UPDATE resellers SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$balance_after, $reseller_id]);

        $stmt = $db->prepare("INSERT INTO transactions (reseller_id, type, amount, balance_before, balance_after, description, voucher_data, session_name) VALUES (?, 'purchase', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$reseller_id, $amount, $balance_before, $balance_after, $description, $voucher_data, $session_name]);

        $db->commit();
        return $db->lastInsertId();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function refundTransaction($transaction_id) {
    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND type = 'purchase'");
        $stmt->execute([$transaction_id]);
        $trx = $stmt->fetch();
        if (!$trx) throw new Exception('Transaksi tidak ditemukan');

        $reseller = getReseller($trx['reseller_id']);
        $balance_before = $reseller['balance'];
        $balance_after = $balance_before + $trx['amount'];

        $stmt = $db->prepare("UPDATE resellers SET balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$balance_after, $trx['reseller_id']]);

        $stmt = $db->prepare("INSERT INTO transactions (reseller_id, type, amount, balance_before, balance_after, description) VALUES (?, 'refund', ?, ?, ?, ?)");
        $stmt->execute([$trx['reseller_id'], $trx['amount'], $balance_before, $balance_after, 'Refund: ' . $trx['description']]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// ======================== TRANSACTIONS ========================

function getTransactions($reseller_id = null, $filters = []) {
    $db = getDB();
    $where = [];
    $params = [];

    if ($reseller_id) {
        $where[] = "t.reseller_id = ?";
        $params[] = $reseller_id;
    }
    if (!empty($filters['type'])) {
        $where[] = "t.type = ?";
        $params[] = $filters['type'];
    }
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(t.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(t.created_at) <= ?";
        $params[] = $filters['date_to'];
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
    $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

    $sql = "SELECT t.*, r.name as reseller_name, r.username as reseller_username
            FROM transactions t
            LEFT JOIN resellers r ON t.reseller_id = r.id
            $whereClause
            ORDER BY t.created_at DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTransactionSummary($reseller_id = null, $period = 'all') {
    $db = getDB();
    $where = [];
    $params = [];

    if ($reseller_id) {
        $where[] = "reseller_id = ?";
        $params[] = $reseller_id;
    }

    switch ($period) {
        case 'today':
            $where[] = "DATE(created_at) = DATE('now', 'localtime')";
            break;
        case 'month':
            $where[] = "strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', 'localtime')";
            break;
        case 'year':
            $where[] = "strftime('%Y', created_at) = strftime('%Y', 'now', 'localtime')";
            break;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT
                type,
                COUNT(*) as total_trx,
                COALESCE(SUM(amount), 0) as total_amount
            FROM transactions
            $whereClause
            GROUP BY type";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $summary = ['deposit' => 0, 'purchase' => 0, 'refund' => 0, 'deposit_count' => 0, 'purchase_count' => 0];
    foreach ($rows as $row) {
        $summary[$row['type']] = $row['total_amount'];
        $summary[$row['type'] . '_count'] = $row['total_trx'];
    }
    return $summary;
}

function getResellerLogs($reseller_id, $limit = 50) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM reseller_logs WHERE reseller_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$reseller_id, $limit]);
    return $stmt->fetchAll();
}
