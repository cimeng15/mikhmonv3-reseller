<?php
/*
 *  Mikhmon V3 — Admin: Reseller Management
 *  Included from admin.php when $id == 'resellers'.
 *
 *  Allows the admin to:
 *    • List all resellers
 *    • Create / Edit / Delete a reseller account
 *    • Top-up or adjust balance
 *    • View a reseller's transaction history
 */

require_once __DIR__ . '/../reseller/include/db.php';

// ── Actions ──────────────────────────────────────────
$rAction = $_GET['raction'] ?? ($_POST['raction'] ?? '');
$rUser   = $_GET['ruser']   ?? ($_POST['ruser']   ?? '');
$msg     = '';

// Delete
if ($rAction === 'delete' && $rUser !== '') {
    reseller_delete($rUser);
    $msg = '<div class="bg-success text-center" style="padding:8px;border-radius:5px;margin:10px 0"><i class="fa fa-check"></i> Reseller <b>' . htmlspecialchars($rUser) . '</b> deleted.</div>';
}

// Top-up
if ($rAction === 'topup' && isset($_POST['topup_amount'])) {
    $r = reseller_get($rUser);
    if ($r) {
        $amount = (float)$_POST['topup_amount'];
        $r['balance'] += $amount;
        reseller_save($r);
        // log it as a transaction
        reseller_tx_add($rUser, [
            'session'    => '—',
            'profile'    => '—',
            'qty'        => 0,
            'unit_price' => 0,
            'total_cost' => -$amount, // negative = deposit
            'comment'    => 'Balance top-up by admin',
            'users'      => [],
        ]);
        $msg = '<div class="bg-success text-center" style="padding:8px;border-radius:5px;margin:10px 0"><i class="fa fa-check"></i> Topped up ' . htmlspecialchars($rUser) . ' by ' . number_format($amount, 2) . '.</div>';
    }
}

// Save (create or update)
if ($rAction === 'save' && isset($_POST['r_username'])) {
    $isNew = !empty($_POST['is_new']);
    $ru = trim($_POST['r_username']);
    $existing = reseller_get($ru);

    if ($isNew && $existing) {
        $msg = '<div class="bg-danger text-center" style="padding:8px;border-radius:5px;margin:10px 0"><i class="fa fa-ban"></i> Username already exists.</div>';
    } else {
        $rec = $existing ?: [];
        $rec['username']         = $ru;
        $rec['name']             = trim($_POST['r_name'] ?? $ru);
        $rec['discount']         = max(0, min(100, (float)($_POST['r_discount'] ?? 0)));
        $rec['status']           = ($_POST['r_status'] ?? 'active') === 'active' ? 'active' : 'disabled';
        $rec['allowed_sessions'] = array_filter(array_map('trim', explode(',', $_POST['r_sessions'] ?? '')));
        $rec['allowed_profiles'] = array_filter(array_map('trim', explode(',', $_POST['r_profiles'] ?? '')));

        // password: only update if provided
        $pw = $_POST['r_password'] ?? '';
        if ($pw !== '') {
            $rec['password'] = password_hash($pw, PASSWORD_DEFAULT);
        } elseif ($isNew) {
            $rec['password'] = password_hash('reseller123', PASSWORD_DEFAULT);
        }

        if (!isset($rec['balance']))    $rec['balance']    = 0;
        if (!isset($rec['created_at'])) $rec['created_at'] = date('Y-m-d H:i:s');

        reseller_save($rec);
        $msg = '<div class="bg-success text-center" style="padding:8px;border-radius:5px;margin:10px 0"><i class="fa fa-check"></i> Reseller <b>' . htmlspecialchars($ru) . '</b> saved.</div>';
    }
}

$allResellers = reseller_list();
?>

<div class="card" style="padding:18px">
<h4 style="margin-top:0"><i class="fa fa-users"></i> Reseller Management</h4>
<?= $msg ?>

<!-- Reseller list table -->
<div style="overflow-x:auto">
<table class="table table-bordered" style="width:100%;border-collapse:collapse">
  <thead>
    <tr style="font-weight:600">
      <th style="padding:6px 10px">#</th>
      <th style="padding:6px 10px">Username</th>
      <th style="padding:6px 10px">Name</th>
      <th style="padding:6px 10px">Balance</th>
      <th style="padding:6px 10px">Discount</th>
      <th style="padding:6px 10px">Status</th>
      <th style="padding:6px 10px">Sessions</th>
      <th style="padding:6px 10px">Profiles</th>
      <th style="padding:6px 10px">Created</th>
      <th style="padding:6px 10px">Action</th>
    </tr>
  </thead>
  <tbody>
  <?php if(empty($allResellers)): ?>
    <tr><td colspan="10" style="text-align:center;padding:20px;opacity:.6">No resellers yet.</td></tr>
  <?php else: $n=0; foreach($allResellers as $r): $n++; ?>
    <tr>
      <td style="padding:6px 10px"><?= $n ?></td>
      <td style="padding:6px 10px"><code><?= htmlspecialchars($r['username']) ?></code></td>
      <td style="padding:6px 10px"><?= htmlspecialchars($r['name']) ?></td>
      <td style="padding:6px 10px;text-align:right"><?= number_format((float)$r['balance'], 2) ?></td>
      <td style="padding:6px 10px;text-align:center"><?= (float)$r['discount'] ?>%</td>
      <td style="padding:6px 10px;text-align:center">
        <?php if($r['status']==='active'): ?>
          <span style="color:#27ae60;font-weight:600">Active</span>
        <?php else: ?>
          <span style="color:#e74c3c;font-weight:600">Disabled</span>
        <?php endif; ?>
      </td>
      <td style="padding:6px 10px;font-size:12px"><?= htmlspecialchars(implode(', ', $r['allowed_sessions'] ?? []) ?: 'All') ?></td>
      <td style="padding:6px 10px;font-size:12px"><?= htmlspecialchars(implode(', ', $r['allowed_profiles'] ?? []) ?: 'All') ?></td>
      <td style="padding:6px 10px;font-size:12px;white-space:nowrap"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
      <td style="padding:6px 10px;white-space:nowrap">
        <a href="javascript:void(0)" onclick="editReseller('<?= htmlspecialchars($r['username']) ?>')" title="Edit"><i class="fa fa-pencil"></i></a>
        &nbsp;
        <a href="javascript:void(0)" onclick="topupReseller('<?= htmlspecialchars($r['username']) ?>')" title="Top-up balance"><i class="fa fa-plus-circle" style="color:#27ae60"></i></a>
        &nbsp;
        <a href="./admin.php?id=resellers&raction=delete&ruser=<?= urlencode($r['username']) ?>&session=<?= $session ?>"
           onclick="return confirm('Delete reseller <?= htmlspecialchars($r['username']) ?>?')" title="Delete"><i class="fa fa-trash" style="color:#e74c3c"></i></a>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>

<button onclick="addReseller()" class="btn bg-primary pointer" style="margin-top:12px;padding:8px 22px;border:0;border-radius:5px;font-weight:700;cursor:pointer">
  <i class="fa fa-user-plus"></i> Add Reseller
</button>
</div>

<!-- ── Modal: Add / Edit Reseller ────────────────────── -->
<div id="reseller-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;padding:40px 15px;overflow-y:auto">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:8px;padding:24px;color:#333">
  <h4 id="rm-title" style="margin-top:0"><i class="fa fa-user-plus"></i> Add Reseller</h4>
  <form method="post" id="reseller-form" action="./admin.php?id=resellers&raction=save&session=<?= $session ?>">
    <input type="hidden" name="raction" value="save">
    <input type="hidden" name="is_new" id="rm-isnew" value="1">
    <div style="margin-bottom:8px">
      <label style="font-weight:600;font-size:13px">Username</label>
      <input type="text" name="r_username" id="rm-username" required style="width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box" pattern="[a-zA-Z0-9\-_]+" title="Alphanumeric, - and _ only">
    </div>
    <div style="margin-bottom:8px">
      <label style="font-weight:600;font-size:13px">Display Name</label>
      <input type="text" name="r_name" id="rm-name" style="width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
    </div>
    <div style="margin-bottom:8px">
      <label style="font-weight:600;font-size:13px">Password <span id="rm-pw-hint" style="font-weight:400;opacity:.6">(default: reseller123)</span></label>
      <input type="password" name="r_password" id="rm-password" style="width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
    </div>
    <div style="margin-bottom:8px">
      <label style="font-weight:600;font-size:13px">Discount (%)</label>
      <input type="number" name="r_discount" id="rm-discount" value="0" min="0" max="100" style="width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
    </div>
    <div style="margin-bottom:8px">
      <label style="font-weight:600;font-size:13px">Allowed Sessions <span style="font-weight:400;opacity:.6">(comma-separated, leave blank = all)</span></label>
      <input type="text" name="r_sessions" id="rm-sessions" placeholder="e.g. session1, session2" style="width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
    </div>
    <div style="margin-bottom:8px">
      <label style="font-weight:600;font-size:13px">Allowed Profiles <span style="font-weight:400;opacity:.6">(comma-separated, leave blank = all)</span></label>
      <input type="text" name="r_profiles" id="rm-profiles" placeholder="e.g. 3jam, 1hari" style="width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
    </div>
    <div style="margin-bottom:12px">
      <label style="font-weight:600;font-size:13px">Status</label>
      <select name="r_status" id="rm-status" style="width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
        <option value="active">Active</option>
        <option value="disabled">Disabled</option>
      </select>
    </div>
    <div style="text-align:right">
      <button type="button" onclick="closeResellerModal()" style="padding:8px 20px;cursor:pointer;margin-right:8px">Cancel</button>
      <button type="submit" class="bg-primary pointer" style="padding:8px 24px;border:0;border-radius:5px;font-weight:700;cursor:pointer">Save</button>
    </div>
  </form>
</div>
</div>

<!-- ── Modal: Top-up ─────────────────────────────────── -->
<div id="topup-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;padding:40px 15px;overflow-y:auto">
<div style="max-width:380px;margin:0 auto;background:#fff;border-radius:8px;padding:24px;color:#333">
  <h4 style="margin-top:0"><i class="fa fa-plus-circle" style="color:#27ae60"></i> Top-up Balance</h4>
  <form method="post" id="topup-form">
    <input type="hidden" name="raction" value="topup">
    <input type="hidden" name="ruser" id="tu-user" value="">
    <div style="margin-bottom:8px">
      <label style="font-weight:600;font-size:13px">Reseller: <span id="tu-label"></span></label>
    </div>
    <div style="margin-bottom:12px">
      <label style="font-weight:600;font-size:13px">Amount</label>
      <input type="number" name="topup_amount" id="tu-amount" min="0" step="any" required style="width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
      <small style="opacity:.6">Use negative value to subtract balance.</small>
    </div>
    <div style="text-align:right">
      <button type="button" onclick="closeTopupModal()" style="padding:8px 20px;cursor:pointer;margin-right:8px">Cancel</button>
      <button type="submit" class="bg-primary pointer" style="padding:8px 24px;border:0;border-radius:5px;font-weight:700;cursor:pointer">Top-up</button>
    </div>
  </form>
</div>
</div>

<script>
var resellers = <?= json_encode($allResellers) ?>;

function addReseller(){
  document.getElementById('rm-title').innerHTML='<i class="fa fa-user-plus"></i> Add Reseller';
  document.getElementById('rm-isnew').value='1';
  document.getElementById('rm-username').value='';
  document.getElementById('rm-username').readOnly=false;
  document.getElementById('rm-name').value='';
  document.getElementById('rm-password').value='';
  document.getElementById('rm-pw-hint').style.display='inline';
  document.getElementById('rm-discount').value='0';
  document.getElementById('rm-sessions').value='';
  document.getElementById('rm-profiles').value='';
  document.getElementById('rm-status').value='active';
  document.getElementById('reseller-modal').style.display='block';
}

function editReseller(username){
  var r=null;
  for(var i=0;i<resellers.length;i++){if(resellers[i].username===username){r=resellers[i];break;}}
  if(!r)return;
  document.getElementById('rm-title').innerHTML='<i class="fa fa-pencil"></i> Edit Reseller';
  document.getElementById('rm-isnew').value='';
  document.getElementById('rm-username').value=r.username;
  document.getElementById('rm-username').readOnly=true;
  document.getElementById('rm-name').value=r.name||'';
  document.getElementById('rm-password').value='';
  document.getElementById('rm-pw-hint').style.display='none';
  document.getElementById('rm-discount').value=r.discount||0;
  document.getElementById('rm-sessions').value=(r.allowed_sessions||[]).join(', ');
  document.getElementById('rm-profiles').value=(r.allowed_profiles||[]).join(', ');
  document.getElementById('rm-status').value=r.status||'active';
  document.getElementById('reseller-modal').style.display='block';
}

function closeResellerModal(){document.getElementById('reseller-modal').style.display='none';}

function topupReseller(username){
  document.getElementById('tu-user').value=username;
  document.getElementById('tu-label').textContent=username;
  document.getElementById('tu-amount').value='';
  document.getElementById('topup-form').action='./admin.php?id=resellers&raction=topup&ruser='+encodeURIComponent(username)+'&session=<?= $session ?>';
  document.getElementById('topup-modal').style.display='block';
}

function closeTopupModal(){document.getElementById('topup-modal').style.display='none';}
</script>
