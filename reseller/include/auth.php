<?php
/*
 *  Mikhmon V3 — Reseller Panel
 *  Authentication helper for reseller sessions.
 */
if (substr($_SERVER["REQUEST_URI"], -8) == "auth.php") {
    header("Location:../../admin.php?id=login");
    exit;
}

require_once __DIR__ . '/db.php';

/**
 * Authenticate a reseller by username + plaintext password.
 * Returns the reseller record on success, false otherwise.
 */
function reseller_authenticate($username, $password) {
    $r = reseller_get($username);
    if (!$r) return false;
    if ($r['status'] !== 'active') return false;
    if (!password_verify($password, $r['password'])) return false;
    return $r;
}

/**
 * Call at the top of every protected page.
 * Redirects to login when the reseller session is missing.
 */
function reseller_require_login() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['reseller_user'])) {
        header("Location:../reseller.php?page=login");
        exit;
    }
    // Refresh reseller data on every request so balance is always current
    $r = reseller_get($_SESSION['reseller_user']);
    if (!$r || $r['status'] !== 'active') {
        session_destroy();
        header("Location:../reseller.php?page=login&err=disabled");
        exit;
    }
    return $r;
}

/**
 * Start a reseller session after successful login.
 */
function reseller_login($reseller) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['reseller_user']   = $reseller['username'];
    $_SESSION['reseller_name']   = $reseller['name'];
    $_SESSION['reseller_logged'] = time();
}

/**
 * Destroy the reseller session.
 */
function reseller_logout() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    unset($_SESSION['reseller_user'], $_SESSION['reseller_name'], $_SESSION['reseller_logged']);
    session_destroy();
}
?>
