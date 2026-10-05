<?php
/**
 * Mikhmon V3 - Admin Reseller Reports
 */
error_reporting(0);
if(!isset($_SESSION["mikhmon"])){echo "<script>window.location='./admin.php?id=login'</script>"; exit;}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/router_helpers.php';

$resellers = getAllResellers();
$summaryAll = getTransactionSummary(null, 'all');
$summaryMonth = getTransactionSummary(null, 'month');
$summaryToday = getTransactionSummary(null, 'today');
$totalBalance = array_sum(array_column($resellers, 'balance'));
?>

<div class="mk-admin-page-head">
    <div><h1>Laporan reseller</h1><p>Ringkasan saldo, pembelian, dan voucher seluruh reseller.</p></div>
</div>
<?php include __DIR__ . '/admin_tabs.php'; ?>

<div class="row">
    <div class="col-md-3">
        <div class="panel panel-primary mk-admin-stat">
            <div class="panel-body text-center">
                <i class="fa fa-users fa-2x"></i>
                <h4>Total Reseller</h4>
                <h2><?=count($resellers)?></h2>
                <small><?=count(array_filter($resellers, function($r){return $r['status']==='active';}))?> aktif</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-success mk-admin-stat">
            <div class="panel-body text-center">
                <i class="fa fa-money fa-2x"></i>
                <h4>Total Saldo</h4>
                <h2>Rp <?=number_format($totalBalance, 0, ',', '.')?></h2>
                <small>saldo seluruh reseller</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-info mk-admin-stat">
            <div class="panel-body text-center">
                <i class="fa fa-shopping-cart fa-2x"></i>
                <h4>Penjualan Hari Ini</h4>
                <h2>Rp <?=number_format($summaryToday['purchase'], 0, ',', '.')?></h2>
                <small><?=$summaryToday['purchase_count']?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-warning mk-admin-stat">
            <div class="panel-body text-center">
                <i class="fa fa-calendar fa-2x"></i>
                <h4>Penjualan Bulan Ini</h4>
                <h2>Rp <?=number_format($summaryMonth['purchase'], 0, ',', '.')?></h2>
                <small><?=$summaryMonth['purchase_count']?> transaksi</small>
            </div>
        </div>
    </div>
</div>

<!-- Per-Reseller Summary -->
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading"><h4><i class="fa fa-bar-chart"></i> Ringkasan Per Reseller</h4></div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mk-card-table">
                        <thead>
                            <tr>
                                <th>Reseller</th>
                                <th>Status</th>
                                <th>Saldo</th>
                                <th>Total Deposit</th>
                                <th>Total Pembelian</th>
                                <th>Jumlah Transaksi</th>
                                <th>Deposit Bulan Ini</th>
                                <th>Pembelian Bulan Ini</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($resellers as $r):
                            $sAll = getTransactionSummary($r['id'], 'all');
                            $sMonth = getTransactionSummary($r['id'], 'month');
                        ?>
                            <tr>
                                <td data-label="Reseller"><strong><?=htmlspecialchars($r['name'])?></strong><br><small class="text-muted"><?=htmlspecialchars($r['username'])?></small></td>
                                <td data-label="Status" class="text-center">
                                    <span class="label label-<?=$r['status']==='active'?'success':'danger'?>"><?=$r['status']?></span>
                                </td>
                                <td data-label="Saldo" class="text-right">Rp <?=number_format($r['balance'], 0, ',', '.')?></td>
                                <td data-label="Total deposit" class="text-right text-success">Rp <?=number_format($sAll['deposit'], 0, ',', '.')?></td>
                                <td data-label="Total pembelian" class="text-right text-info">Rp <?=number_format($sAll['purchase'], 0, ',', '.')?></td>
                                <td data-label="Jumlah transaksi" class="text-center"><?=$sAll['deposit_count'] + $sAll['purchase_count']?></td>
                                <td data-label="Deposit bulan ini" class="text-right">Rp <?=number_format($sMonth['deposit'], 0, ',', '.')?></td>
                                <td data-label="Pembelian bulan ini" class="text-right">Rp <?=number_format($sMonth['purchase'], 0, ',', '.')?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="info">
                                <td colspan="2"><strong>TOTAL</strong></td>
                                <td class="text-right"><strong>Rp <?=number_format($totalBalance, 0, ',', '.')?></strong></td>
                                <td class="text-right"><strong>Rp <?=number_format($summaryAll['deposit'], 0, ',', '.')?></strong></td>
                                <td class="text-right"><strong>Rp <?=number_format($summaryAll['purchase'], 0, ',', '.')?></strong></td>
                                <td class="text-center"><strong><?=$summaryAll['deposit_count'] + $summaryAll['purchase_count']?></strong></td>
                                <td class="text-right"><strong>Rp <?=number_format($summaryMonth['deposit'], 0, ',', '.')?></strong></td>
                                <td class="text-right"><strong>Rp <?=number_format($summaryMonth['purchase'], 0, ',', '.')?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$voucherAdminFilters = [
    'reseller_id' => intval($_GET['voucher_reseller_id'] ?? 0),
    'session_name' => trim($_GET['voucher_session'] ?? ''),
    'profile' => trim($_GET['voucher_profile'] ?? ''),
    'search' => trim($_GET['voucher_search'] ?? ''),
    'date_from' => trim($_GET['voucher_date_from'] ?? ''),
    'date_to' => trim($_GET['voucher_date_to'] ?? ''),
    'limit' => 500
];
$allVoucherRows = getAllResellerVouchers($voucherAdminFilters);
?>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading"><h4><i class="fa fa-ticket"></i> Seluruh Voucher Reseller</h4></div>
            <div class="panel-body">
                <form method="GET" class="form-inline" style="margin-bottom:15px">
                    <input type="hidden" name="id" value="reseller-reports">
                    <select name="voucher_reseller_id" class="form-control">
                        <option value="">Semua Reseller</option>
                        <?php foreach ($resellers as $r): ?>
                        <option value="<?=$r['id']?>" <?=$voucherAdminFilters['reseller_id'] == $r['id'] ? 'selected' : ''?>><?=htmlspecialchars($r['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="voucher_session" class="form-control" value="<?=htmlspecialchars($voucherAdminFilters['session_name'])?>" placeholder="Router">
                    <input type="text" name="voucher_profile" class="form-control" value="<?=htmlspecialchars($voucherAdminFilters['profile'])?>" placeholder="Profil">
                    <input type="search" name="voucher_search" class="form-control" value="<?=htmlspecialchars($voucherAdminFilters['search'])?>" placeholder="Username atau reseller">
                    <input type="date" name="voucher_date_from" class="form-control" value="<?=htmlspecialchars($voucherAdminFilters['date_from'])?>">
                    <input type="date" name="voucher_date_to" class="form-control" value="<?=htmlspecialchars($voucherAdminFilters['date_to'])?>">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> Filter</button>
                </form>
                <p class="text-muted">Total ditemukan: <?=count($allVoucherRows)?></p>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mk-card-table">
                        <thead><tr><th>No.</th><th>Reseller</th><th>Username</th><th>Profil</th><th>Router</th><th>Status</th><th>Dibuat</th><th>Catatan</th></tr></thead>
                        <tbody>
                        <?php foreach ($allVoucherRows as $i => $v): ?>
                        <tr>
                            <td data-label="No."><?=$i + 1?></td>
                            <td data-label="Reseller"><?=htmlspecialchars($v['reseller_name'] . ' (' . $v['reseller_username'] . ')')?></td>
                            <td data-label="Username"><strong><?=htmlspecialchars($v['username'])?></strong></td>
                            <td data-label="Profil"><?=htmlspecialchars($v['profile'])?></td>
                            <td data-label="Router"><?=htmlspecialchars($v['session_name'])?></td>
                            <td data-label="Status"><span class="label label-<?=$v['status'] === 'active' ? 'success' : 'default'?>"><?=htmlspecialchars($v['status'] === 'active' ? 'Aktif' : $v['status'])?></span></td>
                            <td data-label="Dibuat"><?=date('d/m/Y H:i', strtotime($v['created_at']))?></td>
                            <td data-label="Catatan"><small><?=htmlspecialchars($v['comment'])?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($allVoucherRows)): ?><tr><td colspan="8" class="text-center">Belum ada voucher tercatat.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
