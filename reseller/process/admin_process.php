<?php
/**
 * Mikhmon V3 - Admin Reseller Process (AJAX Handler)
 */
error_reporting(0);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["mikhmon"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../models.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    switch ($action) {

        case 'add_reseller':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $discount = floatval($_POST['discount'] ?? 0);
            $allowed_sessions = $_POST['allowed_sessions'] ?? '';

            if (empty($username) || empty($password) || empty($name)) {
                $response = ['success' => false, 'message' => 'Username, password, dan nama wajib diisi'];
                break;
            }

            if (getResellerByUsername($username)) {
                $response = ['success' => false, 'message' => 'Username sudah digunakan'];
                break;
            }

            if (is_array($allowed_sessions)) {
                $allowed_sessions = implode(',', $allowed_sessions);
            }

            $id = createReseller([
                'username' => $username,
                'password' => $password,
                'name' => $name,
                'phone' => $phone,
                'discount' => $discount,
                'allowed_sessions' => $allowed_sessions
            ]);

            $response = ['success' => true, 'message' => 'Reseller berhasil ditambahkan', 'id' => $id];
            break;

        case 'edit_reseller':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'ID tidak valid'];
                break;
            }

            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'discount' => floatval($_POST['discount'] ?? 0),
                'status' => $_POST['status'] ?? 'active'
            ];

            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }

            $allowed_sessions = $_POST['allowed_sessions'] ?? '';
            if (is_array($allowed_sessions)) {
                $allowed_sessions = implode(',', $allowed_sessions);
            }
            $data['allowed_sessions'] = $allowed_sessions;

            updateReseller($id, $data);
            $response = ['success' => true, 'message' => 'Reseller berhasil diupdate'];
            break;

        case 'delete_reseller':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                $response = ['success' => false, 'message' => 'ID tidak valid'];
                break;
            }
            deleteReseller($id);
            $response = ['success' => true, 'message' => 'Reseller berhasil dihapus'];
            break;

        case 'toggle_reseller':
            $id = intval($_POST['id'] ?? 0);
            $reseller = getReseller($id);
            if (!$reseller) {
                $response = ['success' => false, 'message' => 'Reseller tidak ditemukan'];
                break;
            }
            $newStatus = ($reseller['status'] === 'active') ? 'disabled' : 'active';
            updateReseller($id, ['status' => $newStatus]);
            $response = ['success' => true, 'message' => 'Status reseller diubah ke ' . $newStatus, 'status' => $newStatus];
            break;

        case 'add_deposit':
            $reseller_id = intval($_POST['reseller_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($reseller_id <= 0 || $amount <= 0) {
                $response = ['success' => false, 'message' => 'Reseller dan jumlah deposit harus valid'];
                break;
            }

            addDeposit($reseller_id, $amount, $description ?: 'Deposit saldo');
            $reseller = getReseller($reseller_id);
            $response = ['success' => true, 'message' => 'Deposit berhasil. Saldo sekarang: ' . number_format($reseller['balance'], 0, ',', '.')];
            break;

        case 'get_reseller':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $reseller = getReseller($id);
            if ($reseller) {
                unset($reseller['password']);
                $response = ['success' => true, 'data' => $reseller];
            } else {
                $response = ['success' => false, 'message' => 'Reseller tidak ditemukan'];
            }
            break;

        case 'get_transactions':
            $reseller_id = intval($_GET['reseller_id'] ?? 0);
            $filters = [
                'type' => $_GET['type'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? '',
                'limit' => intval($_GET['limit'] ?? 100),
                'offset' => intval($_GET['offset'] ?? 0)
            ];
            $trx = getTransactions($reseller_id ?: null, $filters);
            $response = ['success' => true, 'data' => $trx];
            break;
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
