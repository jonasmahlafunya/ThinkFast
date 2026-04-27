<?php
/**
 * Banking Operations API
 * Handles deposits and withdrawals
 */

require_once 'db_config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, ['error' => 'Invalid request method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    send_json_response(false, ['error' => 'Invalid request data'], 400);
}

$action = sanitize_input($input['action']);
$user_id = isset($input['user_id']) ? intval($input['user_id']) : null;
$amount = isset($input['amount']) ? floatval($input['amount']) : 0;

if (!$user_id) {
    send_json_response(false, ['error' => 'User ID is required']);
}

if ($amount <= 0) {
    send_json_response(false, ['error' => 'Invalid amount']);
}

try {
    switch ($action) {
        case 'deposit':
            handleDeposit($pdo, $user_id, $amount);
            break;
            
        case 'withdraw':
            handleWithdraw($pdo, $user_id, $amount);
            break;
            
        case 'get_balance':
            getBalance($pdo, $user_id);
            break;
            
        default:
            send_json_response(false, ['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    log_error("Banking error: " . $e->getMessage(), [
        'action' => $action,
        'user_id' => $user_id
    ]);
    send_json_response(false, ['error' => 'Banking operation failed'], 500);
}

/**
 * Handle deposit
 */
function handleDeposit($pdo, $user_id, $amount) {
    // Validate minimum deposit
    if ($amount < 10) {
        send_json_response(false, ['error' => 'Minimum deposit is R10']);
    }
    
    // Validate maximum deposit (anti-fraud)
    if ($amount > 10000) {
        send_json_response(false, ['error' => 'Maximum deposit is R10,000. Please contact support for larger amounts.']);
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update user balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("User not found");
        }
        
        // Get new balance
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("Failed to retrieve balance");
        }
        
        // Record transaction
        $description = "Deposit: R" . number_format($amount, 2);
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, type, amount, balance_after, description, created_at
            ) VALUES (?, 'deposit', ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $amount, $user['balance'], $description]);
        
        $pdo->commit();
        
        send_json_response(true, [
            'message' => 'Deposit successful!',
            'new_balance' => floatval($user['balance']),
            'transaction_id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        log_error("Deposit failed: " . $e->getMessage(), ['user_id' => $user_id, 'amount' => $amount]);
        throw $e;
    }
}

/**
 * Handle withdrawal
 */
function handleWithdraw($pdo, $user_id, $amount) {
    // Validate minimum withdrawal
    if ($amount < 50) {
        send_json_response(false, ['error' => 'Minimum withdrawal is R50']);
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Check current balance
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $current_balance = floatval($user['balance']);
        
        // Check sufficient balance
        if ($current_balance < $amount) {
            $pdo->rollBack();
            send_json_response(false, [
                'error' => 'Insufficient balance',
                'current_balance' => $current_balance,
                'requested_amount' => $amount
            ]);
        }
        
        // Update user balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);
        
        $new_balance = $current_balance - $amount;
        
        // Record transaction
        $description = "Withdrawal: R" . number_format($amount, 2);
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, type, amount, balance_after, description, created_at
            ) VALUES (?, 'withdrawal', ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $amount, $new_balance, $description]);
        
        $pdo->commit();
        
        send_json_response(true, [
            'message' => 'Withdrawal successful!',
            'new_balance' => $new_balance,
            'transaction_id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        log_error("Withdrawal failed: " . $e->getMessage(), ['user_id' => $user_id, 'amount' => $amount]);
        throw $e;
    }
}

/**
 * Get user balance
 */
function getBalance($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            send_json_response(false, ['error' => 'User not found'], 404);
        }
        
        send_json_response(true, [
            'balance' => floatval($user['balance'])
        ]);
        
    } catch (PDOException $e) {
        log_error("Get balance failed: " . $e->getMessage(), ['user_id' => $user_id]);
        send_json_response(false, ['error' => 'Failed to retrieve balance'], 500);
    }
}