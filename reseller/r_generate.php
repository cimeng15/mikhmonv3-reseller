<?php
/**
 * Mikhmon V3 - Reseller Voucher Generation
 * Buy vouchers from available router profiles
 */

$selectedSession = $_GET['session'] ?? ($_POST['session'] ?? '');
$profiles = [];
$connectError = '';
$generateResult = null;

// Connect to router and get profiles if session selected
if (!empty($selectedSession) && in_array($selectedSession, $allSessions)) {
    // Read router config
    include __DIR__ . '/../include/readcfg.php';

    // Reconstruct readcfg variables for the selected session
    $session = $selectedSession;
    if (isset($data[$session])) {
        $iphost = explode('!', $data[$session][1])[1] ?? '';
        $userhost = explode('@|@', $data[$session][2])[1] ?? '';
        $passwdhost = explode('#|#', $data[$session][3])[1] ?? '';
        $hotspotname = explode('%', $data[$session][4])[1] ?? '';
        $dnsname = explode('^', $data[$session][5])[1] ?? '';
        $currency = explode('&', $data[$session][6])[1] ?? 'Rp';

        $API = new RouterosAPI();
        $API->debug = false;
        if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
            $profileData = $API->comm("/ip/hotspot/user/profile/print");
            if (is_array($profileData)) {
                foreach ($profileData as $p) {
                    $price = 0;
                    $validity = '';
                    // Parse on-login script for price info
                    if (!empty($p['on-login'])) {
                        $parts = explode(',', $p['on-login']);
                        foreach ($parts as $part) {
                            $part = trim($part);
                            if (strpos($part, ':variable price') !== false) {
                                preg_match('/value=(\d+)/', $part, $m);
                                if (isset($m[1])) $price = intval($m[1]);
                            }
                            if (strpos($part, ':variable validity') !== false) {
                                preg_match('/value=([^\s"]+)/', $part, $m);
                                if (isset($m[1])) $validity = $m[1];
                            }
                        }
                    }
                    // Also check shared-users and rate-limit
                    $profiles[] = [
                        'name' => $p['name'] ?? '',
                        'shared_users' => $p['shared-users'] ?? '1',
                        'rate_limit' => $p['rate-limit'] ?? '',
                        'price' => $price,
                        'validity' => $validity
                    ];
                }
            }
            $API->disconnect();
        } else {
            $connectError = 'Gagal koneksi ke router ' . $selectedSession;
        }
    }
}

// Handle voucher generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_vouchers'])) {
    $session = $_POST['session'] ?? '';
    $profile = $_POST['profile'] ?? '';
    $qty = intval($_POST['qty'] ?? 1);
    $price = floatval($_POST['price'] ?? 0);
    $nameLength = intval($_POST['name_length'] ?? 6);
    $prefix = $_POST['prefix'] ?? '';
    $charset = $_POST['charset'] ?? 'num';

    if ($qty < 1 || $qty > 100) $qty = 1;
    if ($nameLength < 3 || $nameLength > 8) $nameLength = 6;

    // Apply reseller discount
    $discount = $reseller['discount'];
    $priceAfterDiscount = $price - ($price * $discount / 100);
    $totalCost = $priceAfterDiscount * $qty;

    if ($reseller['balance'] < $totalCost) {
        $generateResult = ['error' => "Saldo tidak mencukupi. Butuh Rp " . number_format($totalCost, 0, ',', '.') . " (saldo: Rp " . number_format($reseller['balance'], 0, ',', '.') . ")"];
    } else if (in_array($session, $allSessions) && isset($data[$session])) {
        $iphost = explode('!', $data[$session][1])[1] ?? '';
        $userhost = explode('@|@', $data[$session][2])[1] ?? '';
        $passwdhost = explode('#|#', $data[$session][3])[1] ?? '';
        $hotspotname = explode('%', $data[$session][4])[1] ?? '';

        $API = new RouterosAPI();
        $API->debug = false;

        if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
            $vouchers = [];
            $chars = '0123456789';
            if ($charset === 'lower') $chars = 'abcdefghijklmnopqrstuvwxyz';
            elseif ($charset === 'upper') $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            elseif ($charset === 'mixed') $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            elseif ($charset === 'alphanum') $chars = 'abcdefghjkmnpqrstuvwxyz23456789';

            $serverName = $hotspotname;

            for ($i = 0; $i < $qty; $i++) {
                // Generate random name
                $name = $prefix;
                for ($j = 0; $j < $nameLength; $j++) {
                    $name .= $chars[random_int(0, strlen($chars) - 1)];
                }

                $comment = 'vc-' . date('M/d/Y') . ' ' . $profile . ' reseller:' . $reseller['username'];

                $apiResult = $API->comm("/ip/hotspot/user/add", [
                    "server" => $serverName,
                    "name" => $name,
                    "password" => $name,
                    "profile" => $profile,
                    "comment" => $comment
                ]);

                if (!isset($apiResult['!trap'])) {
                    $vouchers[] = [
                        'username' => $name,
                        'password' => $name,
                        'profile' => $profile,
                        'price' => $price
                    ];
                }
            }

            $API->disconnect();

            if (!empty($vouchers)) {
                $voucherJson = json_encode($vouchers);
                $description = "Beli {$qty}x voucher [{$profile}] @ Rp " . number_format($price, 0, ',', '.') . " (diskon {$discount}%) - Router: {$session}";
                $trxId = deductBalance($reseller['id'], $totalCost, $description, $voucherJson, $session);
                logResellerAction($reseller['id'], 'purchase', "Beli {$qty} voucher {$profile} dari {$session}");

                // Refresh reseller data
                $reseller = getCurrentReseller();

                $generateResult = [
                    'success' => true,
                    'vouchers' => $vouchers,
                    'total_cost' => $totalCost,
                    'trx_id' => $trxId,
                    'qty' => count($vouchers)
                ];
            } else {
                $generateResult = ['error' => 'Gagal generate voucher. Periksa profil dan router.'];
            }
        } else {
            $generateResult = ['error' => 'Gagal koneksi ke router'];
        }
    } else {
        $generateResult = ['error' => 'Session router tidak valid'];
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <h3><i class="fa fa-ticket"></i> Beli Voucher</h3>
        <hr>
    </div>
</div>

<?php if(isset($generateResult['success'])): ?>
<!-- Generation Result -->
<div class="row">
    <div class="col-md-12">
        <div class="alert alert-success">
            <i class="fa fa-check-circle"></i>
            <strong>Berhasil!</strong> <?=$generateResult['qty']?> voucher telah dibuat.
            Total biaya: Rp <?=number_format($generateResult['total_cost'], 0, ',', '.')?>
            | Saldo tersisa: Rp <?=number_format($reseller['balance'], 0, ',', '.')?>
        </div>

        <div class="panel panel-success">
            <div class="panel-heading">
                <h4><i class="fa fa-list"></i> Voucher yang Dibuat
                    <a href="index.php?page=print&trx_id=<?=$generateResult['trx_id']?>" class="btn btn-default btn-sm pull-right" target="_blank">
                        <i class="fa fa-print"></i> Print Voucher
                    </a>
                </h4>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>#</th><th>Username</th><th>Password</th><th>Profile</th><th>Harga</th></tr>
                        </thead>
                        <tbody>
                        <?php $no=1; foreach($generateResult['vouchers'] as $v): ?>
                            <tr>
                                <td><?=$no++?></td>
                                <td><strong><?=htmlspecialchars($v['username'])?></strong></td>
                                <td><?=htmlspecialchars($v['password'])?></td>
                                <td><?=htmlspecialchars($v['profile'])?></td>
                                <td>Rp <?=number_format($v['price'], 0, ',', '.')?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif(isset($generateResult['error'])): ?>
<div class="alert alert-danger"><i class="fa fa-warning"></i> <?=$generateResult['error']?></div>
<?php endif; ?>

<!-- Generate Form -->
<div class="row">
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading"><h4><i class="fa fa-cog"></i> Generate Voucher</h4></div>
            <div class="panel-body">

                <!-- Step 1: Select Session -->
                <form method="GET" class="form-inline" style="margin-bottom:15px">
                    <input type="hidden" name="page" value="generate">
                    <div class="form-group">
                        <label>Pilih Router: </label>
                        <select name="session" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Pilih Router --</option>
                            <?php foreach($allSessions as $s): ?>
                            <option value="<?=htmlspecialchars($s)?>" <?=$selectedSession==$s?'selected':''?>><?=htmlspecialchars($s)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if($connectError): ?>
                    <div class="alert alert-danger"><?=$connectError?></div>
                <?php endif; ?>

                <?php if(!empty($profiles)): ?>
                <!-- Step 2: Select Profile & Generate -->
                <form method="POST">
                    <input type="hidden" name="session" value="<?=htmlspecialchars($selectedSession)?>">
                    <input type="hidden" name="generate_vouchers" value="1">

                    <div class="form-group">
                        <label>User Profile</label>
                        <select name="profile" id="profileSelect" class="form-control" required onchange="updatePrice()">
                            <option value="">-- Pilih Profile --</option>
                            <?php foreach($profiles as $p): ?>
                            <option value="<?=htmlspecialchars($p['name'])?>" data-price="<?=$p['price']?>" data-validity="<?=htmlspecialchars($p['validity'])?>">
                                <?=htmlspecialchars($p['name'])?> - <?=$currency?> <?=number_format($p['price'], 0, ',', '.')?> <?=$p['validity'] ? "({$p['validity']})" : ''?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="price" id="priceInput" value="0">

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Jumlah Voucher</label>
                                <input type="number" name="qty" class="form-control" value="1" min="1" max="100" onchange="updateTotal()" onkeyup="updateTotal()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Panjang Username</label>
                                <select name="name_length" class="form-control">
                                    <option value="4">4 karakter</option>
                                    <option value="5">5 karakter</option>
                                    <option value="6" selected>6 karakter</option>
                                    <option value="7">7 karakter</option>
                                    <option value="8">8 karakter</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tipe Karakter</label>
                                <select name="charset" class="form-control">
                                    <option value="num">Angka (0-9)</option>
                                    <option value="lower">Huruf Kecil (a-z)</option>
                                    <option value="alphanum">Huruf+Angka (mudah dibaca)</option>
                                    <option value="mixed">Campuran (a-Z, 0-9)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Prefix (opsional)</label>
                        <input type="text" name="prefix" class="form-control" placeholder="cth: RSL-" maxlength="10">
                    </div>

                    <hr>
                    <div class="well" id="costSummary" style="display:none">
                        <div class="row">
                            <div class="col-xs-6">Harga per voucher:</div>
                            <div class="col-xs-6 text-right" id="perPrice">-</div>
                        </div>
                        <?php if($reseller['discount'] > 0): ?>
                        <div class="row text-success">
                            <div class="col-xs-6">Diskon reseller (<?=$reseller['discount']?>%):</div>
                            <div class="col-xs-6 text-right" id="discountAmount">-</div>
                        </div>
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-xs-6"><strong>Total Biaya:</strong></div>
                            <div class="col-xs-6 text-right"><strong id="totalCost">-</strong></div>
                        </div>
                        <div class="row">
                            <div class="col-xs-6">Saldo Anda:</div>
                            <div class="col-xs-6 text-right" id="currentBalance">Rp <?=number_format($reseller['balance'], 0, ',', '.')?></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="btnGenerate" disabled>
                        <i class="fa fa-ticket"></i> Generate & Beli Voucher
                    </button>
                </form>

                <script>
                var resellerBalance = <?=$reseller['balance']?>;
                var resellerDiscount = <?=$reseller['discount']?>;

                function updatePrice() {
                    var sel = document.getElementById('profileSelect');
                    var opt = sel.options[sel.selectedIndex];
                    var price = parseFloat(opt.getAttribute('data-price') || 0);
                    document.getElementById('priceInput').value = price;
                    updateTotal();
                }

                function updateTotal() {
                    var price = parseFloat(document.getElementById('priceInput').value || 0);
                    var qty = parseInt(document.querySelector('input[name="qty"]').value || 1);
                    if (price <= 0) {
                        document.getElementById('costSummary').style.display = 'none';
                        document.getElementById('btnGenerate').disabled = true;
                        return;
                    }
                    var discountAmt = price * resellerDiscount / 100;
                    var priceAfterDiscount = price - discountAmt;
                    var total = priceAfterDiscount * qty;

                    document.getElementById('costSummary').style.display = 'block';
                    document.getElementById('perPrice').textContent = 'Rp ' + price.toLocaleString('id-ID');
                    <?php if($reseller['discount'] > 0): ?>
                    document.getElementById('discountAmount').textContent = '- Rp ' + (discountAmt * qty).toLocaleString('id-ID');
                    <?php endif; ?>
                    document.getElementById('totalCost').textContent = 'Rp ' + total.toLocaleString('id-ID');

                    var btn = document.getElementById('btnGenerate');
                    if (total > resellerBalance) {
                        btn.disabled = true;
                        btn.textContent = 'Saldo Tidak Mencukupi';
                        btn.className = 'btn btn-danger btn-lg btn-block';
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-ticket"></i> Generate & Beli Voucher';
                        btn.className = 'btn btn-primary btn-lg btn-block';
                    }
                }
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar Info -->
    <div class="col-md-4">
        <div class="panel panel-info">
            <div class="panel-heading"><h4><i class="fa fa-info-circle"></i> Info</h4></div>
            <div class="panel-body">
                <p><strong>Saldo:</strong> Rp <?=number_format($reseller['balance'], 0, ',', '.')?></p>
                <p><strong>Diskon:</strong> <?=$reseller['discount']?>%</p>
                <p><strong>Router tersedia:</strong></p>
                <ul>
                    <?php foreach($allSessions as $s): ?>
                    <li><?=htmlspecialchars($s)?></li>
                    <?php endforeach; ?>
                </ul>
                <hr>
                <small class="text-muted">
                    <i class="fa fa-info-circle"></i> Pilih router, kemudian pilih profil voucher dan jumlah yang diinginkan.
                    Saldo akan otomatis terpotong sesuai harga.
                </small>
            </div>
        </div>
    </div>
</div>
