<?php
/*
 *  Reseller List – Admin panel page
 *  URL:  admin.php?id=resellers
 */
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
}

include_once('./include/resellers.php');

$resellers = resellers_all();

// read available sessions (routers) from config for reference
$availableSessions = [];
foreach (file('./include/config.php') as $line) {
    $sn = explode("'", $line)[1];
    if ($sn !== '' && $sn !== 'mikhmon') {
        $availableSessions[] = $sn;
    }
}
?>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fa fa-users"></i> <?= $_reseller_management ?> &nbsp;|&nbsp;
          <i onclick="location.reload();" class="fa fa-refresh pointer" title="Reload data"></i>
        </h3>
      </div>
      <div class="card-body">

        <!-- toolbar -->
        <div class="row" style="margin-bottom:10px;">
          <div class="col-6">
            <a href="./admin.php?id=reseller-add" class="btn bg-primary pointer" style="padding:5px 15px;border-radius:4px;">
              <i class="fa fa-plus"></i> <?= $_reseller_add ?>
            </a>
          </div>
          <div class="col-6 text-right">
            <input id="filterTable" class="form-control" type="text" placeholder="<?= $_search ?>..." style="display:inline-block;width:250px;">
          </div>
        </div>

        <table class="table table-sm" id="dataTable">
          <thead>
            <tr>
              <th>#</th>
              <th><?= $_name ?></th>
              <th><?= $_user_name ?></th>
              <th>Email</th>
              <th>Phone</th>
              <th><?= $_reseller_balance ?></th>
              <th><?= $_reseller_sessions ?></th>
              <th>Status</th>
              <th><?= $_action ?></th>
            </tr>
          </thead>
          <tbody>
          <?php
            $no = 1;
            foreach ($resellers as $r) {
                $statusBadge = ($r['status'] === 'active')
                    ? '<span class="text-green"><i class="fa fa-check-circle"></i> Active</span>'
                    : '<span class="text-red"><i class="fa fa-ban"></i> Disabled</span>';
                $sessionList = is_array($r['sessions']) ? implode(', ', $r['sessions']) : '';
          ?>
            <tr>
              <td><?= $no++; ?></td>
              <td><?= htmlspecialchars($r['name']); ?></td>
              <td><?= htmlspecialchars($r['username']); ?></td>
              <td><?= htmlspecialchars($r['email']); ?></td>
              <td><?= htmlspecialchars($r['phone']); ?></td>
              <td class="text-right"><?= number_format($r['balance'], 0, '.', ','); ?></td>
              <td><?= htmlspecialchars($sessionList); ?></td>
              <td><?= $statusBadge; ?></td>
              <td>
                <a href="./admin.php?id=reseller-view&reseller=<?= urlencode($r['id']); ?>" title="View"><i class="fa fa-eye"></i></a>&nbsp;
                <a href="./admin.php?id=reseller-edit&reseller=<?= urlencode($r['id']); ?>" title="Edit"><i class="fa fa-edit"></i></a>&nbsp;
                <a href="javascript:void(0)" onclick="if(confirm('<?= $_reseller_confirm_delete ?> <?= htmlspecialchars($r['name']); ?>?')){loadpage('./admin.php?id=reseller-delete&reseller=<?= urlencode($r['id']); ?>')}" title="Delete"><i class="fa fa-trash text-red"></i></a>&nbsp;
                <?php if ($r['status'] === 'active') { ?>
                  <a href="javascript:void(0)" onclick="loadpage('./admin.php?id=reseller-toggle&reseller=<?= urlencode($r['id']); ?>')" title="Disable"><i class="fa fa-toggle-on text-green"></i></a>
                <?php } else { ?>
                  <a href="javascript:void(0)" onclick="loadpage('./admin.php?id=reseller-toggle&reseller=<?= urlencode($r['id']); ?>')" title="Enable"><i class="fa fa-toggle-off text-red"></i></a>
                <?php } ?>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>

        <?php if (empty($resellers)) { ?>
          <div class="text-center" style="padding:30px;">
            <i class="fa fa-info-circle"></i> <?= $_reseller_empty ?>
          </div>
        <?php } ?>

      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
  makeAllSortable();
  $("#filterTable").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#dataTable tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});
</script>
