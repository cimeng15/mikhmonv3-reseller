<?php
/*
 *  View Reseller detail – Admin panel page
 *  URL:  admin.php?id=reseller-view&reseller=<id>
 */
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
}

include_once('./include/resellers.php');

$rid = $_GET['reseller'];
$rec = reseller_get($rid);
if (!$rec) {
    echo "<script>alert('Reseller not found.');window.location='./admin.php?id=resellers'</script>";
    return;
}

// handle balance adjustment
$adjMsg = '';
if (isset($_POST['adjust_balance'])) {
    $amt = floatval($_POST['adj_amount']);
    if ($amt != 0) {
        $result = reseller_adjust_balance($rid, $amt);
        if ($result === true) {
            echo "<script>window.location='./admin.php?id=reseller-view&reseller=" . urlencode($rid) . "'</script>";
        } else {
            $adjMsg = '<div class="bg-danger" style="padding:5px;border-radius:4px;"><i class="fa fa-ban"></i> ' . htmlspecialchars($result) . '</div>';
        }
    }
}

// re-read after possible adjustment
$rec = reseller_get($rid);

$statusBadge = ($rec['status'] === 'active')
    ? '<span class="text-green"><i class="fa fa-check-circle"></i> Active</span>'
    : '<span class="text-red"><i class="fa fa-ban"></i> Disabled</span>';
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-user"></i> <?= $_reseller_detail ?> — <?= htmlspecialchars($rec['name']); ?></h3>
      </div>
      <div class="card-body">
        <div class="row">
          <!-- Info -->
          <div class="col-6">
            <div class="card">
              <div class="card-header"><h3 class="card-title"><?= $_reseller_account ?></h3></div>
              <div class="card-body">
                <table class="table table-sm">
                  <tr><td><b><?= $_user_name ?></b></td><td><?= htmlspecialchars($rec['username']); ?></td></tr>
                  <tr><td><b><?= $_name ?></b></td><td><?= htmlspecialchars($rec['name']); ?></td></tr>
                  <tr><td><b>Email</b></td><td><?= htmlspecialchars($rec['email']); ?></td></tr>
                  <tr><td><b>Phone</b></td><td><?= htmlspecialchars($rec['phone']); ?></td></tr>
                  <tr><td><b>Status</b></td><td><?= $statusBadge; ?></td></tr>
                  <tr><td><b><?= $_reseller_created ?></b></td><td><?= htmlspecialchars($rec['created_at']); ?></td></tr>
                  <tr><td><b><?= $_reseller_updated ?></b></td><td><?= htmlspecialchars($rec['updated_at']); ?></td></tr>
                </table>
              </div>
            </div>
          </div>

          <!-- Permissions & Balance -->
          <div class="col-6">
            <div class="card">
              <div class="card-header"><h3 class="card-title"><?= $_reseller_permissions ?></h3></div>
              <div class="card-body">
                <table class="table table-sm">
                  <tr>
                    <td><b><?= $_reseller_balance ?></b></td>
                    <td class="text-right" style="font-size:18px;font-weight:bold;">
                      <?= number_format($rec['balance'], 0, '.', ','); ?>
                    </td>
                  </tr>
                  <tr>
                    <td><b><?= $_reseller_sessions ?></b></td>
                    <td><?= htmlspecialchars(implode(', ', is_array($rec['sessions']) ? $rec['sessions'] : [])); ?></td>
                  </tr>
                  <tr>
                    <td><b><?= $_reseller_profiles ?></b></td>
                    <td><?= htmlspecialchars(implode(', ', is_array($rec['profiles']) ? $rec['profiles'] : [])); ?></td>
                  </tr>
                </table>
              </div>
            </div>

            <!-- Quick balance adjustment -->
            <div class="card">
              <div class="card-header"><h3 class="card-title"><i class="fa fa-money"></i> <?= $_reseller_adjust_balance ?></h3></div>
              <div class="card-body">
                <?= $adjMsg; ?>
                <form method="post" action="" autocomplete="off">
                  <table class="table table-sm">
                    <tr>
                      <td class="align-middle"><?= $_reseller_amount ?></td>
                      <td>
                        <input class="form-control" type="number" name="adj_amount" placeholder="+1000 or -500" step="1" required>
                        <small class="text-muted"><?= $_reseller_amount_hint ?></small>
                      </td>
                    </tr>
                    <tr>
                      <td></td>
                      <td>
                        <input class="group-item group-item-l" type="submit" style="cursor:pointer;" name="adjust_balance" value="<?= $_reseller_apply ?>">
                      </td>
                    </tr>
                  </table>
                </form>
              </div>
            </div>

            <!-- Actions -->
            <div style="text-align:right;margin-top:10px;">
              <a href="./admin.php?id=reseller-edit&reseller=<?= urlencode($rec['id']); ?>" class="btn bg-primary pointer" style="padding:5px 15px;border-radius:4px;"><i class="fa fa-edit"></i> <?= $_edit ?></a>
              <a href="./admin.php?id=resellers" class="btn pointer" style="padding:5px 15px;border-radius:4px;"><i class="fa fa-arrow-left"></i> <?= $_reseller_back ?></a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
