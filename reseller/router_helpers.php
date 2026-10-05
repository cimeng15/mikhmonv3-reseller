<?php
/**
 * Mikhmon V3 - Reseller Router Helpers
 * Shared router/session helpers for reseller-only data access.
 */

require_once __DIR__ . '/models.php';

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

function updateVoucherStatusesFromRouter($resellerId, $sessionName, $liveUsers) {
    $liveUsernames = [];
    foreach ((array)$liveUsers as $user) {
        if (is_array($user) && !empty($user['name'])) $liveUsernames[] = (string)$user['name'];
    }
    return markVouchersMissingFromRouter($resellerId, $sessionName, $liveUsernames);
}


    function resellerFetchOwnedVouchers($sessionName, $resellerUsername, $resellerId, $allowedSessions) {
        [$api, $error] = resellerConnect($sessionName, $allowedSessions);
        if ($error !== null) {
            return ['success' => false, 'error' => $error, 'vouchers' => []];
        }

        try {
            $users = $api->comm('/ip/hotspot/user/print');
            $owned = [];
            $liveUsernames = [];
            if (is_array($users)) {
                foreach ($users as $user) {
                    if (isset($user['!trap']) || !is_array($user)) {
                        continue;
                    }
                    $username = (string)($user['name'] ?? '');
                    $comment = (string)($user['comment'] ?? '');
                    if (!resellerCommentBelongsTo($comment, $resellerUsername)) {
                        continue;
                    }
                    $liveUsernames[] = $username;
                    $user['_session_name'] = $sessionName;
                    $user['_exists_on_router'] = true;
                    $owned[] = $user;
                }
            }

            markVouchersMissingFromRouter($resellerId, $sessionName, $liveUsernames);
            // Historical local records remain visible after router deletion.
            $localRows = getResellerVouchers($resellerId, [
                'session_name' => $sessionName,
                'limit' => 500
            ]);
            $liveNames = [];
            foreach ($owned as $live) {
                $liveNames[(string)($live['name'] ?? '')] = true;
            }
            foreach ($localRows as $local) {
                $localName = (string)$local['username'];
                if (!isset($liveNames[$localName])) {
                    $owned[] = [
                        'name' => $localName,
                        'password' => $local['password'],
                        'profile' => $local['profile'],
                        'comment' => $local['comment'],
                        'disabled' => $local['status'] === 'disabled' ? 'true' : 'false',
                        '_session_name' => $local['session_name'],
                        '_exists_on_router' => false,
                        '_local_status' => $local['status'],
                        '_local_created_at' => $local['created_at']
                    ];
                }
            }
            return ['success' => true, 'error' => null, 'vouchers' => $owned];
        } finally {
            $api->disconnect();
        }
    }

