<?php
/*
 *  Mikhmon V3 — Reseller Panel
 *  Flat-file JSON database helper for reseller data.
 *
 *  Files stored in reseller/data/:
 *    resellers.json        — reseller accounts
 *    transactions_<id>.json — per-reseller transaction log
 */
if (substr($_SERVER["REQUEST_URI"], -6) == "db.php") {
    header("Location:../../admin.php?id=login");
    exit;
}

define('RESELLER_DATA_DIR', __DIR__ . '/../data');

/**
 * Read a JSON data file. Returns array (empty if file missing).
 */
function reseller_db_read($filename) {
    $path = RESELLER_DATA_DIR . '/' . $filename;
    if (!file_exists($path)) return [];
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Write array to a JSON data file (atomic via temp+rename).
 */
function reseller_db_write($filename, $data) {
    $dir = RESELLER_DATA_DIR;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = $dir . '/' . $filename;
    $tmp  = $path . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    rename($tmp, $path);
}

/* ---------- Reseller CRUD ---------- */

function reseller_list() {
    return reseller_db_read('resellers.json');
}

function reseller_get($username) {
    $all = reseller_list();
    foreach ($all as $r) {
        if ($r['username'] === $username) return $r;
    }
    return null;
}

function reseller_save($reseller) {
    $all = reseller_list();
    $found = false;
    foreach ($all as &$r) {
        if ($r['username'] === $reseller['username']) {
            $r = $reseller;
            $found = true;
            break;
        }
    }
    unset($r);
    if (!$found) $all[] = $reseller;
    reseller_db_write('resellers.json', $all);
}

function reseller_delete($username) {
    $all = reseller_list();
    $all = array_values(array_filter($all, function($r) use ($username) {
        return $r['username'] !== $username;
    }));
    reseller_db_write('resellers.json', $all);
    // also clean up transaction log
    $txfile = RESELLER_DATA_DIR . '/transactions_' . md5($username) . '.json';
    if (file_exists($txfile)) unlink($txfile);
}

/* ---------- Transaction log ---------- */

function reseller_tx_file($username) {
    return 'transactions_' . md5($username) . '.json';
}

function reseller_tx_list($username) {
    return reseller_db_read(reseller_tx_file($username));
}

function reseller_tx_add($username, $tx) {
    $all = reseller_tx_list($username);
    $tx['id']        = uniqid('tx_');
    $tx['timestamp'] = date('Y-m-d H:i:s');
    array_unshift($all, $tx); // newest first
    reseller_db_write(reseller_tx_file($username), $all);
    return $tx;
}

/**
 * Create a default reseller account (for bootstrapping / admin use).
 */
function reseller_create_default() {
    if (reseller_get('reseller') !== null) return;
    reseller_save([
        'username'        => 'reseller',
        'password'        => password_hash('reseller123', PASSWORD_DEFAULT),
        'name'            => 'Default Reseller',
        'balance'         => 0,        // saldo in smallest currency unit
        'allowed_sessions'=> [],       // empty = all sessions
        'allowed_profiles'=> [],       // empty = all profiles
        'discount'        => 0,        // percent discount on selling price
        'status'          => 'active', // active | disabled
        'created_at'      => date('Y-m-d H:i:s'),
    ]);
}
?>
