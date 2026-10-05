<?php
/**
 * Mikhmon V3 - Reseller Transactions
 */
$filter_type = $_GET['type'] ?? '';
$filter_from = $_GET['date_from'] ?? '';
$filter_to = $_GET['date_to'] ?? '';

$filters = ['limit' => 200];
if ($filter_type) $filters['type'] = $filter_type;
if ($filter_from) $filters['date_from'] = $filter_from;
if ($filter_to) $filters['date_to'] = $filter_to;

$transactions = getTransactions($reseller['id'], $filters);
$summary = getTransactionSummary($reseller['id']);
?>

<div class="row">
    <div class="col-md-12">
        <h3><i class="fa fa-history"></i> Riwayat Transaksi</h3>
        <hr>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="panel panel-success">
            <div class="panel-body text-center">
                <h5>Total Deposit</h5>
                <h3 class="text-success">Rp <?=number_format($summary['deposit'], 0, ',', '.')?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-info">
            <div class="panel-body text-center">
                <h5>Total Pembelian</h5>
                <h3 class="text-info">Rp <?=number_format($summary['purchase'], 0, ',', '.')?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-warning">
            <div class="panel-body text-center">
                <h5>Total Refund</h5>
                <h3 class="text-warning">Rp <?=number_format($summary['refund'], 0, ',', '.')?></h3>
            </div>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h4><i class="fa fa-list"></i> Daftar Transaksi</h4>
    </div>
    <div class="panel-body">
        <!-- Filter -->
        <form method="GET" class="form-inline" style="margin-bottom:15px">
            <input type="hidden" name="page" value="transactions">
            <div class="form-group">
                <select name="type" class="form-control">
                    <option value="">Semua Tipe</option>
                    <option value="deposit" <?=$filter_type=='deposit'?'selected':''?>>Deposit</option>
                    <option value="purchase" <?=$filter_type=='purchase'?'selected':''?>>Pembelian</option>
                    <option value="refund" <?=$filter_type=='refund'?'selected':''?>>Refund</option>
                </select>
            </div>
            <div class="form-group">
                <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($filter_from)?>" placeholder="Dari">
            </div>
            <div class="form-group">
                <input type="date" name="date_to" class="form-control" value="<?=htmlspecialchars($filter_to)?>" placeholder="Sampai">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
            <a href="index.php?page=transactions" class="btn btn-default"><i class="fa fa-refresh"></i></a>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Jumlah</th>
                        <th>Saldo Sebelum</th>
                        <th>Saldo Sesudah</th>
                        <th>Router</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($transactions)): ?>
                    <tr><td colspan="8" class="text-center">Tidak ada transaksi</td></tr>
                <?php else: ?>
                    <?php foreach($transactions as $t): ?>
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
                        <td class="text-right">Rp <?=number_format($t['balance_before'], 0, ',', '.')?></td>
                        <td class="text-right">Rp <?=number_format($t['balance_after'], 0, ',', '.')?></td>
                        <td><?=htmlspecialchars($t['session_name'] ?? '-')?></td>
                        <td><?=htmlspecialchars($t['description'])?></td>
                        <td>
                            <?php if($t['type']=='purchase' && !empty($t['voucher_data'])): ?>
                            <a href="index.php?page=print&trx_id=<?=$t['id']?>" class="btn btn-xs btn-default" target="_blank">
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
