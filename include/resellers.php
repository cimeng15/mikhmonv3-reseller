<?php
/*
 *  Reseller data-access layer for Mikhmon V3.
 *
 *  Stores reseller accounts in  data/resellers.json  so nothing
 *  touches the existing config.php / MikroTik flow.
 *
 *  Every reseller record:
 *    id            – unique slug  (lowercase, no spaces)
 *    name          – display name
 *    username      – login username
 *    password      – encrypted with the existing encrypt() helper
 *    email         – contact email  (optional)
 *    phone         – contact phone  (optional)
 *    sessions      – array of session (router) names the reseller may use
 *    profiles      – array of hotspot profiles the reseller may sell
 *    balance       – numeric credit balance (admin-managed)
 *    status        – "active" | "disabled"
 *    created_at    – ISO-8601 timestamp
 *    updated_at    – ISO-8601 timestamp
 */

if (substr($_SERVER["REQUEST_URI"], -13) == "resellers.php") {
    header("Location:./");
    exit;
}

define('RESELLER_FILE', __DIR__ . '/../data/resellers.json');

/**
 * Make sure the data directory & file exist.
 */
function _reseller_init() {
    $dir = dirname(RESELLER_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!file_exists(RESELLER_FILE)) {
        file_put_contents(RESELLER_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
}

/**
 * Return all resellers as an associative array keyed by id.
 */
function resellers_all() {
    _reseller_init();
    $json = file_get_contents(RESELLER_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Return a single reseller by id, or null.
 */
function reseller_get($id) {
    $all = resellers_all();
    return isset($all[$id]) ? $all[$id] : null;
}

/**
 * Persist the full resellers array back to disk.
 */
function _reseller_save($all) {
    _reseller_init();
    file_put_contents(RESELLER_FILE, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Create a new reseller.  Returns true on success, error string on failure.
 */
function reseller_create($fields) {
    $all = resellers_all();
    $id  = preg_replace('/[^a-z0-9\-]/', '', strtolower($fields['username']));
    if ($id === '' || $id === 'mikhmon') {
        return 'Invalid username.';
    }
    if (isset($all[$id])) {
        return 'Username already exists.';
    }
    $now = date('c');
    $all[$id] = [
        'id'         => $id,
        'name'       => $fields['name']       ?? $id,
        'username'   => $fields['username'],
        'password'   => encrypt($fields['password']),
        'email'      => $fields['email']      ?? '',
        'phone'      => $fields['phone']      ?? '',
        'sessions'   => $fields['sessions']   ?? [],
        'profiles'   => $fields['profiles']   ?? [],
        'balance'    => floatval($fields['balance'] ?? 0),
        'status'     => $fields['status']     ?? 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    _reseller_save($all);
    return true;
}

/**
 * Update an existing reseller.
 */
function reseller_update($id, $fields) {
    $all = resellers_all();
    if (!isset($all[$id])) {
        return 'Reseller not found.';
    }
    $rec = $all[$id];

    if (isset($fields['name']))       $rec['name']     = $fields['name'];
    if (isset($fields['email']))      $rec['email']    = $fields['email'];
    if (isset($fields['phone']))      $rec['phone']    = $fields['phone'];
    if (isset($fields['sessions']))   $rec['sessions'] = $fields['sessions'];
    if (isset($fields['profiles']))   $rec['profiles'] = $fields['profiles'];
    if (isset($fields['status']))     $rec['status']   = $fields['status'];
    if (isset($fields['balance']))    $rec['balance']  = floatval($fields['balance']);
    if (!empty($fields['password']))  $rec['password'] = encrypt($fields['password']);

    $rec['updated_at'] = date('c');
    $all[$id] = $rec;
    _reseller_save($all);
    return true;
}

/**
 * Delete a reseller.
 */
function reseller_delete($id) {
    $all = resellers_all();
    if (!isset($all[$id])) {
        return 'Reseller not found.';
    }
    unset($all[$id]);
    _reseller_save($all);
    return true;
}

/**
 * Adjust a reseller's balance (positive = add credit, negative = deduct).
 */
function reseller_adjust_balance($id, $amount) {
    $all = resellers_all();
    if (!isset($all[$id])) {
        return 'Reseller not found.';
    }
    $all[$id]['balance']    = floatval($all[$id]['balance']) + floatval($amount);
    $all[$id]['updated_at'] = date('c');
    _reseller_save($all);
    return true;
}

/**
 * Toggle status between active / disabled.
 */
function reseller_toggle_status($id) {
    $all = resellers_all();
    if (!isset($all[$id])) return 'Reseller not found.';
    $all[$id]['status'] = ($all[$id]['status'] === 'active') ? 'disabled' : 'active';
    $all[$id]['updated_at'] = date('c');
    _reseller_save($all);
    return true;
}
