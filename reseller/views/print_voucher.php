<?php
/*
 *  Mikhmon V3 — Reseller Panel · Print Voucher (popup)
 *  Re-uses the core Mikhmon voucher template system.
 *
 *  Opens in a new window, connects to RouterOS, pulls vouchers
 *  by comment, and renders them with the standard template.
 */
session_start();
error_reporting(0);

require_once __DIR__ . '/include/auth.php';
$reseller = reseller_require_login();

$session = $_GET['session'] ?? '';
$comment = $_GET['comment'] ?? '';

include __DIR__ . '/../include/config.php';
$iphost     = explode('!',   $data[$session][1])[1];
$userhost   = explode('@|@', $data[$session][2])[1];
$passwdhost = explode('#|#', $data[$session][3])[1];
$hotspotname= explode('%',   $data[$session][4])[1];
$dnsname    = explode('^',   $data[$session][5])[1];
$currency   = explode('&',   $data[$session][6])[1];

include_once __DIR__ . '/../lib/routeros_api.class.php';
include_once __DIR__ . '/../lib/formatbytesbites.php';

$API = new RouterosAPI();
$API->debug = false;
$API->connect($iphost, $userhost, decrypt($passwdhost));

$getuser = $API->comm('/ip/hotspot/user/print', ["?comment" => $comment, "?uptime" => "0s"]);
$TotalReg = count($getuser);

if ($TotalReg > 0) {
    $getuprofile = $getuser[0]['profile'];
    $getprofile  = $API->comm("/ip/hotspot/user/profile/print", ["?name" => $getuprofile]);
    $getsharedu  = $getprofile[0]['shared-users'];
    $ponlogin    = $getprofile[0]['on-login'];
    $validity    = explode(",", $ponlogin)[3];
    $getprice    = explode(",", $ponlogin)[2];
    $getsprice   = explode(",", $ponlogin)[4];

    $cekindo = ['indo'=>['RP','Rp','rp','IDR','idr','RP.','Rp.','rp.','IDR.','idr.']];
    if ($getsprice == "0" && $getprice != "0") {
        if (in_array($currency, $cekindo['indo'])) {
            $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
        } else {
            $price = $currency . " " . number_format((float)$getprice, 2);
        }
    } elseif ($getsprice != "0") {
        if (in_array($currency, $cekindo['indo'])) {
            $price = $currency . " " . number_format((float)$getsprice, 0, ",", ".");
        } else {
            $price = $currency . " " . number_format((float)$getsprice, 2);
        }
    } else {
        $price = "";
    }
}

$logo = __DIR__ . "/../img/logo-" . $session . ".png";
if (file_exists($logo)) {
    $logo = "../img/logo-" . $session . ".png?t=" . str_replace(" ", "_", date("Y-m-d H:i:s"));
} else {
    $logo = "../img/logo.png?t=" . str_replace(" ", "_", date("Y-m-d H:i:s"));
}

$id = $comment;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Voucher — <?= htmlspecialchars($hotspotname . " — " . $comment); ?></title>
    <meta charset="utf-8" />
    <meta http-equiv="pragma" content="no-cache" />
    <link rel="icon" href="../img/favicon.png" />
    <script src="../js/qrious.min.js"></script>
    <style>
    body{color:#000;background:#fff;font-size:14px;font-family:'Helvetica',arial,sans-serif;margin:0;-webkit-print-color-adjust:exact}
    table.voucher{display:inline-block;border:2px solid #000;margin:2px}
    @page{size:auto;margin:9mm 3mm 3mm 7mm}
    @media print{table{page-break-after:auto}tr{page-break-inside:avoid;page-break-after:auto}td{page-break-inside:avoid;page-break-after:auto}}
    #num{float:right;display:inline-block}
    .qrc{width:30px;height:30px;margin-top:1px}
    .no-print{margin:15px;text-align:center}
    @media print{.no-print{display:none}}
    </style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()" style="padding:10px 30px;font-size:15px;font-weight:700;cursor:pointer"><i class="fa fa-print"></i> Print</button>
  <button onclick="window.close()" style="padding:10px 30px;font-size:15px;cursor:pointer">Close</button>
</div>

<?php if($TotalReg === 0): ?>
  <div style="text-align:center;padding:40px;font-size:18px">No unused vouchers found for "<strong><?= htmlspecialchars($comment) ?></strong>".</div>
<?php else: ?>

<?php for ($i = 0; $i < $TotalReg; $i++):
  $regtable = $getuser[$i];
  $uid      = str_replace("=", "", base64_encode($regtable['.id']));
  $username = $regtable['name'];
  $password = $regtable['password'];
  $profile  = $regtable['profile'];
  $timelimit= $regtable['limit-uptime'];
  $getdatalimit = $regtable['limit-bytes-total'];
  $datalimit = ($getdatalimit == 0) ? '' : formatBytes($getdatalimit, 2);
  $urilogin = "http://{$dnsname}/login?username={$username}&password={$password}";
  $num = $i + 1;
?>

<table class="voucher" cellpadding="4">
  <tr>
    <td rowspan="4" style="padding:6px;vertical-align:middle;text-align:center">
      <img src="<?= $logo ?>" style="max-height:50px"><br>
      <canvas class="qrc" id="<?= $uid ?>"></canvas>
      <script>
      (function(){new QRious({element:document.getElementById('<?= $uid ?>'),value:'<?= $urilogin ?>',size:'256'});})();
      </script>
    </td>
    <td style="font-weight:bold;font-size:13px"><?= htmlspecialchars($hotspotname) ?></td>
    <td id="num">#<?= $num ?></td>
  </tr>
  <tr>
    <td colspan="2">
      <strong>User:</strong> <?= htmlspecialchars($username) ?><br>
      <strong>Pass:</strong> <?= htmlspecialchars($password) ?><br>
      <strong>Profile:</strong> <?= htmlspecialchars($profile) ?>
      <?php if($validity): ?><br><strong>Validity:</strong> <?= htmlspecialchars($validity) ?><?php endif; ?>
      <?php if($price): ?><br><strong>Price:</strong> <?= $price ?><?php endif; ?>
      <?php if($timelimit): ?><br><strong>Time Limit:</strong> <?= htmlspecialchars($timelimit) ?><?php endif; ?>
      <?php if($datalimit): ?><br><strong>Data Limit:</strong> <?= $datalimit ?><?php endif; ?>
    </td>
  </tr>
</table>

<?php endfor; ?>
<?php endif; ?>

</body>
</html>
