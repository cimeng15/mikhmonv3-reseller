<?php
/*
 *  Mikhmon V3 — Reseller Panel · Dashboard
 *  Shows: balance, today's sales, total vouchers generated, recent transactions.
 */

// Connect to RouterOS for live stats
$API = new RouterosAPI();
$API->debug = false;
$connected = $API->connect($iphost, $userhost, decrypt($passwdhost));

$identity = '';
$totalUsers = 0;
$activeUsers = 0;
if ($connected) {
    $getid = $API->comm("/system/identity/print");
    $identity = $getid[0]['name'];
    $allu = $API->comm("/ip/hotspot/user/print");
    $totalUsers = count($allu);
    $actu = $API->comm("/ip/hotspot/active/print");
    $activeUsers = count($actu);
}

// Reseller stats from transaction log
$txAll = reseller_tx_list($reseller['username']);
$today = date('Y-m-d');
$todaySales  = 0;
$todayQty    = 0;
$totalVouchers = 0;
$totalSpent    = 0;
foreach ($txAll as $tx) {
    $totalVouchers += (int)$tx['qty'];
    $totalSpent    += (float)$tx['total_cost'];
    if (substr($tx['timestamp'], 0, 10) === $today) {
        $todaySales += (float)$tx['total_cost'];
        $todayQty  += (int)$tx['qty'];
    }
}

$cekindo = ['indo'=>['RP','Rp','rp','IDR','idr','RP.','Rp.','rp.','IDR.','idr.']];
function fmtCurrency($amount, $cur) {
    global $cekindo;
    if (in_array($cur, $cekindo['indo'])) {
        return $cur . ' ' . number_format((float)$amount, 0, ',', '.');
    }
    return $cur . ' ' . number_format((float)$amount, 2);
}
?>

<!-- ── Dashboard Cards ─────────────────────────────── -->
<div class="r-cards">
  <div class="r-card card">
    <i class="fa fa-money"></i>
    <h4>Balance (Saldo)</h4>
    <div class="r-val"><?= fmtCurrency($reseller['balance'], $currency) ?></div>
  </div>
  <div class="r-card card">
    <i class="fa fa-shopping-cart"></i>
    <h4>Today's Sales</h4>
    <div class="r-val"><?= fmtCurrency($todaySales, $currency) ?></div>
    <small><?= $todayQty ?> voucher(s)</small>
  </div>
  <div class="r-card card">
    <i class="fa fa-ticket"></i>
    <h4>Total Vouchers Generated</h4>
    <div class="r-val"><?= number_format($totalVouchers) ?></div>
  </div>
  <div class="r-card card">
    <i class="fa fa-wifi"></i>
    <h4>Hotspot Active</h4>
    <div class="r-val"><?= $activeUsers ?></div>
    <small>of <?= $totalUsers ?> total users</small>
  </div>
</div>

<!-- ── Connection Status ───────────────────────────── -->
<div class="r-cards">
  <div class="r-card card" style="flex:1 1 100%">
    <table class="r-table">
      <tr>
        <td style="width:140px"><strong><i class="fa fa-server"></i> Router</strong></td>
        <td><?= htmlspecialchars($identity ?: '—') ?></td>
        <td style="text-align:right">
          <?php if($connected): ?>
            <span class="r-badge r-badge-ok"><i class="fa fa-check"></i> Connected</span>
          <?php else: ?>
            <span class="r-badge r-badge-off"><i class="fa fa-times"></i> Not Connected</span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <td><strong><i class="fa fa-user-circle"></i> Account</strong></td>
        <td><?= htmlspecialchars($reseller['name']) ?> (<code><?= htmlspecialchars($reseller['username']) ?></code>)</td>
        <td style="text-align:right">
          <span class="r-badge r-badge-ok"><?= ucfirst($reseller['status']) ?></span>
          <?php if($reseller['discount'] > 0): ?>
            &nbsp;<span class="r-badge r-badge-warn"><?= $reseller['discount'] ?>% discount</span>
          <?php endif; ?>
        </td>
      </tr>
    </table>
  </div>
</div>

<!-- ── Quick Actions ───────────────────────────────── -->
<div class="r-cards">
  <a href="./reseller.php?page=generate&session=<?= $sess ?>" class="r-card card" style="text-decoration:none;text-align:center">
    <i class="fa fa-ticket" style="font-size:40px;opacity:.6"></i>
    <h4 style="margin-top:10px">Generate Voucher</h4>
  </a>
  <a href="./reseller.php?page=history&session=<?= $sess ?>" class="r-card card" style="text-decoration:none;text-align:center">
    <i class="fa fa-history" style="font-size:40px;opacity:.6"></i>
    <h4 style="margin-top:10px">Transaction History</h4>
  </a>
</div>

<!-- ── Recent Transactions (last 10) ───────────────── -->
<div class="card" style="padding:15px;margin-top:5px">
  <h4 style="margin-top:0"><i class="fa fa-clock-o"></i> Recent Transactions</h4>
  <?php if(empty($txAll)): ?>
    <p style="opacity:.6">No transactions yet.</p>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="r-table">
    <thead>
      <tr>
        <th>#</th><th>Date</th><th>Profile</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Comment</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $recent = array_slice($txAll, 0, 10);
    $n = 0;
    foreach ($recent as $tx):
      $n++;
    ?>
      <tr>
        <td><?= $n ?></td>
        <td><?= htmlspecialchars($tx['timestamp']) ?></td>
        <td><?= htmlspecialchars($tx['profile']) ?></td>
        <td><?= (int)$tx['qty'] ?></td>
        <td><?= fmtCurrency($tx['unit_price'], $currency) ?></td>
        <td><strong><?= fmtCurrency($tx['total_cost'], $currency) ?></strong></td>
        <td><?= htmlspecialchars($tx['comment'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if(count($txAll) > 10): ?>
    <div style="text-align:center;margin-top:10px">
      <a href="./reseller.php?page=history&session=<?= $sess ?>">View all &raquo;</a>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
