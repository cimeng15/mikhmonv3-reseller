<?php
/*
 *  Mikhmon V3 — Reseller Panel · AJAX API
 *  Internal endpoints used by the front-end JS.
 *
 *  ?page=api&action=<action>&session=<session>
 *
 *  Actions:
 *    profile_info   — get profile details (price, validity)
 *    balance        — get current balance
 */
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'profile_info':
        $profName = $_GET['name'] ?? '';
        $API = new RouterosAPI();
        $API->debug = false;
        if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
            echo json_encode(['error' => 'Not connected']);
            break;
        }
        $getprofile = $API->comm("/ip/hotspot/user/profile/print", ["?name" => $profName]);
        if (empty($getprofile)) {
            echo json_encode(['error' => 'Profile not found']);
            break;
        }
        $p = $getprofile[0];
        $ponlogin = $p['on-login'];
        $parts    = explode(',', $ponlogin);
        echo json_encode([
            'name'     => $p['name'],
            'validity' => $parts[3] ?? '',
            'price'    => (float)($parts[2] ?? 0),
            'sprice'   => (float)($parts[4] ?? 0),
            'shared'   => $p['shared-users'],
            'ratelimit'=> $p['rate-limit'] ?? '',
        ]);
        break;

    case 'balance':
        echo json_encode([
            'balance'  => $reseller['balance'],
            'username' => $reseller['username'],
            'name'     => $reseller['name'],
        ]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
        break;
}
?>
