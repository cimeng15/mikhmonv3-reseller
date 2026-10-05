<?php
/**
 * Mikhmon V3 - Reseller Dashboard
 */
$summaryToday = getTransactionSummary($reseller['id'], 'today');
$summaryMonth = getTransactionSummary($reseller['id'], 'month');
$summaryAll = getTransactionSummary($reseller['id'], 'all');
$recentTrx = getTransactions($reseller['id'], ['limit' => 10]);
?>

<div class="rs-page-head">
    <div>
        <h1>Halo, <?=htmlspecialchars(explode(' ', trim($reseller['name']))[0])?> 👋</h1>
        <p><span class="rs-status-dot"></span>Akun aktif · Pantau bisnis voucher Anda hari ini.</p>
    </div>
    <a href="index.php?page=generate" class="btn btn-primary"><i class="fa fa-plus"></i> Beli Voucher</a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="rs-balance-card">
            <div class="eyebrow">Saldo tersedia</div>
            <div class="amount">Rp <?=number_format($reseller['balance'], 0, ',', '.')?></div>
            <div class="meta">
                <span><i class="fa fa-percent"></i> Diskon <?=$reseller['discount']?>%</span>
                <span><i class="fa fa-server"></i> <?=count($allSessions)?> router tersedia</span>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="rs-metric-card">
            <div class="rs-metric-top"><span class="rs-metric-icon"><i class="fa fa-calendar-check-o"></i></span></div>
            <div>
                <div class="rs-metric-caption">Hari ini</div>
                <div class="rs-metric-value">Rp <?=number_format($summaryToday['purchase'], 0, ',', '.')?></div>
                <div class="rs-metric-foot"><?=intval($summaryToday['purchase_count'])?> transaksi</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="rs-metric-card">
            <div class="rs-metric-top"><span class="rs-metric-icon"><i class="fa fa-calendar"></i></span></div>
            <div>
                <div class="rs-metric-caption">Bulan ini</div>
                <div class="rs-metric-value">Rp <?=number_format($summaryMonth['purchase'], 0, ',', '.')?></div>
                <div class="rs-metric-foot"><?=intval($summaryMonth['purchase_count'])?> transaksi</div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="rs-metric-card">
            <div class="rs-metric-top"><span class="rs-metric-icon"><i class="fa fa-line-chart"></i></span></div>
            <div>
                <div class="rs-metric-caption">Total beli</div>
                <div class="rs-metric-value">Rp <?=number_format($summaryAll['purchase'], 0, ',', '.')?></div>
                <div class="rs-metric-foot"><?=intval($summaryAll['purchase_count'])?> transaksi</div>
            </div>
        </div>
    </div>
</div>

<div class="rs-section-title">
    <h2>Akses cepat</h2>
</div>
<div class="rs-quick-grid">
    <a href="index.php?page=generate" class="rs-quick-action">
        <span class="rs-quick-icon"><i class="fa fa-plus"></i></span>
        <span><strong>Beli voucher baru</strong><small>Pilih router dan profile voucher</small></span>
    </a>
    <a href="index.php?page=vouchers" class="rs-quick-action">
        <span class="rs-quick-icon"><i class="fa fa-ticket"></i></span>
        <span><strong>Voucher saya</strong><small>Lihat voucher aktif dan historis</small></span>
    </a>
    <a href="index.php?page=transactions" class="rs-quick-action">
        <span class="rs-quick-icon"><i class="fa fa-exchange"></i></span>
        <span><strong>Riwayat transaksi</strong><small>Pantau deposit dan pembelian</small></span>
    </a>
</div>

<div class="rs-section-title">
    <h2>Transaksi terakhir</h2>
    <a href="index.php?page=transactions">Lihat semua <i class="fa fa-arrow-right"></i></a>
</div>
<div class="panel panel-default">
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover rs-card-table">
                <thead>
                    <tr><th>Tanggal</th><th>Tipe</th><th>Jumlah</th><th>Saldo akhir</th><th>Keterangan</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                <?php if(empty($recentTrx)): ?>
                    <tr><td colspan="6" class="text-center">Belum ada transaksi. Mulai dengan membeli voucher pertama Anda.</td></tr>
                <?php else: foreach($recentTrx as $t): ?>
                    <tr>
                        <td data-label="Tanggal"><?=date('d M Y, H:i', strtotime($t['created_at']))?></td>
                        <td data-label="Tipe">
                            <?php if($t['type']=='deposit'): ?><span class="label label-success"><i class="fa fa-arrow-down"></i> Deposit</span>
                            <?php elseif($t['type']=='purchase'): ?><span class="label label-info"><i class="fa fa-shopping-cart"></i> Pembelian</span>
                            <?php else: ?><span class="label label-warning"><i class="fa fa-refresh"></i> Refund</span><?php endif; ?>
                        </td>
                        <td data-label="Jumlah"><strong>Rp <?=number_format($t['amount'], 0, ',', '.')?></strong></td>
                        <td data-label="Saldo akhir">Rp <?=number_format($t['balance_after'], 0, ',', '.')?></td>
                        <td data-label="Keterangan"><?=htmlspecialchars($t['description'])?></td>
                        <td data-label="Aksi">
                            <?php if($t['type']=='purchase' && !empty($t['voucher_data'])): ?>
                            <a href="index.php?page=print&trx_id=<?=$t['id']?>" class="btn btn-xs btn-default"><i class="fa fa-print"></i> Print</a>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
