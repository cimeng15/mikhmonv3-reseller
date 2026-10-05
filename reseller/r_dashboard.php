<?php
/**
 * Mikhmon V3 - Reseller Dashboard
 */
$summaryToday = getTransactionSummary($reseller['id'], 'today');
$summaryMonth = getTransactionSummary($reseller['id'], 'month');
$summaryAll = getTransactionSummary($reseller['id'], 'all');
$recentTrx = getTransactions($reseller['id'], ['limit' => 10]);
?>

<div class="row">
    <div class="col-md-12">
        <h3><i class="fa fa-dashboard"></i> Dashboard
            <small>Selamat datang, <strong><?=htmlspecialchars($reseller['name'])?></strong></small>
        </h3>
        <hr>
    </div>
</div>

<!-- Balance & Stats -->
<div class="row">
    <div class="col-md-3">
        <div class="panel panel-success">
            <div class="panel-body stat-box">
                <i class="fa fa-money fa-2x text-success"></i>
                <h4>Saldo Anda</h4>
                <h2 class="text-success">Rp <?=number_format($reseller['balance'], 0, ',', '.')?></h2>
                <?php if($reseller['discount'] > 0): ?>
                <small class="text-info">Diskon: <?=$reseller['discount']?>%</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-info">
            <div class="panel-body stat-box">
                <i class="fa fa-shopping-cart fa-2x text-info"></i>
                <h4>Pembelian Hari Ini</h4>
                <h2>Rp <?=number_format($summaryToday['purchase'], 0, ',', '.')?></h2>
                <small><?=$summaryToday['purchase_count']?> voucher</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-primary">
            <div class="panel-body stat-box">
                <i class="fa fa-calendar fa-2x"></i>
                <h4>Pembelian Bulan Ini</h4>
                <h2>Rp <?=number_format($summaryMonth['purchase'], 0, ',', '.')?></h2>
                <small><?=$summaryMonth['purchase_count']?> voucher</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-body stat-box">
                <i class="fa fa-bar-chart fa-2x"></i>
                <h4>Total Pembelian</h4>
                <h2>Rp <?=number_format($summaryAll['purchase'], 0, ',', '.')?></h2>
                <small><?=$summaryAll['purchase_count']?> voucher total</small>
            </div>
        </div>
    </div>
</div>

<!-- Quick Action -->
<div class="row">
    <div class="col-md-4">
        <a href="index.php?page=generate" class="btn btn-primary btn-lg btn-block" style="padding:20px">
            <i class="fa fa-ticket fa-2x"></i><br>
            Beli Voucher Baru
        </a>
    </div>
    <div class="col-md-4">
        <a href="index.php?page=transactions" class="btn btn-info btn-lg btn-block" style="padding:20px">
            <i class="fa fa-history fa-2x"></i><br>
            Riwayat Transaksi
        </a>
    </div>
    <div class="col-md-4">
        <a href="index.php?page=profile" class="btn btn-default btn-lg btn-block" style="padding:20px">
            <i class="fa fa-user fa-2x"></i><br>
            Profil Saya
        </a>
    </div>
</div>

<br>

<!-- Recent Transactions -->
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading"><h4><i class="fa fa-clock-o"></i> Transaksi Terakhir</h4></div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Tipe</th>
                                <th>Jumlah</th>
                                <th>Saldo</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(empty($recentTrx)): ?>
                            <tr><td colspan="6" class="text-center">Belum ada transaksi</td></tr>
                        <?php else: ?>
                            <?php foreach($recentTrx as $t): ?>
                            <tr>
                                <td><?=date('d/m/Y H:i', strtotime($t['created_at']))?></td>
                                <td>
                                    <?php if($t['type']=='deposit'): ?>
                                        <span class="label label-success"><i class="fa fa-arrow-down"></i> Deposit</span>
                                    <?php elseif($t['type']=='purchase'): ?>
                                        <span class="label label-info"><i class="fa fa-shopping-cart"></i> Pembelian</span>
                                    <?php else: ?>
                                        <span class="label label-warning"><i class="fa fa-refresh"></i> Refund</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">Rp <?=number_format($t['amount'], 0, ',', '.')?></td>
                                <td class="text-right">Rp <?=number_format($t['balance_after'], 0, ',', '.')?></td>
                                <td><?=htmlspecialchars($t['description'])?></td>
                                <td>
                                    <?php if($t['type']=='purchase' && !empty($t['voucher_data'])): ?>
                                    <a href="index.php?page=print&trx_id=<?=$t['id']?>" class="btn btn-xs btn-default">
                                        <i class="fa fa-print"></i> Print
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
