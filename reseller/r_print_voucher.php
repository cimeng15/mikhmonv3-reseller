<?php
/**
 * Mikhmon V3 - Reseller Print Voucher
 */
$trx_id = intval($_GET['trx_id'] ?? 0);
$trx = null;
$vouchers = [];

if ($trx_id > 0) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND reseller_id = ? AND type = 'purchase'");
    $stmt->execute([$trx_id, $reseller['id']]);
    $trx = $stmt->fetch();

    if ($trx && !empty($trx['voucher_data'])) {
        $vouchers = json_decode($trx['voucher_data'], true) ?: [];
    }
}

if (empty($vouchers)) {
    echo '<div class="alert alert-warning"><i class="fa fa-warning"></i> Tidak ada data voucher untuk dicetak.</div>';
    echo '<a href="index.php?page=transactions" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</a>';
    return;
}
?>

<style>
@media print {
    .no-print { display: none !important; }
    .voucher-card { border: 1px dashed #333 !important; page-break-inside: avoid; }
}
.voucher-card {
    border: 2px dashed #ccc;
    padding: 15px;
    margin: 5px;
    text-align: center;
    border-radius: 8px;
    display: inline-block;
    width: 220px;
    vertical-align: top;
}
.voucher-card .voucher-code {
    font-size: 20px;
    font-weight: bold;
    font-family: monospace;
    letter-spacing: 2px;
    margin: 10px 0;
    padding: 8px;
    background: #f5f5f5;
    border-radius: 4px;
}
.voucher-card .voucher-profile {
    font-size: 12px;
    color: #888;
}
</style>

<div class="no-print" style="margin-bottom:15px">
    <h3><i class="fa fa-print"></i> Cetak Voucher</h3>
    <p>Transaksi #<?=$trx['id']?> | <?=date('d/m/Y H:i', strtotime($trx['created_at']))?> | <?=count($vouchers)?> voucher</p>
    <button onclick="window.print()" class="btn btn-primary"><i class="fa fa-print"></i> Print</button>
    <a href="index.php?page=transactions" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</a>
    <hr>
</div>

<div id="voucherPrint">
    <?php foreach($vouchers as $v): ?>
    <div class="voucher-card">
        <div><strong>WiFi Voucher</strong></div>
        <div class="voucher-code"><?=htmlspecialchars($v['username'])?></div>
        <div class="voucher-profile">
            Profil: <?=htmlspecialchars($v['profile'])?><br>
            <?php if(!empty($v['price'])): ?>
            Harga: Rp <?=number_format($v['price'], 0, ',', '.')?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
