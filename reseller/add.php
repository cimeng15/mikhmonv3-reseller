<?php
/*
 *  Add Reseller – Admin panel page
 *  URL:  admin.php?id=reseller-add
 */
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
}

include_once('./include/resellers.php');

// available sessions (routers) from config
$availableSessions = [];
foreach (file('./include/config.php') as $line) {
    $sn = explode("'", $line)[1];
    if ($sn !== '' && $sn !== 'mikhmon') {
        $availableSessions[] = $sn;
    }
}

// handle form submit
$formError = '';
if (isset($_POST['save_reseller'])) {
    $fields = [
        'name'     => trim($_POST['rname']),
        'username' => trim($_POST['rusername']),
        'password' => $_POST['rpassword'],
        'email'    => trim($_POST['remail']),
        'phone'    => trim($_POST['rphone']),
        'balance'  => floatval($_POST['rbalance']),
        'sessions' => isset($_POST['rsessions']) ? $_POST['rsessions'] : [],
        'profiles' => isset($_POST['rprofiles']) ? array_map('trim', explode(',', $_POST['rprofiles'])) : [],
        'status'   => $_POST['rstatus'],
    ];
    $result = reseller_create($fields);
    if ($result === true) {
        echo "<script>window.location='./admin.php?id=resellers'</script>";
    } else {
        $formError = '<div class="bg-danger" style="padding:8px;border-radius:4px;margin-bottom:10px;"><i class="fa fa-ban"></i> ' . htmlspecialchars($result) . '</div>';
    }
}
?>

<script>
function PassR(){
  var x = document.getElementById('rpassword');
  if (x.type === 'password') { x.type = 'text'; } else { x.type = 'password'; }
}
</script>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-user-plus"></i> <?= $_reseller_add ?></h3>
      </div>
      <div class="card-body">
        <?= $formError; ?>
        <form autocomplete="off" method="post" action="">
          <div class="row">
            <!-- Left column -->
            <div class="col-6">
              <div class="card">
                <div class="card-header"><h3 class="card-title"><?= $_reseller_account ?></h3></div>
                <div class="card-body">
                  <table class="table table-sm">
                    <tr>
                      <td class="align-middle"><?= $_name ?></td>
                      <td><input class="form-control" type="text" name="rname" placeholder="Display Name" required></td>
                    </tr>
                    <tr>
                      <td class="align-middle"><?= $_user_name ?></td>
                      <td><input class="form-control" type="text" name="rusername" placeholder="Login username" pattern="[a-zA-Z0-9\-]+" title="Letters, numbers and hyphens only" required></td>
                    </tr>
                    <tr>
                      <td class="align-middle"><?= $_password ?></td>
                      <td>
                        <div class="input-group">
                          <div class="input-group-11 col-box-10">
                            <input class="group-item group-item-l" id="rpassword" type="password" name="rpassword" required>
                          </div>
                          <div class="input-group-1 col-box-2">
                            <div class="group-item group-item-r pd-2p5 text-center align-middle">
                              <input title="Show/Hide Password" type="checkbox" onclick="PassR()">
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td class="align-middle">Email</td>
                      <td><input class="form-control" type="email" name="remail" placeholder="Optional"></td>
                    </tr>
                    <tr>
                      <td class="align-middle">Phone</td>
                      <td><input class="form-control" type="text" name="rphone" placeholder="Optional"></td>
                    </tr>
                    <tr>
                      <td class="align-middle">Status</td>
                      <td>
                        <select class="form-control" name="rstatus">
                          <option value="active">Active</option>
                          <option value="disabled">Disabled</option>
                        </select>
                      </td>
                    </tr>
                  </table>
                </div>
              </div>
            </div>

            <!-- Right column -->
            <div class="col-6">
              <div class="card">
                <div class="card-header"><h3 class="card-title"><?= $_reseller_permissions ?></h3></div>
                <div class="card-body">
                  <table class="table table-sm">
                    <tr>
                      <td class="align-middle"><?= $_reseller_balance ?></td>
                      <td><input class="form-control" type="number" name="rbalance" value="0" min="0" step="1"></td>
                    </tr>
                    <tr>
                      <td class="align-middle"><?= $_reseller_sessions ?></td>
                      <td>
                        <?php foreach ($availableSessions as $as) { ?>
                          <label style="display:block;margin:3px 0;">
                            <input type="checkbox" name="rsessions[]" value="<?= htmlspecialchars($as); ?>">
                            <?= htmlspecialchars($as); ?>
                          </label>
                        <?php } ?>
                        <?php if (empty($availableSessions)) { ?>
                          <span class="text-muted"><i class="fa fa-info-circle"></i> No routers configured.</span>
                        <?php } ?>
                      </td>
                    </tr>
                    <tr>
                      <td class="align-middle"><?= $_reseller_profiles ?></td>
                      <td><input class="form-control" type="text" name="rprofiles" placeholder="profile1, profile2, ..." title="Comma-separated hotspot profile names"></td>
                    </tr>
                  </table>
                </div>
              </div>

              <div style="text-align:right;margin-top:10px;">
                <div class="input-group-4" style="display:inline-block;">
                  <input class="group-item group-item-l" type="submit" style="cursor:pointer;" name="save_reseller" value="<?= $_save ?>">
                </div>
                <div class="input-group-2" style="display:inline-block;">
                  <a href="./admin.php?id=resellers" class="group-item group-item-r pd-2p5 text-center" style="cursor:pointer;display:inline-block;"><i class="fa fa-arrow-left"></i> <?= $_reseller_back ?></a>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
