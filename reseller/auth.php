<?php
/**
 * Mikhmon V3 - Reseller Authentication
 */

require_once __DIR__ . '/database.php';

function resellerLogin($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM resellers WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $reseller = $stmt->fetch();

    if ($reseller && password_verify($password, $reseller['password'])) {
        $_SESSION['reseller_id'] = $reseller['id'];
        $_SESSION['reseller_user'] = $reseller['username'];
        $_SESSION['reseller_name'] = $reseller['name'];
        logResellerAction($reseller['id'], 'login', 'Login berhasil');
        return true;
    }
    return false;
}

function resellerLogout() {
    if (isset($_SESSION['reseller_id'])) {
        logResellerAction($_SESSION['reseller_id'], 'logout', 'Logout');
    }
    unset($_SESSION['reseller_id'], $_SESSION['reseller_user'], $_SESSION['reseller_name']);
}

function isResellerLoggedIn() {
    return isset($_SESSION['reseller_id']) && !empty($_SESSION['reseller_id']);
}

function getCurrentReseller() {
    if (!isResellerLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM resellers WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['reseller_id']]);
    return $stmt->fetch();
}

function logResellerAction($reseller_id, $action, $detail = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO reseller_logs (reseller_id, action, detail, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$reseller_id, $action, $detail, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // Silent fail for logging
    }
}
