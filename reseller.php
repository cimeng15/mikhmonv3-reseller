<?php
/**
 * Mikhmon V3 - Reseller Portal
 * Main entry point for reseller panel
 */
error_reporting(0);
session_start();

require_once __DIR__ . '/reseller/database.php';
require_once __DIR__ . '/reseller/auth.php';
require_once __DIR__ . '/reseller/models.php';
require_once __DIR__ . '/lib/routeros_api.class.php';
require_once __DIR__ . '/include/config.php';

// Handle Login
if (isset($_POST['reseller_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (resellerLogin($username, $password)) {
        header('Location: reseller.php');
        exit;
    } else {
        $loginError = 'Username atau password salah';
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    resellerLogout();
    header('Location: reseller.php');
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
    <link rel="icon" href="img/favicon.png">
    <link rel="stylesheet" href="css/mikhmon-ui.<?=$theme?>.min.css">
    <link rel="stylesheet" href="css/font-awesome/css/font-awesome.min.css">
    <style>
        .login-box { max-width: 400px; margin: 80px auto; }
        .login-logo { text-align: center; margin-bottom: 20px; }
        .login-logo img { width: 80px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-box">
            <div class="login-logo">
                <img src="img/logo.png" alt="Mikhmon">
                <h3>Reseller Panel</h3>
            </div>
            <div class="panel panel-default">
                <div class="panel-body">
                    <?php if(isset($loginError)): ?>
                    <div class="alert alert-danger"><i class="fa fa-warning"></i> <?=$loginError?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fa fa-user"></i> Username</label>
                            <input type="text" name="username" class="form-control" required autofocus>
                        </div>
                        <div class="form-group">
                            <label><i class="fa fa-lock"></i> Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" name="reseller_login" class="btn btn-primary btn-block">
                            <i class="fa fa-sign-in"></i> Login
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted"><small>Mikhmon V3 Reseller System</small></p>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// ====== AUTHENTICATED RESELLER AREA ======
$reseller = getCurrentReseller();
if (!$reseller) {
    resellerLogout();
    header('Location: reseller.php');
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
    <link rel="icon" href="img/favicon.png">
    <link rel="stylesheet" href="css/mikhmon-ui.<?=$theme?>.min.css">
    <link rel="stylesheet" href="css/font-awesome/css/font-awesome.min.css">
    <script src="js/jquery.min.js"></script>
    <style>
        .reseller-nav { margin-bottom: 20px; }
        .balance-display {
            font-size: 24px; font-weight: bold;
            padding: 8px 15px; border-radius: 4px;
            display: inline-block;
        }
        .stat-box { text-align: center; padding: 15px; }
        .stat-box h2 { margin: 5px 0; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-default no-print">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#resellerNav">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="reseller.php">
                    <img src="img/logo.png" style="height:20px;display:inline"> Mikhmon Reseller
                </a>
            </div>
            <div class="collapse navbar-collapse" id="resellerNav">
                <ul class="nav navbar-nav">
                    <li class="<?=$page=='dashboard'?'active':''?>">
                        <a href="reseller.php?page=dashboard"><i class="fa fa-dashboard"></i> Dashboard</a>
                    </li>
                    <li class="<?=$page=='generate'?'active':''?>">
                        <a href="reseller.php?page=generate"><i class="fa fa-ticket"></i> Beli Voucher</a>
                    </li>
                    <li class="<?=$page=='transactions'?'active':''?>">
                        <a href="reseller.php?page=transactions"><i class="fa fa-history"></i> Transaksi</a>
                    </li>
                    <li class="<?=$page=='profile'?'active':''?>">
                        <a href="reseller.php?page=profile"><i class="fa fa-user"></i> Profil</a>
                    </li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li>
                        <a href="#" style="cursor:default">
                            <i class="fa fa-money"></i>
                            Saldo: <strong class="text-success">Rp <?=number_format($reseller['balance'], 0, ',', '.')?></strong>
                        </a>
                    </li>
                    <li><a href="reseller.php?logout=1"><i class="fa fa-sign-out"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <?php
        switch ($page) {
            case 'dashboard':
                include __DIR__ . '/reseller/r_dashboard.php';
                break;
            case 'generate':
                include __DIR__ . '/reseller/r_generate.php';
                break;
            case 'transactions':
                include __DIR__ . '/reseller/r_transactions.php';
                break;
            case 'print':
                include __DIR__ . '/reseller/r_print_voucher.php';
                break;
            case 'profile':
                include __DIR__ . '/reseller/r_profile.php';
                break;
            default:
                include __DIR__ . '/reseller/r_dashboard.php';
        }
        ?>
    </div>

    <footer class="text-center text-muted no-print" style="padding:20px">
        <small>Mikhmon V3 Reseller System | <?=htmlspecialchars($reseller['name'])?></small>
    </footer>

    <script src="js/mikhmon-ui.<?=$theme?>.min.js"></script>
</body>
</html>
