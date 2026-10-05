<?php
/**
 * Mikhmon V3 Reseller System - Deposit Model
 * 
 * Handles deposit requests, payment tracking, and approval workflow.
 */

if (substr($_SERVER["REQUEST_URI"], -16) == "DepositModel.php") {
    header("Location:../");
    exit;
}

require_once(__DIR__ . '/database.php');
require_once(__DIR__ . '/ResellerModel.php');

class DepositModel
{
    /**
     * Create a new deposit request.
     */
    public static function createRequest(int $resellerId, array $data): int
    {
        $minDeposit = (int) ResellerModel::getSystemSetting('min_deposit', 10000);
        if ($data['amount'] < $minDeposit) {
            throw new Exception("Minimum deposit is {$minDeposit}.");
        }

        $id = ResellerDB::insertArray('deposit_requests', [
            'reseller_id'    => $resellerId,
            'amount'         => $data['amount'],
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'payment_proof'  => $data['payment_proof'] ?? '',
            'bank_name'      => $data['bank_name'] ?? '',
            'account_number' => $data['account_number'] ?? '',
            'account_name'   => $data['account_name'] ?? '',
            'reference_no'   => $data['reference_no'] ?? '',
            'status'         => 'pending',
        ]);

        // Notify via activity log
        ResellerModel::logActivity(
            $resellerId, 'deposit_request', 'deposit_request', $id,
            json_encode(['amount' => $data['amount'], 'method' => $data['payment_method'] ?? 'bank_transfer']),
            'reseller'
        );

        // Create notification for admin (reseller_id=0 convention for admin)
        ResellerDB::insertArray('reseller_notifications', [
            'reseller_id' => $resellerId,
            'title'       => 'Deposit Request Submitted',
            'message'     => "Your deposit request for " . $data['amount'] . " has been submitted and is pending approval.",
            'type'        => 'info',
        ]);

        return $id;
    }

    /**
     * Approve a deposit request.
     */
    public static function approve(int $requestId, string $adminNotes = '', string $processedBy = 'admin'): bool
    {
        $request = self::getById($requestId);
        if (!$request || $request['status'] !== 'pending') {
            throw new Exception("Request not found or already processed.");
        }

        ResellerDB::beginTransaction();
        try {
            // Update request status
            ResellerDB::updateArray('deposit_requests', [
                'status'       => 'approved',
                'admin_notes'  => $adminNotes,
                'processed_by' => $processedBy,
                'processed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$requestId]);

            // Add balance to reseller
            ResellerModel::deposit(
                $request['reseller_id'],
                $request['amount'],
                $processedBy,
                "Deposit request #{$requestId} approved"
            );

            // Notify reseller
            ResellerDB::insertArray('reseller_notifications', [
                'reseller_id' => $request['reseller_id'],
                'title'       => 'Deposit Approved',
                'message'     => "Your deposit of {$request['amount']} has been approved and added to your balance.",
                'type'        => 'success',
            ]);

            ResellerModel::logActivity(
                $request['reseller_id'], 'deposit_approved', 'deposit_request', $requestId,
                json_encode(['amount' => $request['amount']]),
                $processedBy
            );

            ResellerDB::commit();
            return true;
        } catch (Exception $e) {
            ResellerDB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject a deposit request.
     */
    public static function reject(int $requestId, string $adminNotes = '', string $processedBy = 'admin'): bool
    {
        $request = self::getById($requestId);
        if (!$request || $request['status'] !== 'pending') {
            throw new Exception("Request not found or already processed.");
        }

        ResellerDB::updateArray('deposit_requests', [
            'status'       => 'rejected',
            'admin_notes'  => $adminNotes,
            'processed_by' => $processedBy,
            'processed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$requestId]);

        // Notify reseller
        ResellerDB::insertArray('reseller_notifications', [
            'reseller_id' => $request['reseller_id'],
            'title'       => 'Deposit Rejected',
            'message'     => "Your deposit request of {$request['amount']} was rejected. Reason: {$adminNotes}",
            'type'        => 'danger',
        ]);

        return true;
    }

    /**
     * Get a deposit request by ID.
     */
    public static function getById(int $id): ?array
    {
        return ResellerDB::fetchOne("SELECT * FROM deposit_requests WHERE id = ?", [$id]);
    }

    /**
     * Get all deposit requests with filters.
     */
    public static function getAll(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (isset($filters['reseller_id'])) {
            $where[] = "dr.reseller_id = ?";
            $params[] = $filters['reseller_id'];
        }
        if (isset($filters['status'])) {
            $where[] = "dr.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "dr.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "dr.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $limit  = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        return ResellerDB::fetchAll(
            "SELECT dr.*, r.username AS reseller_name, r.fullname AS reseller_fullname
             FROM deposit_requests dr
             JOIN resellers r ON r.id = dr.reseller_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY dr.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    /**
     * Count pending deposit requests.
     */
    public static function countPending(): int
    {
        return (int) ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM deposit_requests WHERE status = 'pending'"
        );
    }
}
