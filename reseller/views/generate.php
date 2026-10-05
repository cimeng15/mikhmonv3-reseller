<?php
/*
 *  Mikhmon V3 — Reseller Panel · Generate Voucher
 *
 *  1. Pick a user-profile → see price, validity, limits.
 *  2. Choose qty + comment prefix.
 *  3. Submit → RouterOS API generates hotspot users; balance is deducted;
 *     a transaction record is saved.
 */

$API = new RouterosAPI();
$API->debug = false;
$connected = $API->connect($iphost, $userhost, decrypt($passwdhost));

$profiles = [];
if ($connected) {
    $raw = $API->comm("/ip/hotspot/user/profile/print");
    foreach ($raw as $p) {
        $name = $p['name'];
        // respect allowed_profiles filter
        if (!empty($reseller['allowed_profiles']) && !in_array($name, $reseller['allowed_profiles'])) continue;
        if ($name === 'default') continue; // skip default profile
        $ponlogin  = $p['on-login'];
        $parts     = explode(',', $ponlogin);
        $validity  = isset($parts[3]) ? $parts[3] : '';
        $price     = isset($parts[2]) ? (float)$parts[2] : 0;
        $sprice    = isset($parts[4]) ? (float)$parts[4] : 0;
        $usePrice  = ($sprice > 0) ? $sprice : $price;
        $profiles[] = [
            'name'      => $name,
            'price'     => $price,
            'sprice'    => $sprice,
            'use_price' => $usePrice,
            'validity'  => $validity,
            'shared'    => $p['shared-users'],
            'ratelimit' => $p['rate-limit'] ?? '',
        ];
    }
}

$cekindo = ['indo'=>['RP','Rp','rp','IDR','idr','RP.','Rp.','rp.','IDR.','idr.']];
function fmtCur($amount, $cur) {
    global $cekindo;
    if (in_array($cur, $cekindo['indo'])) {
        return $cur . ' ' . number_format((float)$amount, 0, ',', '.');
    }
    return $cur . ' ' . number_format((float)$amount, 2);
}

// ── POST: do the generation ──────────────────────────
$result = null;
$genComment = '';
if (isset($_POST['do_generate']) && $connected) {
    $profName = $_POST['profile'];
    $qty      = max(1, min(100, (int)$_POST['qty']));
    $prefix   = preg_replace('/[^a-zA-Z0-9\-]/', '', $_POST['prefix'] ?? '');
    $charType = $_POST['char_type'] ?? 'lower';  // lower | upper | number | mix
    $nameLen  = max(4, min(12, (int)($_POST['name_len'] ?? 6)));
    $genComment = $prefix !== '' ? $prefix : ('reseller-' . $reseller['username']);

    // Find profile info
    $profileInfo = null;
    foreach ($profiles as $pi) {
        if ($pi['name'] === $profName) { $profileInfo = $pi; break; }
    }
    if (!$profileInfo) {
        $result = ['error' => 'Profile not found or not allowed.'];
    } else {
        $unitPrice = $profileInfo['use_price'];
        // apply reseller discount
        if ($reseller['discount'] > 0) {
            $unitPrice = $unitPrice * (1 - $reseller['discount'] / 100);
        }
        $totalCost = $unitPrice * $qty;

        // check balance
        if ($reseller['balance'] < $totalCost) {
            $result = ['error' => 'Insufficient balance. Need ' . fmtCur($totalCost, $currency) . ' but your balance is ' . fmtCur($reseller['balance'], $currency) . '.'];
        } else {
            // generate users via RouterOS API
            $charset = '';
            switch ($charType) {
                case 'upper':  $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; break;
                case 'number': $charset = '23456789'; break;
                case 'mix':    $charset = 'abcdefghjkmnpqrstuvwxyz23456789'; break;
                default:       $charset = 'abcdefghjkmnpqrstuvwxyz'; break;
            }

            $generated = [];
            for ($i = 0; $i < $qty; $i++) {
                // random username
                $uname = '';
                for ($c = 0; $c < $nameLen; $c++) {
                    $uname .= $charset[random_int(0, strlen($charset)-1)];
                }
                // random password
                $pword = '';
                for ($c = 0; $c < $nameLen; $c++) {
                    $pword .= $charset[random_int(0, strlen($charset)-1)];
                }

                if ($prefix !== '') {
                    $uname = $prefix . '-' . $uname;
                }

                $API->comm("/ip/hotspot/user/add", [
                    "=name"     => $uname,
                    "=password" => $pword,
                    "=profile"  => $profName,
                    "=comment"  => $genComment,
                    "=server"   => "all",
                ]);

                $generated[] = ['username' => $uname, 'password' => $pword];
            }

            // deduct balance
            $reseller['balance'] -= $totalCost;
            reseller_save($reseller);

            // log transaction
            $tx = reseller_tx_add($reseller['username'], [
                'session'    => $sess,
                'profile'    => $profName,
                'qty'        => $qty,
                'unit_price' => $unitPrice,
                'total_cost' => $totalCost,
                'comment'    => $genComment,
                'prefix'     => $prefix,
                'users'      => $generated,
            ]);

            $result = [
                'success'   => true,
                'tx'        => $tx,
                'generated' => $generated,
                'totalCost' => $totalCost,
            ];
        }
    }
}
?>

<!-- ── Profile selector ────────────────────────────── -->
<div class="card" style="padding:18px">
<h4 style="margin-top:0"><i class="fa fa-ticket"></i> Generate Voucher</h4>

<?php if(!$connected): ?>
  <div class="r-error"><i class="fa fa-exclamation-triangle"></i> Not connected to router <strong><?= htmlspecialchars($sess) ?></strong>. Check session settings.</div>
<?php elseif(empty($profiles)): ?>
  <div class="r-error"><i class="fa fa-exclamation-triangle"></i> No user profiles found (or none allowed for your account).</div>
<?php else: ?>

  <?php if($result && isset($result['error'])): ?>
    <div class="r-error"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($result['error']) ?></div>
  <?php endif; ?>

  <?php if($result && !empty($result['success'])): ?>
    <div class="r-success">
      <i class="fa fa-check-circle"></i>
      <strong><?= $qty ?></strong> voucher(s) generated for profile <strong><?= htmlspecialchars($profName) ?></strong>.
      Total cost: <strong><?= fmtCur($result['totalCost'], $currency) ?></strong>.
      New balance: <strong><?= fmtCur($reseller['balance'], $currency) ?></strong>.
    </div>

    <!-- Generated vouchers table -->
    <div style="overflow-x:auto;margin-bottom:15px">
    <table class="r-table" id="generated-table">
      <thead>
        <tr><th>#</th><th>Username</th><th>Password</th><th>Profile</th></tr>
      </thead>
      <tbody>
      <?php $n=0; foreach($result['generated'] as $g): $n++; ?>
        <tr>
          <td><?= $n ?></td>
          <td><code><?= htmlspecialchars($g['username']) ?></code></td>
          <td><code><?= htmlspecialchars($g['password']) ?></code></td>
          <td><?= htmlspecialchars($profName) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <button onclick="printVouchers('<?= $genComment ?>','<?= $sess ?>')" class="r-btn bg-primary pointer" style="border:0;padding:8px 22px;border-radius:5px;font-weight:700;cursor:pointer">
      <i class="fa fa-print"></i> Print Vouchers
    </button>
    <hr>
  <?php endif; ?>

  <form method="post" class="r-form" action="./reseller.php?page=generate&session=<?= $sess ?>">
    <label><i class="fa fa-pie-chart"></i> User Profile</label>
    <select name="profile" id="r-profile" onchange="showProfileInfo()">
      <option value="">— Select Profile —</option>
      <?php foreach($profiles as $p): ?>
        <option value="<?= htmlspecialchars($p['name']) ?>"
                data-price="<?= $p['use_price'] ?>"
                data-validity="<?= htmlspecialchars($p['validity']) ?>"
                data-shared="<?= htmlspecialchars($p['shared']) ?>"
                data-rate="<?= htmlspecialchars($p['ratelimit']) ?>"
                <?= (isset($_POST['profile']) && $_POST['profile']===$p['name'])?'selected':'' ?>>
          <?= htmlspecialchars($p['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div id="r-profile-info" class="r-info" style="display:none;margin:10px 0;padding:10px;border-radius:5px;background:rgba(128,128,128,.08)">
      <span id="pi-validity"></span> &middot;
      <span id="pi-price"></span> &middot;
      <span id="pi-shared"></span>
      <span id="pi-rate"></span>
    </div>

    <label><i class="fa fa-sort-numeric-asc"></i> Quantity (1–100)</label>
    <input type="number" name="qty" min="1" max="100" value="<?= isset($_POST['qty']) ? (int)$_POST['qty'] : 1 ?>" id="r-qty" oninput="calcTotal()">

    <label><i class="fa fa-font"></i> Username Character</label>
    <select name="char_type">
      <option value="lower">Random abcd</option>
      <option value="upper">Random ABCD</option>
      <option value="number">Random 2345</option>
      <option value="mix">Random abcd2345</option>
    </select>

    <label><i class="fa fa-text-width"></i> Name Length</label>
    <select name="name_len">
      <option value="4">4</option>
      <option value="5">5</option>
      <option value="6" selected>6</option>
      <option value="8">8</option>
      <option value="10">10</option>
      <option value="12">12</option>
    </select>

    <label><i class="fa fa-tag"></i> Prefix / Comment (optional)</label>
    <input type="text" name="prefix" placeholder="e.g. jan2024" value="<?= htmlspecialchars($_POST['prefix'] ?? '') ?>">

    <div id="r-total-box" class="r-info" style="display:none;margin-top:12px;padding:12px;font-size:15px;border-radius:6px;background:rgba(128,128,128,.1)">
      Estimated total: <strong id="r-total-val">—</strong>
      &nbsp; | &nbsp; Balance: <strong><?= fmtCur($reseller['balance'], $currency) ?></strong>
      <?php if($reseller['discount']>0): ?>
        &nbsp; | &nbsp; <span class="r-badge r-badge-warn"><?= $reseller['discount'] ?>% discount applied</span>
      <?php endif; ?>
    </div>

    <input type="submit" name="do_generate" value="Generate Voucher" class="r-btn bg-primary pointer">
  </form>

<?php endif; ?>
</div>

<!-- ── Available Profiles table ────────────────────── -->
<?php if($connected && !empty($profiles)): ?>
<div class="card" style="padding:18px;margin-top:15px">
  <h4 style="margin-top:0"><i class="fa fa-list"></i> Available Profiles</h4>
  <div style="overflow-x:auto">
  <table class="r-table">
    <thead>
      <tr><th>Profile</th><th>Validity</th><th>Price</th><th>Selling Price</th><th>Shared Users</th><th>Rate Limit</th></tr>
    </thead>
    <tbody>
    <?php foreach($profiles as $p): ?>
      <tr>
        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
        <td><?= htmlspecialchars($p['validity'] ?: '—') ?></td>
        <td><?= $p['price'] > 0 ? fmtCur($p['price'], $currency) : '—' ?></td>
        <td><?= $p['sprice'] > 0 ? fmtCur($p['sprice'], $currency) : '—' ?></td>
        <td><?= htmlspecialchars($p['shared']) ?></td>
        <td><?= htmlspecialchars($p['ratelimit'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<script>
var currency  = '<?= addslashes($currency) ?>';
var discount  = <?= (float)$reseller['discount'] ?>;
var isIndo    = <?= in_array($currency, $cekindo['indo']) ? 'true' : 'false' ?>;

function fmtNum(n){
  if(isIndo) return currency+' '+n.toLocaleString('id-ID',{minimumFractionDigits:0});
  return currency+' '+n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
}
function showProfileInfo(){
  var sel=document.getElementById('r-profile');
  var opt=sel.options[sel.selectedIndex];
  if(!opt||!opt.value){document.getElementById('r-profile-info').style.display='none';return;}
  var v=opt.getAttribute('data-validity')||'—';
  var p=parseFloat(opt.getAttribute('data-price'))||0;
  var s=opt.getAttribute('data-shared')||'—';
  var r=opt.getAttribute('data-rate')||'';
  document.getElementById('pi-validity').innerHTML='<strong>Validity:</strong> '+v;
  document.getElementById('pi-price').innerHTML='<strong>Price:</strong> '+fmtNum(p);
  document.getElementById('pi-shared').innerHTML='<strong>Shared:</strong> '+s;
  document.getElementById('pi-rate').innerHTML=r?(' &middot; <strong>Rate:</strong> '+r):'';
  document.getElementById('r-profile-info').style.display='block';
  calcTotal();
}
function calcTotal(){
  var sel=document.getElementById('r-profile');
  var opt=sel.options[sel.selectedIndex];
  if(!opt||!opt.value){document.getElementById('r-total-box').style.display='none';return;}
  var p=parseFloat(opt.getAttribute('data-price'))||0;
  if(discount>0) p=p*(1-discount/100);
  var qty=parseInt(document.getElementById('r-qty').value)||1;
  var total=p*qty;
  document.getElementById('r-total-val').textContent=fmtNum(total);
  document.getElementById('r-total-box').style.display='block';
}
function printVouchers(comment,session){
  window.open('./voucher/print.php?id='+encodeURIComponent(comment)+'&session='+encodeURIComponent(session),'_blank','width=800,height=600');
}
</script>
