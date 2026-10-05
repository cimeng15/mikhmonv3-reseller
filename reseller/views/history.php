<?php
/*
 *  Mikhmon V3 — Reseller Panel · Transaction History
 *  Paginated table with date-range filter and CSV export.
 */

$cekindo = ['indo'=>['RP','Rp','rp','IDR','idr','RP.','Rp.','rp.','IDR.','idr.']];
function fmtCur2($amount, $cur) {
    global $cekindo;
    if (in_array($cur, $cekindo['indo'])) {
        return $cur . ' ' . number_format((float)$amount, 0, ',', '.');
    }
    return $cur . ' ' . number_format((float)$amount, 2);
}

$txAll = reseller_tx_list($reseller['username']);

// ── Filters ──────────────────────────────────────────
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to']   ?? '';
$search   = $_GET['q']    ?? '';

$filtered = $txAll;
if ($dateFrom !== '') {
    $filtered = array_filter($filtered, function($tx) use ($dateFrom) {
        return substr($tx['timestamp'], 0, 10) >= $dateFrom;
    });
}
if ($dateTo !== '') {
    $filtered = array_filter($filtered, function($tx) use ($dateTo) {
        return substr($tx['timestamp'], 0, 10) <= $dateTo;
    });
}
if ($search !== '') {
    $q = strtolower($search);
    $filtered = array_filter($filtered, function($tx) use ($q) {
        return strpos(strtolower($tx['profile']), $q) !== false
            || strpos(strtolower($tx['comment'] ?? ''), $q) !== false;
    });
}
$filtered = array_values($filtered);

// ── Pagination ───────────────────────────────────────
$perPage   = 20;
$totalRows = count($filtered);
$totalPages= max(1, ceil($totalRows / $perPage));
$curPage   = max(1, min($totalPages, (int)($_GET['pg'] ?? 1)));
$offset    = ($curPage - 1) * $perPage;
$rows      = array_slice($filtered, $offset, $perPage);

// summary
$totalAmountFiltered = array_sum(array_column($filtered, 'total_cost'));
$totalQtyFiltered    = array_sum(array_column($filtered, 'qty'));

// ── CSV Export ───────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reseller_transactions_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#','Date','Session','Profile','Qty','Unit Price','Total','Comment','Usernames']);
    $n = 0;
    foreach ($filtered as $tx) {
        $n++;
        $unames = [];
        if (!empty($tx['users'])) {
            foreach ($tx['users'] as $u) $unames[] = $u['username'];
        }
        fputcsv($out, [
            $n,
            $tx['timestamp'],
            $tx['session'] ?? '',
            $tx['profile'],
            $tx['qty'],
            $tx['unit_price'],
            $tx['total_cost'],
            $tx['comment'] ?? '',
            implode('; ', $unames),
        ]);
    }
    fclose($out);
    exit;
}
?>

<div class="card" style="padding:18px">
  <h4 style="margin-top:0"><i class="fa fa-history"></i> Transaction History</h4>

  <!-- Filter bar -->
  <form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-bottom:12px">
    <input type="hidden" name="page" value="history">
    <input type="hidden" name="session" value="<?= htmlspecialchars($sess) ?>">
    <div>
      <label style="font-size:12px;display:block">From</label>
      <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" style="padding:6px 10px;border:1px solid rgba(128,128,128,.3);border-radius:4px">
    </div>
    <div>
      <label style="font-size:12px;display:block">To</label>
      <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" style="padding:6px 10px;border:1px solid rgba(128,128,128,.3);border-radius:4px">
    </div>
    <div>
      <label style="font-size:12px;display:block">Search (profile / comment)</label>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="keyword" style="padding:6px 10px;border:1px solid rgba(128,128,128,.3);border-radius:4px">
    </div>
    <div>
      <button type="submit" class="bg-primary pointer" style="border:0;padding:7px 18px;border-radius:4px;font-weight:600;cursor:pointer"><i class="fa fa-filter"></i> Filter</button>
    </div>
    <div>
      <a href="./reseller.php?page=history&session=<?= $sess ?>&from=<?= $dateFrom ?>&to=<?= $dateTo ?>&q=<?= urlencode($search) ?>&export=csv"
         class="bg-primary pointer" style="display:inline-block;padding:7px 18px;border-radius:4px;font-weight:600;text-decoration:none;font-size:14px"><i class="fa fa-download"></i> CSV</a>
    </div>
  </form>

  <!-- Summary -->
  <div style="margin-bottom:10px;font-size:14px;opacity:.8">
    Showing <strong><?= $totalRows ?></strong> transaction(s).
    Total qty: <strong><?= number_format($totalQtyFiltered) ?></strong>.
    Total amount: <strong><?= fmtCur2($totalAmountFiltered, $currency) ?></strong>.
  </div>

  <!-- Table -->
  <?php if(empty($rows)): ?>
    <p style="opacity:.6">No transactions match your filter.</p>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="r-table">
    <thead>
      <tr>
        <th>#</th><th>Date</th><th>Session</th><th>Profile</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Comment</th><th>Details</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $n = $offset;
    foreach ($rows as $tx):
      $n++;
      $txId = $tx['id'] ?? $n;
    ?>
      <tr>
        <td><?= $n ?></td>
        <td style="white-space:nowrap"><?= htmlspecialchars($tx['timestamp']) ?></td>
        <td><?= htmlspecialchars($tx['session'] ?? '') ?></td>
        <td><?= htmlspecialchars($tx['profile']) ?></td>
        <td><?= (int)$tx['qty'] ?></td>
        <td><?= fmtCur2($tx['unit_price'], $currency) ?></td>
        <td><strong><?= fmtCur2($tx['total_cost'], $currency) ?></strong></td>
        <td><?= htmlspecialchars($tx['comment'] ?? '') ?></td>
        <td>
          <a href="javascript:void(0)" onclick="toggleDetail('<?= $txId ?>')" title="Show generated users"><i class="fa fa-eye"></i></a>
        </td>
      </tr>
      <tr id="detail-<?= $txId ?>" style="display:none">
        <td colspan="9" style="background:rgba(128,128,128,.04);padding:10px 18px">
          <?php if(!empty($tx['users'])): ?>
            <table class="r-table" style="margin:0">
              <tr><th>Username</th><th>Password</th></tr>
              <?php foreach($tx['users'] as $u): ?>
                <tr>
                  <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                  <td><code><?= htmlspecialchars($u['password']) ?></code></td>
                </tr>
              <?php endforeach; ?>
            </table>
            <div style="margin-top:8px">
              <button onclick="printVouchers2('<?= htmlspecialchars($tx['comment'] ?? '') ?>','<?= htmlspecialchars($tx['session'] ?? $sess) ?>')"
                class="bg-primary pointer" style="border:0;padding:5px 14px;border-radius:4px;font-weight:600;cursor:pointer;font-size:13px">
                <i class="fa fa-print"></i> Re-print
              </button>
            </div>
          <?php else: ?>
            <em style="opacity:.6">User details not available.</em>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <!-- Pagination -->
  <?php if($totalPages > 1): ?>
  <div class="r-pagination">
    <?php
    $base = "./reseller.php?page=history&session={$sess}&from={$dateFrom}&to={$dateTo}&q=" . urlencode($search);
    if ($curPage > 1):
    ?>
      <a href="<?= $base ?>&pg=<?= $curPage-1 ?>">&laquo; Prev</a>
    <?php endif; ?>
    <?php for ($i = max(1,$curPage-3); $i <= min($totalPages, $curPage+3); $i++): ?>
      <a href="<?= $base ?>&pg=<?= $i ?>" class="<?= $i===$curPage?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($curPage < $totalPages): ?>
      <a href="<?= $base ?>&pg=<?= $curPage+1 ?>">Next &raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<script>
function toggleDetail(id){
  var el=document.getElementById('detail-'+id);
  el.style.display=el.style.display==='none'?'table-row':'none';
}
function printVouchers2(comment,session){
  window.open('./voucher/print.php?id='+encodeURIComponent(comment)+'&session='+encodeURIComponent(session),'_blank','width=800,height=600');
}
</script>
