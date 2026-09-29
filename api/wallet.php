<?php
/**
 * RESTful Wallet API Endpoints - Geo Auction System
 *
 * Endpoints:
 * - POST /api/wallet.php?action=deposit
 * - POST /api/wallet.php?action=withdraw
 * - GET  /api/wallet.php?action=balance
 * - GET  /api/wallet.php?action=history
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/WalletService.php';

// Authentication Check: Session or Bearer Token
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    // Check Authorization header for Bearer token if session is not active
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/', $auth, $matches)) {
        // Token auth lookup simulation
        $token = $matches[1];
        $t_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE remember_token = ? LIMIT 1");
        mysqli_stmt_bind_param($t_stmt, "s", $token);
        mysqli_stmt_execute($t_stmt);
        $t_row = mysqli_fetch_assoc(mysqli_stmt_get_result($t_stmt));
        if ($t_row) {
            $user_id = (int)$t_row['id'];
        }
        mysqli_stmt_close($t_stmt);
    }
}

if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'UNAUTHORIZED',
            'message' => 'Authentication required. Please log in or provide a valid Bearer token.'
        ]
    ]);
    exit();
}

$walletService = new WalletService($conn);
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$idempotency_key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null;

// Read JSON input body if application/json
$raw_input = file_get_contents('php://input');
$body = json_decode($raw_input, true) ?? $_POST;

try {
    switch ($action) {
        // -------------------------------------------------------------
        // GET /api/wallet.php?action=balance
        // -------------------------------------------------------------
        case 'balance':
            if ($method !== 'GET') {
                http_response_code(405);
                throw new Exception('Method Not Allowed. Use GET.');
            }

            $data = $walletService->getBalance($user_id);
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;

        // -------------------------------------------------------------
        // POST /api/wallet.php?action=deposit
        // -------------------------------------------------------------
        case 'deposit':
            if ($method !== 'POST') {
                http_response_code(405);
                throw new Exception('Method Not Allowed. Use POST.');
            }

            $amount = (float)($body['amount'] ?? 0);
            $gateway = trim($body['payment_method'] ?? 'JAZZCASH');
            $ref = trim($body['payment_reference'] ?? ('DEP-' . strtoupper(substr(uniqid(), -8))));

            if ($amount <= 0) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => ['code' => 'INVALID_AMOUNT', 'message' => 'Deposit amount must be positive.']
                ]);
                exit();
            }

            $result = $walletService->deposit($user_id, $amount, $ref, $gateway, $idempotency_key);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;

        // -------------------------------------------------------------
        // POST /api/wallet.php?action=withdraw
        // -------------------------------------------------------------
        case 'withdraw':
            if ($method !== 'POST') {
                http_response_code(405);
                throw new Exception('Method Not Allowed. Use POST.');
            }

            $amount = (float)($body['amount'] ?? 0);
            $destination = $body['destination'] ?? [];

            $balance = $walletService->getBalance($user_id);
            if ($amount > $balance['available_balance']) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'INSUFFICIENT_AVAILABLE_BALANCE',
                        'message' => 'Available balance (' . number_format($balance['available_balance'], 2) . ') is insufficient for requested withdrawal of ' . number_format($amount, 2) . '.'
                    ]
                ]);
                exit();
            }

            // Deduct from wallet and queue payout
            $bank_name = trim($destination['bank_name'] ?? ($body['bank_name'] ?? ''));
            $acc_title = trim($destination['account_title'] ?? ($body['account_title'] ?? ''));
            $acc_num   = trim($destination['account_number'] ?? ($body['account_number'] ?? ''));

            if ($amount < 500) {
                http_response_code(422);
                throw new Exception('Minimum withdrawal amount is Rs. 500.');
            }

            // Process withdrawal in transaction
            mysqli_begin_transaction($conn);
            $w_stmt = mysqli_prepare($conn, "SELECT balance FROM users WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($w_stmt, "i", $user_id);
            mysqli_stmt_execute($w_stmt);
            $curr_b = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($w_stmt))['balance'] ?? 0);
            mysqli_stmt_close($w_stmt);

            $new_b = $curr_b - $amount;
            $u_up = mysqli_prepare($conn, "UPDATE users SET balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($u_up, "di", $new_b, $user_id);
            mysqli_stmt_execute($u_up);
            mysqli_stmt_close($u_up);

            // Also update wallets table if exists
            mysqli_query($conn, "UPDATE wallets SET available_balance = $new_b WHERE user_id = $user_id");

            $payout_ref = 'WD-' . strtoupper(substr(uniqid(), -8));
            $desc = "Withdrawal to " . $bank_name . " (" . $acc_title . " - " . $acc_num . ")";
            $tx_stmt = mysqli_prepare($conn, "
                INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, reference_id, description, status, created_at)
                VALUES (?, 'withdraw', ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $neg_amount = -$amount;
            mysqli_stmt_bind_param($tx_stmt, "idddss", $user_id, $neg_amount, $curr_b, $new_b, $payout_ref, $desc);
            mysqli_stmt_execute($tx_stmt);
            mysqli_stmt_close($tx_stmt);

            mysqli_commit($conn);

            http_response_code(202);
            echo json_encode([
                'success' => true,
                'data' => [
                    'payout_request_id' => $payout_ref,
                    'amount'            => $amount,
                    'available_balance' => $new_b,
                    'status'            => 'PENDING_REVIEW'
                ]
            ]);
            break;

        // -------------------------------------------------------------
        // GET /api/wallet.php?action=history
        // -------------------------------------------------------------
        case 'history':
            if ($method !== 'GET') {
                http_response_code(405);
                throw new Exception('Method Not Allowed. Use GET.');
            }

            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $stmt = mysqli_prepare($conn, "
                SELECT id, type, amount, balance_before, balance_after, reference_id, description, status, created_at
                FROM transactions
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $limit, $offset);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $items = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $items[] = $row;
            }
            mysqli_stmt_close($stmt);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'items' => $items,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit
                    ]
                ]
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Invalid action. Supported actions: balance, deposit, withdraw, history.']
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}
