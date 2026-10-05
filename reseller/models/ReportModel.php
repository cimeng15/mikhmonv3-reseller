<?php
/**
 * Mikhmon V3 Reseller System - Report Model
 * 
 * Handles all reporting and analytics queries for the reseller system.
 */

if (substr($_SERVER["REQUEST_URI"], -15) == "ReportModel.php") {
    header("Location:../");
    exit;
}

require_once(__DIR__ . '/database.php');

class ReportModel
{
    // ========================================================================
    // SALES REPORTS
    // ========================================================================

    /**
     * Get daily sales report for a reseller.
     */
    public static function dailySalesReport(int $resellerId, string $date): array
    {
        return ResellerDB::fetchAll(
            "SELECT
                vs.profile_name,
                COUNT(*) AS count,
                SUM(vs.buy_price) AS total_cost,
                SUM(vs.sell_price) AS total_revenue,
                SUM(vs.profit) AS total_profit
             FROM voucher_sales vs
             WHERE vs.reseller_id = ? AND date(vs.created_at) = ?
             GROUP BY vs.profile_name
             ORDER BY total_revenue DESC",
            [$resellerId, $date]
        );
    }

    /**
     * Get monthly sales report for a reseller.
     */
    public static function monthlySalesReport(int $resellerId, string $yearMonth): array
    {
        return ResellerDB::fetchAll(
            "SELECT
                date(vs.created_at) AS sale_date,
                COUNT(*) AS count,
                SUM(vs.buy_price) AS total_cost,
                SUM(vs.sell_price) AS total_revenue,
                SUM(vs.profit) AS total_profit
             FROM voucher_sales vs
             WHERE vs.reseller_id = ? AND strftime('%Y-%m', vs.created_at) = ?
             GROUP BY date(vs.created_at)
             ORDER BY sale_date",
            [$resellerId, $yearMonth]
        );
    }

    /**
     * Get top-selling profiles for a reseller in a given period.
     */
    public static function topProfiles(int $resellerId, string $dateFrom, string $dateTo, int $limit = 10): array
    {
        return ResellerDB::fetchAll(
            "SELECT
                vs.profile_name,
                vp.display_name,
                COUNT(*) AS count,
                SUM(vs.buy_price) AS total_cost,
                SUM(vs.sell_price) AS total_revenue,
                SUM(vs.profit) AS total_profit
             FROM voucher_sales vs
             LEFT JOIN voucher_profiles vp ON vp.id = vs.profile_id
             WHERE vs.reseller_id = ?
                AND vs.created_at >= ? AND vs.created_at <= ?
             GROUP BY vs.profile_name
             ORDER BY count DESC
             LIMIT ?",
            [$resellerId, $dateFrom, $dateTo . ' 23:59:59', $limit]
        );
    }

    // ========================================================================
    // ADMIN REPORTS (across all resellers)
    // ========================================================================

    /**
     * Get overall reseller system summary.
     */
    public static function systemOverview(): array
    {
        $stats = [];

        $stats['total_resellers'] = (int) ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM resellers WHERE status != 'disabled'"
        );

        $stats['active_resellers'] = (int) ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM resellers WHERE status = 'active'"
        );

        $stats['total_balance'] = (int) ResellerDB::fetchValue(
            "SELECT COALESCE(SUM(balance), 0) FROM resellers WHERE status = 'active'"
        );

        $stats['today_sales'] = ResellerDB::fetchOne(
            "SELECT COUNT(*) AS count, COALESCE(SUM(buy_price),0) AS revenue
             FROM voucher_sales WHERE date(created_at) = date('now','localtime')"
        );

        $stats['month_sales'] = ResellerDB::fetchOne(
            "SELECT COUNT(*) AS count, COALESCE(SUM(buy_price),0) AS revenue
             FROM voucher_sales WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now','localtime')"
        );

        $stats['pending_deposits'] = (int) ResellerDB::fetchValue(
            "SELECT COUNT(*) FROM deposit_requests WHERE status = 'pending'"
        );

        $stats['pending_commissions'] = (int) ResellerDB::fetchValue(
            "SELECT COALESCE(SUM(amount), 0) FROM reseller_commissions WHERE status = 'pending'"
        );

        return $stats;
    }

    /**
     * Get top resellers by sales volume in a period.
     */
    public static function topResellers(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        return ResellerDB::fetchAll(
            "SELECT
                r.id,
                r.username,
                r.fullname,
                COUNT(vs.id) AS voucher_count,
                COALESCE(SUM(vs.buy_price), 0) AS total_cost,
                COALESCE(SUM(vs.sell_price), 0) AS total_revenue,
                COALESCE(SUM(vs.profit), 0) AS total_profit
             FROM resellers r
             LEFT JOIN voucher_sales vs ON vs.reseller_id = r.id
                AND vs.created_at >= ? AND vs.created_at <= ?
             WHERE r.status = 'active'
             GROUP BY r.id
             ORDER BY voucher_count DESC
             LIMIT ?",
            [$dateFrom, $dateTo . ' 23:59:59', $limit]
        );
    }

    /**
     * Sales trend chart data (daily totals for last N days).
     */
    public static function salesTrend(int $days = 30, ?int $resellerId = null): array
    {
        $where = "1=1";
        $params = [];

        if ($resellerId) {
            $where = "reseller_id = ?";
            $params[] = $resellerId;
        }

        return ResellerDB::fetchAll(
            "SELECT
                date(created_at) AS day,
                COUNT(*) AS count,
                COALESCE(SUM(buy_price), 0) AS revenue,
                COALESCE(SUM(profit), 0) AS profit
             FROM voucher_sales
             WHERE {$where} AND created_at >= datetime('now','localtime','-{$days} days')
             GROUP BY date(created_at)
             ORDER BY day",
            $params
        );
    }

    /**
     * Get sales per session/router for admin overview.
     */
    public static function salesBySession(string $dateFrom, string $dateTo): array
    {
        return ResellerDB::fetchAll(
            "SELECT
                session_name,
                COUNT(*) AS count,
                COALESCE(SUM(buy_price), 0) AS total_cost,
                COALESCE(SUM(sell_price), 0) AS total_revenue
             FROM voucher_sales
             WHERE created_at >= ? AND created_at <= ?
             GROUP BY session_name
             ORDER BY count DESC",
            [$dateFrom, $dateTo . ' 23:59:59']
        );
    }

    /**
     * Get commission report for admin.
     */
    public static function commissionReport(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (isset($filters['beneficiary_id'])) {
            $where[] = "rc.beneficiary_id = ?";
            $params[] = $filters['beneficiary_id'];
        }
        if (isset($filters['status'])) {
            $where[] = "rc.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "rc.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "rc.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        return ResellerDB::fetchAll(
            "SELECT
                rc.*,
                r1.username AS beneficiary_name,
                r2.username AS source_name,
                vs.hotspot_username,
                vs.profile_name
             FROM reseller_commissions rc
             JOIN resellers r1 ON r1.id = rc.beneficiary_id
             JOIN resellers r2 ON r2.id = rc.source_reseller_id
             JOIN voucher_sales vs ON vs.id = rc.sale_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY rc.created_at DESC
             LIMIT 100",
            $params
        );
    }

    /**
     * Get reseller activity log.
     */
    public static function activityLog(array $filters = []): array
    {
        $where = ["1=1"];
        $params = [];

        if (isset($filters['reseller_id'])) {
            $where[] = "ral.reseller_id = ?";
            $params[] = $filters['reseller_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = "ral.action = ?";
            $params[] = $filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "ral.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "ral.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;

        return ResellerDB::fetchAll(
            "SELECT ral.*, r.username AS reseller_name
             FROM reseller_activity_log ral
             LEFT JOIN resellers r ON r.id = ral.reseller_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY ral.created_at DESC
             LIMIT {$limit}",
            $params
        );
    }

    /**
     * Balance history chart data for a reseller.
     */
    public static function balanceHistory(int $resellerId, int $limit = 50): array
    {
        return ResellerDB::fetchAll(
            "SELECT type, amount, balance_after, description, created_at
             FROM balance_transactions
             WHERE reseller_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$resellerId, $limit]
        );
    }
}
