<?php
/**
 * Mikhmon V3 - Reseller Profile
 */
$successMsg = '';
$errorMsg = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass)) {
        $errorMsg = 'Password lama dan baru harus diisi';
    } elseif ($newPass !== $confirmPass) {
        $errorMsg = 'Password baru dan konfirmasi tidak cocok';
    } elseif (strlen($newPass) < 4) {
        $errorMsg = 'Password baru minimal 4 karakter';
    } else {
        // Verify current password
        $db = getDB();
        $stmt = $db->prepare("SELECT password FROM resellers WHERE id = ?");
        $stmt->execute([$reseller['id']]);
        $row = $stmt->fetch();

        if ($row && password_verify($currentPass, $row['password'])) {
            updateReseller($reseller['id'], ['password' => $newPass]);
            logResellerAction($reseller['id'], 'change_password', 'Password diubah');
            $successMsg = 'Password berhasil diubah';
        } else {
            $errorMsg = 'Password lama salah';
        }
    }
}

$logs = getResellerLogs($reseller['id'], 30);
?>

<div class="rs-page-head">
    <div><h1>Profil saya</h1><p>Informasi akun, keamanan, dan riwayat aktivitas reseller.</p></div>
</div>

<div class="row">
    <!-- Profile Info -->
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><h4><i class="fa fa-id-card"></i> Informasi Akun</h4></div>
            <div class="panel-body">
                <table class="table">
                    <tr><td width="40%"><strong>Username</strong></td><td><?=htmlspecialchars($reseller['username'])?></td></tr>
                    <tr><td><strong>Nama</strong></td><td><?=htmlspecialchars($reseller['name'])?></td></tr>
                    <tr><td><strong>Telepon</strong></td><td><?=htmlspecialchars($reseller['phone'] ?: '-')?></td></tr>
                    <tr><td><strong>Saldo</strong></td><td class="text-success"><strong>Rp <?=number_format($reseller['balance'], 0, ',', '.')?></strong></td></tr>
                    <tr><td><strong>Skema harga</strong></td><td>Price profil untuk pembelian</td></tr>
                    <tr><td><strong>Status</strong></td><td><span class="label label-<?=$reseller['status']==='active'?'success':'danger'?>"><?=$reseller['status']?></span></td></tr>
                    <tr><td><strong>Bergabung</strong></td><td><?=date('d/m/Y', strtotime($reseller['created_at']))?></td></tr>
                    <tr>
                        <td><strong>Router Akses</strong></td>
                        <td>
                            <?php if(empty($reseller['allowed_sessions'])): ?>
                                <span class="text-muted">Semua router</span>
                            <?php else: ?>
                                <?=htmlspecialchars($reseller['allowed_sessions'])?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Change Password -->
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><h4><i class="fa fa-lock"></i> Ubah Password</h4></div>
            <div class="panel-body">
                <?php if($successMsg): ?>
                    <div class="alert alert-success"><?=$successMsg?></div>
                <?php endif; ?>
                <?php if($errorMsg): ?>
                    <div class="alert alert-danger"><?=$errorMsg?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Password Lama</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="new_password" class="form-control" required minlength="4">
                    </div>
                    <div class="form-group">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-warning btn-block">
                        <i class="fa fa-save"></i> Ubah Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Activity Log -->
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading"><h4><i class="fa fa-clock-o"></i> Riwayat Aktivitas</h4></div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-striped rs-card-table">
                        <thead>
                            <tr><th>Waktu</th><th>Aksi</th><th>Detail</th><th>IP</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach($logs as $log): ?>
                            <tr>
                                <td data-label="Waktu"><?=date('d/m/Y H:i:s', strtotime($log['created_at']))?></td>
                                <td data-label="Aksi"><span class="label label-default"><?=htmlspecialchars($log['action'])?></span></td>
                                <td data-label="Detail"><?=htmlspecialchars($log['detail'])?></td>
                                <td data-label="IP"><small><?=htmlspecialchars($log['ip_address'])?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
