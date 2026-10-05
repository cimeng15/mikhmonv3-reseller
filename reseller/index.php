<?php
/**
 * Mikhmon V3 - Reseller Portal
 * Main entry point for reseller panel
 */
error_reporting(0);
session_start();

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/../lib/routeros_api.class.php';
require_once __DIR__ . '/../include/config.php';

// Handle Login
if (isset($_POST['reseller_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (resellerLogin($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Username atau password salah';
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    resellerLogout();
    header('Location: index.php');
    exit;
}

// Show login if not authenticated
if (!isResellerLoggedIn()) {
    $theme = 'dark';
    if (file_exists(__DIR__ . '/include/theme.php')) {
        include __DIR__ . '/include/theme.php';
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mikhmon Reseller Panel</title>
    <link rel="icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/mikhmon-ui.<?=$theme?>.min.css">
    <link rel="stylesheet" href="assets/css/font-awesome/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/reseller-modern.css">
</head>
<body class="rs-login-body">
    <main class="rs-login-shell">
        <section class="rs-login-hero">
            <a class="rs-login-brand" href="index.php">
                <img src="assets/img/logo.png" alt="Mikhmon">
                <span>Mikhmon Reseller</span>
            </a>
            <div class="rs-login-copy">
                <span class="rs-login-eyebrow"><i class="fa fa-wifi"></i> Hotspot business hub</span>
                <h1>Voucher WiFi, lebih mudah.</h1>
                <p>Kelola pembelian, cetak voucher, dan pantau transaksi dari satu panel yang cepat dan aman.</p>
                <div class="rs-login-points">
                    <span class="rs-login-point"><i class="fa fa-bolt"></i> Proses cepat</span>
                    <span class="rs-login-point"><i class="fa fa-shield"></i> Data terpisah</span>
                    <span class="rs-login-point"><i class="fa fa-mobile"></i> Mobile friendly</span>
                </div>
            </div>
        </section>
        <section class="rs-login-form-side">
            <div class="rs-login-form">
                <h2>Selamat datang</h2>
                <p>Masuk dengan akun reseller Anda.</p>
                <?php if(isset($loginError)): ?>
                <div class="alert alert-danger"><i class="fa fa-warning"></i> <?=htmlspecialchars($loginError)?></div>
                <?php endif; ?>
                <form method="POST" autocomplete="on">
                    <div class="rs-field">
                        <label for="resellerUsername">Username</label>
                        <div class="rs-input-wrap">
                            <i class="fa fa-user"></i>
                            <input id="resellerUsername" type="text" name="username" class="form-control" autocomplete="username" required autofocus placeholder="Masukkan username">
                        </div>
                    </div>
                    <div class="rs-field">
                        <label for="resellerPassword">Password</label>
                        <div class="rs-input-wrap">
                            <i class="fa fa-lock"></i>
                            <input id="resellerPassword" type="password" name="password" class="form-control" autocomplete="current-password" required placeholder="Masukkan password">
                        </div>
                    </div>
                    <button type="submit" name="reseller_login" class="btn btn-primary btn-lg btn-block">
                        Masuk ke Panel <i class="fa fa-arrow-right"></i>
                    </button>
                </form>
                <div class="rs-login-foot"><i class="fa fa-lock"></i> Akses khusus reseller terdaftar</div>
            </div>
        </section>
    </main>
</body>
</html>
<?php
    exit;
}

// ====== AUTHENTICATED RESELLER AREA ======
$reseller = getCurrentReseller();
if (!$reseller) {
    resellerLogout();
    header('Location: index.php');
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$theme = 'dark';
if (file_exists(__DIR__ . '/include/theme.php')) {
    include __DIR__ . '/include/theme.php';
}

// Get allowed sessions
$allowedSessions = getAllowedSessions($reseller['id']);
$allSessions = [];
if (isset($data) && is_array($data)) {
    foreach ($data as $key => $val) {
        if ($key !== 'mikhmon' && is_array($val)) {
            if (empty($allowedSessions) || in_array($key, $allowedSessions)) {
                $allSessions[] = $key;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mikhmon Reseller - <?=htmlspecialchars($reseller['name'])?></title>
    <link rel="icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/mikhmon-ui.<?=$theme?>.min.css">
    <link rel="stylesheet" href="assets/css/font-awesome/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/reseller-modern.css">
    <script src="assets/js/jquery.min.js"></script>
</head>
<body class="rs-body">
<div id="resellerApp" class="rs-app">
    <aside class="rs-sidebar no-print" aria-label="Navigasi utama">
        <a class="rs-brand" href="index.php">
            <img src="assets/img/logo.png" alt="Mikhmon">
            <span class="rs-brand-copy"><strong>Mikhmon</strong><small>Reseller panel</small></span>
        </a>
        <div class="rs-nav-label">Menu utama</div>
        <ul class="rs-nav">
            <li class="<?=$page=='dashboard'?'active':''?>"><a href="index.php?page=dashboard"><i class="fa fa-dashboard"></i><span>Dashboard</span></a></li>
            <li class="<?=$page=='generate'?'active':''?>"><a href="index.php?page=generate"><i class="fa fa-plus-circle"></i><span>Beli Voucher</span></a></li>
            <li class="<?=$page=='vouchers'?'active':''?>"><a href="index.php?page=vouchers"><i class="fa fa-ticket"></i><span>Voucher Saya</span></a></li>
            <li class="<?=$page=='transactions'?'active':''?>"><a href="index.php?page=transactions"><i class="fa fa-exchange"></i><span>Transaksi</span></a></li>
            <li class="<?=$page=='profile'?'active':''?>"><a href="index.php?page=profile"><i class="fa fa-user"></i><span>Profil</span></a></li>
        </ul>
        <div class="rs-sidebar-account">
            <span class="name"><?=htmlspecialchars($reseller['name'])?></span>
            <span class="role">@<?=htmlspecialchars($reseller['username'])?> · Reseller aktif</span>
            <a class="logout" href="index.php?logout=1"><i class="fa fa-sign-out"></i> Keluar akun</a>
        </div>
    </aside>
    <div id="resellerOverlay" class="rs-overlay no-print"></div>
    <main class="rs-main">
        <header class="rs-topbar no-print">
            <button id="resellerMenuTrigger" class="rs-mobile-trigger" type="button" aria-label="Buka menu" aria-expanded="false"><i class="fa fa-bars"></i></button>
            <div class="rs-page-context">
                <strong><?=htmlspecialchars(ucwords(str_replace('-', ' ', $page)))?></strong>
                <span><?=date('l, d F Y')?></span>
            </div>
            <div class="rs-top-actions">
                <div class="rs-balance-pill">
                    <i class="fa fa-money"></i>
                    <span><small>Saldo tersedia</small><strong>Rp <?=number_format($reseller['balance'], 0, ',', '.')?></strong></span>
                </div>
                <div class="rs-avatar" title="<?=htmlspecialchars($reseller['name'])?>"><?=htmlspecialchars(strtoupper(substr($reseller['name'], 0, 1)))?></div>
            </div>
        </header>
        <div class="rs-content">
        <?php
        switch ($page) {
            case 'dashboard':
                include __DIR__ . '/r_dashboard.php';
                break;
            case 'generate':
                include __DIR__ . '/r_generate.php';
                break;
            case 'vouchers':
                include __DIR__ . '/r_vouchers.php';
                break;
            case 'transactions':
                include __DIR__ . '/r_transactions.php';
                break;
            case 'print':
                include __DIR__ . '/r_print_voucher.php';
                break;
            case 'profile':
                include __DIR__ . '/r_profile.php';
                break;
            default:
                include __DIR__ . '/r_dashboard.php';
        }
        ?>
        </div>
        <footer class="rs-footer no-print">Mikhmon Reseller · Kelola voucher lebih cepat dan terukur</footer>
    </main>
    <nav class="rs-mobile-nav no-print" aria-label="Navigasi seluler">
        <a class="<?=$page=='dashboard'?'active':''?>" href="index.php?page=dashboard"><i class="fa fa-dashboard"></i><span>Home</span></a>
        <a class="<?=$page=='generate'?'active':''?>" href="index.php?page=generate"><i class="fa fa-plus-circle"></i><span>Beli</span></a>
        <a class="<?=$page=='vouchers'?'active':''?>" href="index.php?page=vouchers"><i class="fa fa-ticket"></i><span>Voucher</span></a>
        <a class="<?=$page=='transactions'?'active':''?>" href="index.php?page=transactions"><i class="fa fa-exchange"></i><span>Transaksi</span></a>
        <a class="<?=$page=='profile'?'active':''?>" href="index.php?page=profile"><i class="fa fa-user"></i><span>Profil</span></a>
    </nav>
</div>
<script src="assets/js/mikhmon-ui.<?=$theme?>.min.js"></script>
<script src="assets/js/reseller-modern.js"></script>
</body>
</html>
