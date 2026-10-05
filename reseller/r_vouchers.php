<?php
/**
 * Mikhmon V3 - Reseller Voucher List
 * Only shows hotspot users tagged with the current reseller username.
 */
require_once __DIR__ . '/router_helpers.php';

$voucherSession = trim($_GET['session'] ?? '');
$voucherSearch = trim($_GET['search'] ?? '');
$voucherProfile = trim($_GET['profile'] ?? '');
$voucherStatus = trim($_GET['status'] ?? '');
$voucherRows = [];
$voucherError = '';

if ($voucherSession !== '') {
    $result = resellerFetchOwnedVouchers($voucherSession, $reseller['username'], $reseller['id'], $allSessions);
    if (!$result['success']) {
        $voucherError = $result['error'];
    } else {
        $voucherRows = $result['vouchers'];
    }
} else {
    foreach ($allSessions as $sessionName) {
        $result = resellerFetchOwnedVouchers($sessionName, $reseller['username'], $reseller['id'], $allSessions);
        if ($result['success']) {
            $voucherRows = array_merge($voucherRows, $result['vouchers']);
        } elseif ($voucherError === '') {
            $voucherError = $result['error'];
        }
    }
}

$voucherProfiles = [];
foreach ($voucherRows as $voucher) {
    $profileName = $voucher['profile'] ?? '';
    if ($profileName !== '') $voucherProfiles[$profileName] = true;
}

$voucherRows = array_values(array_filter($voucherRows, function ($voucher) use ($voucherSearch, $voucherProfile, $voucherStatus) {
    $haystack = strtolower(implode(' ', [
        $voucher['name'] ?? '',
        $voucher['profile'] ?? '',
        $voucher['comment'] ?? '',
        $voucher['_session_name'] ?? ''
    ]));

    if ($voucherSearch !== '' && strpos($haystack, strtolower($voucherSearch)) === false) return false;
    if ($voucherProfile !== '' && ($voucher['profile'] ?? '') !== $voucherProfile) return false;

    if ($voucherStatus === 'removed_from_router' && (($voucher['_exists_on_router'] ?? false) === true)) return false;
    if ($voucherStatus === 'expired' && (($voucher['_local_status'] ?? '') !== 'expired')) return false;
    if ($voucherStatus === 'active' && (($voucher['_exists_on_router'] ?? false) !== true || (($voucher['disabled'] ?? 'false') === 'true'))) return false;
    if ($voucherStatus === 'disabled' && (($voucher['_exists_on_router'] ?? false) !== true || (($voucher['disabled'] ?? 'false') !== 'true'))) return false;
    return true;
}));
?>

<div class="row">
    <div class="col-md-12">
        <h3><i class="fa fa-ticket"></i> Voucher Saya</h3>
        <p class="text-muted">Hanya voucher yang dibuat oleh akun <strong><?=htmlspecialchars($reseller['username'])?></strong> yang ditampilkan.</p>
        <hr>
    </div>
</div>

<?php if ($voucherError !== ''): ?>
<div class="alert alert-warning"><i class="fa fa-warning"></i> <?=htmlspecialchars($voucherError)?></div>
<?php endif; ?>

<div class="panel panel-default">
    <div class="panel-heading"><h4><i class="fa fa-filter"></i> Filter Voucher</h4></div>
    <div class="panel-body">
        <form method="GET" class="form-inline">
            <input type="hidden" name="page" value="vouchers">
            <div class="form-group">
                <select name="session" class="form-control">
                    <option value="">Semua Router</option>
                    <?php foreach ($allSessions as $sessionName): ?>
                    <option value="<?=htmlspecialchars($sessionName)?>" <?=$voucherSession === $sessionName ? 'selected' : ''?>><?=htmlspecialchars($sessionName)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <select name="profile" class="form-control">
                    <option value="">Semua Profile</option>
                    <?php foreach (array_keys($voucherProfiles) as $profileName): ?>
                    <option value="<?=htmlspecialchars($profileName)?>" <?=$voucherProfile === $profileName ? 'selected' : ''?>><?=htmlspecialchars($profileName)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">Semua Status</option>
                    <option value="active" <?=$voucherStatus === 'active' ? 'selected' : ''?>>Aktif</option>
                    <option value="disabled" <?=$voucherStatus === 'disabled' ? 'selected' : ''?>>Disabled</option>
                    <option value="expired" <?=$voucherStatus === 'expired' ? 'selected' : ''?>>Expired</option>
                    <option value="removed_from_router" <?=$voucherStatus === 'removed_from_router' ? 'selected' : ''?>>Dihapus dari Router</option>
                </select>
            </div>
            <div class="form-group">
                <input type="search" name="search" class="form-control" value="<?=htmlspecialchars($voucherSearch)?>" placeholder="Cari username/comment">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Tampilkan</button>
            <a href="index.php?page=vouchers" class="btn btn-default"><i class="fa fa-refresh"></i> Reset</a>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><h4><i class="fa fa-list"></i> <?=$voucherRows ? count($voucherRows) : 0?> voucher ditemukan</h4></div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th><th>Username</th><th>Profile</th><th>Router</th><th>Status</th><th>Comment</th><th>Dibuat</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($voucherRows)): ?>
                    <tr><td colspan="8" class="text-center">Belum ada voucher milik Anda.</td></tr>
                <?php else: ?>
                    <?php foreach ($voucherRows as $number => $voucher): ?>
                    <tr>
                        <td><?=($number + 1)?></td>
                        <td><strong><?=htmlspecialchars($voucher['name'] ?? '')?></strong></td>
                        <td><?=htmlspecialchars($voucher['profile'] ?? '-')?></td>
                        <td><?=htmlspecialchars($voucher['_session_name'] ?? '-')?></td>
                        <td>
                            <?php if (($voucher['_exists_on_router'] ?? false) !== true): ?>
                                <span class="label label-default">Dihapus dari Router</span>
                            <?php elseif (($voucher['disabled'] ?? 'false') === 'true'): ?>
                                <span class="label label-danger">Disabled</span>
                            <?php else: ?>
                                <span class="label label-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td><?=htmlspecialchars($voucher['comment'] ?? '')?></td>
                        <td><?=htmlspecialchars($voucher['_local_created_at'] ?? '-')?></td>
                        <td>
                            <a class="btn btn-xs btn-default" href="index.php?page=vouchers&session=<?=urlencode($voucher['_session_name'] ?? '')?>&search=<?=urlencode($voucher['name'] ?? '')?>">
                                <i class="fa fa-search"></i> Detail
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
