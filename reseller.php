<?php
/*
 *  Mikhmon V3 — Reseller Panel
 *  Main entry-point: login, dashboard, voucher generation, transaction history.
 *
 *  URL routing via ?page=<page>
 *    login      — login form
 *    logout     — destroy session
 *    dashboard  — balance, quick stats, shortcut cards
 *    generate   — generate vouchers (pick profile, qty, comment)
 *    history    — transaction / voucher generation history
 *    print      — print voucher batch (popup)
 *    api        — internal AJAX endpoints
 */
session_start();
error_reporting(0);
ob_start("ob_gzhandler");

// ── Paths ────────────────────────────────────────────────
require_once __DIR__ . '/reseller/include/db.php';
require_once __DIR__ . '/reseller/include/auth.php';

// bootstrap: ensure a default reseller exists
reseller_create_default();

// ── Mikhmon core includes (config, lang, theme, RouterOS) ─
include __DIR__ . '/include/config.php';
include __DIR__ . '/include/lang.php';
include __DIR__ . '/lang/' . $langid . '.php';
include __DIR__ . '/include/theme.php';
include __DIR__ . '/settings/settheme.php';
if ($_SESSION['theme'] == "") {
    $theme = $theme;
    $themecolor = $themecolor;
} else {
    $theme = $_SESSION['theme'];
    $themecolor = $_SESSION['themecolor'];
}

$url  = $_SERVER['REQUEST_URI'];
$page = isset($_GET['page']) ? $_GET['page'] : '';

// ── PUBLIC: Login / Logout ────────────────────────────────
if ($page === 'login' || $page === '' && empty($_SESSION['reseller_user'])) {
    $error = '';
    if (isset($_POST['reseller_login'])) {
        $r = reseller_authenticate($_POST['user'], $_POST['pass']);
        if ($r) {
            reseller_login($r);
            echo "<script>window.location='./reseller.php?page=dashboard'</script>";
            exit;
        } else {
            $error = '<div class="bg-danger text-center" style="padding:8px;border-radius:5px;margin-top:8px;"><i class="fa fa-ban"></i> Invalid username or password.</div>';
        }
    }
    include __DIR__ . '/reseller/views/login.php';
    exit;
}

if ($page === 'logout') {
    reseller_logout();
    echo "<script>window.location='./reseller.php?page=login'</script>";
    exit;
}

// ── PROTECTED: everything below requires a session ────────
$reseller = reseller_require_login();

// connect to the chosen MikroTik session
$sess = isset($_GET['session']) ? $_GET['session'] : '';
// Determine available sessions for this reseller
$allowed = $reseller['allowed_sessions'];
$allSessions = [];
foreach (file(__DIR__ . '/include/config.php') as $line) {
    $sn = explode("'", $line)[1];
    if ($sn == '' || $sn == 'mikhmon') continue;
    if (empty($allowed) || in_array($sn, $allowed)) {
        $allSessions[] = $sn;
    }
}
if ($sess === '' && count($allSessions) > 0) {
    $sess = $allSessions[0];
}

// Read the chosen session's config
include __DIR__ . '/include/readcfg.php';
// Override $session for readcfg
$session = $sess;
$iphost     = explode('!',   $data[$session][1])[1];
$userhost   = explode('@|@', $data[$session][2])[1];
$passwdhost = explode('#|#', $data[$session][3])[1];
$hotspotname= explode('%',   $data[$session][4])[1];
$dnsname    = explode('^',   $data[$session][5])[1];
$currency   = explode('&',   $data[$session][6])[1];

include_once __DIR__ . '/lib/routeros_api.class.php';
include_once __DIR__ . '/lib/formatbytesbites.php';

// ── AJAX / API endpoint ──────────────────────────────────
if ($page === 'api') {
    header('Content-Type: application/json');
    include __DIR__ . '/reseller/views/api.php';
    exit;
}

// ── HTML Head ─────────────────────────────────────────────
?>
<!DOCTYPE html>
<html>
<head>
    <title>MIKHMON Reseller — <?= htmlspecialchars($reseller['name']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= $themecolor ?>" />
    <link rel="stylesheet" href="css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="./img/favicon.png" />
    <script src="js/jquery.min.js"></script>
    <link href="css/pace.<?= $theme; ?>.css" rel="stylesheet" />
    <script src="js/pace.min.js"></script>
    <style>
    /* ── Reseller panel custom styles ─────────────── */
    .r-cards{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px}
    .r-card{flex:1 1 220px;min-width:200px;padding:18px 20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.12)}
    .r-card h4{margin:0 0 6px;font-size:14px;opacity:.7}
    .r-card .r-val{font-size:26px;font-weight:700}
    .r-card .fa{font-size:32px;float:right;margin-top:-8px;opacity:.25}
    .r-badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
    .r-badge-ok{background:#27ae60;color:#fff}
    .r-badge-warn{background:#e67e22;color:#fff}
    .r-badge-off{background:#e74c3c;color:#fff}
    .r-table{width:100%;border-collapse:collapse;margin-top:10px}
    .r-table th,.r-table td{padding:8px 10px;text-align:left;border-bottom:1px solid rgba(128,128,128,.2)}
    .r-table th{font-weight:600;opacity:.8}
    .r-table tr:hover{background:rgba(128,128,128,.06)}
    .r-form label{display:block;margin:8px 0 3px;font-weight:600;font-size:13px}
    .r-form select,.r-form input[type=number],.r-form input[type=text]{width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.3);border-radius:5px;font-size:14px;box-sizing:border-box}
    .r-form .r-btn{margin-top:15px;padding:10px 28px;border:0;border-radius:5px;font-size:15px;font-weight:700;cursor:pointer}
    .r-form .r-info{margin-top:8px;font-size:13px;opacity:.7}
    #r-result{margin-top:15px}
    .r-success{padding:12px 18px;background:#d4edda;color:#155724;border-radius:6px;margin-bottom:10px}
    .r-error{padding:12px 18px;background:#f8d7da;color:#721c24;border-radius:6px;margin-bottom:10px}
    .r-pagination{margin:12px 0;text-align:center}
    .r-pagination a,.r-pagination span{display:inline-block;padding:4px 12px;margin:0 2px;border-radius:4px;text-decoration:none;font-size:13px}
    .r-pagination .active{font-weight:700;text-decoration:underline}
    @media(max-width:600px){.r-card{flex:1 1 100%}}
    </style>
</head>
<body>
<div class="wrapper">

<!-- ── Navbar ──────────────────────────────────────── -->
<div id="navbar" class="navbar">
  <div class="navbar-left">
    <a id="brand" class="text-center" href="./reseller.php?page=dashboard&session=<?= $sess ?>">MIKHMON</a>
    <a id="openNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
    <a id="closeNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
    <a id="cpage" class="navbar-left" href="javascript:void(0)">
      Reseller Panel
    </a>
  </div>
  <div class="navbar-right">
    <a id="logout" href="./reseller.php?page=logout"><i class="fa fa-sign-out mr-1"></i> <?= $_logout ?></a>
    <?php if(count($allSessions) > 1): ?>
    <select class="ses text-right mr-t-10 pd-5" onchange="window.location='./reseller.php?page=<?= $page ?>&session='+this.value">
      <?php foreach($allSessions as $s): ?>
        <option value="<?= $s ?>" <?= $s===$sess?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
  </div>
</div>

<!-- ── Sidebar ─────────────────────────────────────── -->
<div id="sidenav" class="sidenav">
  <div class="menu text-center align-middle card-header" style="border-radius:0;">
    <h3><i class="fa fa-user-circle"></i> <?= htmlspecialchars($reseller['name']) ?></h3>
  </div>
  <a href="./reseller.php?page=dashboard&session=<?= $sess ?>" class="menu <?= $page==='dashboard'?'active':'' ?>">
    <i class="fa fa-dashboard"></i> <?= $_dashboard ?>
  </a>
  <a href="./reseller.php?page=generate&session=<?= $sess ?>" class="menu <?= $page==='generate'?'active':'' ?>">
    <i class="fa fa-ticket"></i> Generate Voucher
  </a>
  <a href="./reseller.php?page=history&session=<?= $sess ?>" class="menu <?= $page==='history'?'active':'' ?>">
    <i class="fa fa-history"></i> Transaction History
  </a>
</div>

<div id="notify"><div class="message"></div></div>
<div id="temp"></div>

<!-- ── Main Content ────────────────────────────────── -->
<div id="main">
<div class="main-container">

<?php
// ── Route to the right view ──────────────────────────
switch ($page) {
    case 'dashboard':
        include __DIR__ . '/reseller/views/dashboard.php';
        break;
    case 'generate':
        include __DIR__ . '/reseller/views/generate.php';
        break;
    case 'history':
        include __DIR__ . '/reseller/views/history.php';
        break;
    case 'print':
        include __DIR__ . '/reseller/views/print_voucher.php';
        break;
    default:
        echo "<script>window.location='./reseller.php?page=dashboard&session={$sess}'</script>";
        break;
}
?>

</div><!-- .main-container -->
</div><!-- #main -->
</div><!-- .wrapper -->

<script src="js/mikhmon-ui.<?= $theme; ?>.min.js"></script>
<script>
// Sidebar toggle (same logic as Mikhmon core)
$(document).ready(function(){
  $("#openNav").click(function(){
    document.getElementById("sidenav").style.width="250px";
    document.getElementById("main").style.marginLeft="250px";
  });
  $("#closeNav").click(function(){
    document.getElementById("sidenav").style.width="0";
    document.getElementById("main").style.marginLeft="0";
  });
});
</script>
</body>
</html>
