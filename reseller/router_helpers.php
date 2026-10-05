<?php
/**
 * Mikhmon V3 - Reseller Router Helpers
 * Shared router/session helpers for reseller-only data access.
 */

if (!function_exists('resellerGetRouterConfig')) {
    function resellerGetRouterConfig($sessionName) {
        global $data;

        if (empty($sessionName) || !isset($data[$sessionName]) || !is_array($data[$sessionName])) {
            return null;
        }

        $sessionData = $data[$sessionName];
        return [
            'session' => $sessionName,
            'iphost' => explode('!', $sessionData[1] ?? '', 2)[1] ?? '',
            'userhost' => explode('@|@', $sessionData[2] ?? '', 2)[1] ?? '',
            'passwdhost' => explode('#|#', $sessionData[3] ?? '', 2)[1] ?? '',
            'hotspotname' => explode('%', $sessionData[4] ?? '', 2)[1] ?? '',
            'dnsname' => explode('^', $sessionData[5] ?? '', 2)[1] ?? '',
            'currency' => explode('&', $sessionData[6] ?? '', 2)[1] ?? 'Rp'
        ];
    }
}

if (!function_exists('resellerSessionIsAllowed')) {
    function resellerSessionIsAllowed($sessionName, $allowedSessions) {
        return !empty($sessionName)
            && is_array($allowedSessions)
            && in_array($sessionName, $allowedSessions, true);
    }
}

if (!function_exists('resellerConnect')) {
    function resellerConnect($sessionName, $allowedSessions) {
        if (!resellerSessionIsAllowed($sessionName, $allowedSessions)) {
            return [null, 'Akses ke router tidak diizinkan untuk reseller ini.'];
        }

        $config = resellerGetRouterConfig($sessionName);
        if (!$config || empty($config['iphost']) || empty($config['userhost'])) {
            return [null, 'Konfigurasi router tidak ditemukan.'];
        }

        $api = new RouterosAPI();
        $api->debug = false;
        if (!$api->connect($config['iphost'], $config['userhost'], decrypt($config['passwdhost']))) {
            return [null, 'Gagal koneksi ke router ' . $sessionName . '.'];
        }

        return [$api, null];
    }
}

if (!function_exists('resellerCommentBelongsTo')) {
    function resellerCommentBelongsTo($comment, $username) {
        $comment = (string)$comment;
        $username = (string)$username;

        if ($comment === '' || $username === '') {
            return false;
        }

        // The generator writes: "... reseller:<username>".
        // Boundaries prevent reseller "ali" from matching "alice".
        $pattern = '/(?:^|\s)reseller:' . preg_quote($username, '/') . '(?:\s|$)/i';
        return (bool)preg_match($pattern, $comment);
    }
}

if (!function_exists('resellerFetchOwnedVouchers')) {
    function resellerFetchOwnedVouchers($sessionName, $resellerUsername, $allowedSessions) {
        [$api, $error] = resellerConnect($sessionName, $allowedSessions);
        if ($error !== null) {
            return ['success' => false, 'error' => $error, 'vouchers' => []];
        }

        try {
            $users = $api->comm('/ip/hotspot/user/print');
            $owned = [];
            if (is_array($users)) {
                foreach ($users as $user) {
                    if (isset($user['!trap']) || !is_array($user)) {
                        continue;
                    }
                    if (resellerCommentBelongsTo($user['comment'] ?? '', $resellerUsername)) {
                        $user['_session_name'] = $sessionName;
                        $owned[] = $user;
                    }
                }
            }
            return ['success' => true, 'error' => null, 'vouchers' => $owned];
        } finally {
            $api->disconnect();
        }
    }
}
