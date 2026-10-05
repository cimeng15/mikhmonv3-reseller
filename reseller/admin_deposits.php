<?php
/**
 * Mikhmon V3 - Admin Deposit Management
 */
error_reporting(0);
if(!isset($_SESSION["mikhmon"])){echo "<script>window.location='./admin.php?id=login'</script>"; exit;}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/models.php';

$resellers = getAllResellers();
$filter_reseller = $_GET['reseller_id'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_from = $_GET['date_from'] ?? '';
$filter_to = $_GET['date_to'] ?? '';

$filters = [];
if ($filter_type) $filters['type'] = $filter_type;
if ($filter_from) $filters['date_from'] = $filter_from;
if ($filter_to) $filters['date_to'] = $filter_to;

$transactions = getTransactions($filter_reseller ?: null, $filters);
$summary = getTransactionSummary($filter_reseller ?: null);
?>

<div class="mk-admin-page-head">
    <div><h1>Saldo dan transaksi</h1><p>Tambah saldo reseller dan pantau seluruh arus transaksi.</p></div>
</div>
<?php include __DIR__ . '/admin_tabs.php'; ?>

<div class="row">
    <div class="col-md-4">
        <div class="panel panel-success mk-admin-stat">
            <div class="panel-body text-center">
                <h4><i class="fa fa-arrow-down text-success"></i> Total Deposit</h4>
                <h3 class="text-success">Rp <?=number_format($summary['deposit'], 0, ',', '.')?></h3>
                <small><?=$summary['deposit_count']?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-info mk-admin-stat">
            <div class="panel-body text-center">
                <h4><i class="fa fa-shopping-cart text-info"></i> Total Pembelian</h4>
                <h3 class="text-info">Rp <?=number_format($summary['purchase'], 0, ',', '.')?></h3>
                <small><?=$summary['purchase_count']?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-warning mk-admin-stat">
            <div class="panel-body text-center">
                <h4><i class="fa fa-refresh text-warning"></i> Total Refund</h4>
                <h3 class="text-warning">Rp <?=number_format($summary['refund'], 0, ',', '.')?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Quick Deposit -->
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading"><h4><i class="fa fa-money"></i> Tambah Deposit</h4></div>
            <div class="panel-body">
                <form class="form-inline" id="formQuickDeposit">
                    <div class="form-group">
                        <label>Reseller:</label>
                        <select name="reseller_id" class="form-control" required>
                            <option value="">Pilih reseller</option>
                            <?php foreach($resellers as $r): ?>
                            <option value="<?=$r['id']?>"><?=htmlspecialchars($r['name'])?> (Saldo: Rp <?=number_format($r['balance'],0,',','.')?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jumlah (Rp):</label>
                        <input type="number" name="amount" class="form-control" required min="1000" step="1000" style="width:150px" placeholder="50000">
                    </div>
                    <div class="form-group">
                        <label>Keterangan:</label>
                        <input type="text" name="description" class="form-control" placeholder="Transfer bank atau tunai" style="width:200px">
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Deposit</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Transaction List -->
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><i class="fa fa-list"></i> Riwayat Transaksi</h4>
            </div>
            <div class="panel-body">
                <!-- Filters -->
                <form class="form-inline" style="margin-bottom:15px" method="GET">
                    <input type="hidden" name="id" value="reseller-deposits">
                    <div class="form-group">
                        <select name="reseller_id" class="form-control">
                            <option value="">Semua Reseller</option>
                            <?php foreach($resellers as $r): ?>
                            <option value="<?=$r['id']?>" <?=$filter_reseller==$r['id']?'selected':''?>><?=htmlspecialchars($r['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                    <a href="?id=reseller-deposits" class="btn btn-default"><i class="fa fa-refresh"></i> Reset</a>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mk-card-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Reseller</th>
                                <th>Tipe</th>
                                <th>Jumlah</th>
                                <th>Saldo Sebelum</th>
                                <th>Saldo Sesudah</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if(empty($transactions)): ?>
                            <tr><td colspan="7" class="text-center">Belum ada transaksi</td></tr>
                        <?php else: ?>
                            <?php foreach($transactions as $t): ?>
                            <tr>
                                <td data-label="Tanggal"><?=date('d/m/Y H:i', strtotime($t['created_at']))?></td>
                                <td data-label="Reseller"><?=htmlspecialchars($t['reseller_name'] ?? '-')?></td>
                                <td data-label="Tipe">
                                    <?php if($t['type']=='deposit'): ?>
                                        <span class="label label-success">Deposit</span>
                                    <?php elseif($t['type']=='purchase'): ?>
                                        <span class="label label-info">Pembelian</span>
                                    <?php else: ?>
                                        <span class="label label-warning">Refund</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Jumlah" class="text-right">Rp <?=number_format($t['amount'], 0, ',', '.')?></td>
                                <td data-label="Saldo sebelum" class="text-right">Rp <?=number_format($t['balance_before'], 0, ',', '.')?></td>
                                <td data-label="Saldo sesudah" class="text-right">Rp <?=number_format($t['balance_after'], 0, ',', '.')?></td>
                                <td data-label="Keterangan"><?=htmlspecialchars($t['description'])?></td>
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

<script>
$('#formQuickDeposit').on('submit', function(e) {
    e.preventDefault();
    $.post('./reseller/process/admin_process.php', $(this).serialize() + '&action=add_deposit', function(res) {
        if (res.success) {
            alert(res.message);
            location.reload();
        } else {
            alert('Error: ' + res.message);
        }
    }, 'json');
});
</script>
