<?php
/**
 * Mikhmon V3 - Admin Reseller Reports
 */
error_reporting(0);
if(!isset($_SESSION["mikhmon"])){echo "<script>window.location='./admin.php?id=login'</script>"; exit;}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/models.php';

$resellers = getAllResellers();
$summaryAll = getTransactionSummary(null, 'all');
$summaryMonth = getTransactionSummary(null, 'month');
$summaryToday = getTransactionSummary(null, 'today');
$totalBalance = array_sum(array_column($resellers, 'balance'));
?>

<div class="row">
    <div class="col-md-3">
        <div class="panel panel-primary">
            <div class="panel-body text-center">
                <i class="fa fa-users fa-2x"></i>
                <h4>Total Reseller</h4>
                <h2><?=count($resellers)?></h2>
                <small><?=count(array_filter($resellers, function($r){return $r['status']==='active';}))?> aktif</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-success">
            <div class="panel-body text-center">
                <i class="fa fa-money fa-2x"></i>
                <h4>Total Saldo</h4>
                <h2>Rp <?=number_format($totalBalance, 0, ',', '.')?></h2>
                <small>saldo seluruh reseller</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-info">
            <div class="panel-body text-center">
                <i class="fa fa-shopping-cart fa-2x"></i>
                <h4>Penjualan Hari Ini</h4>
                <h2>Rp <?=number_format($summaryToday['purchase'], 0, ',', '.')?></h2>
                <small><?=$summaryToday['purchase_count']?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-warning">
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
                    <table class="table table-bordered table-striped">
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
                                <td><strong><?=htmlspecialchars($r['name'])?></strong><br><small class="text-muted"><?=htmlspecialchars($r['username'])?></small></td>
                                <td class="text-center">
                                    <span class="label label-<?=$r['status']==='active'?'success':'danger'?>"><?=$r['status']?></span>
                                </td>
                                <td class="text-right">Rp <?=number_format($r['balance'], 0, ',', '.')?></td>
                                <td class="text-right text-success">Rp <?=number_format($sAll['deposit'], 0, ',', '.')?></td>
                                <td class="text-right text-info">Rp <?=number_format($sAll['purchase'], 0, ',', '.')?></td>
                                <td class="text-center"><?=$sAll['deposit_count'] + $sAll['purchase_count']?></td>
                                <td class="text-right">Rp <?=number_format($sMonth['deposit'], 0, ',', '.')?></td>
                                <td class="text-right">Rp <?=number_format($sMonth['purchase'], 0, ',', '.')?></td>
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
