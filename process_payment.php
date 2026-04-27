<?php
/**
 * Payment Processing API
 * Handles deposits and withdrawals via payment gateway
 * 
 * NOTE: This is a simplified implementation. In production, integrate with
 * real payment gateways like PayFast, Peach Payments, or Ozow
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

try {
    switch ($action) {
        case 'deposit':
            processDeposit($pdo, $input);
            break;
            
        case 'withdraw':
            processWithdrawal($pdo, $input);
            break;
            
        case 'get_transaction_history':
            getTransactionHistory($pdo, $input);
            break;
            
        default:
            send_json_response(false, ['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    log_error("Payment processing error: " . $e->getMessage(), ['action' => $action]);
    send_json_response(false, ['error' => 'Payment processing failed'], 500);
}

/**
 * Process deposit
 */
function processDeposit($pdo, $input) {
    // Validate required fields
    if (empty($input['user_id']) || empty($input['amount'])) {
        send_json_response(false, ['error' => 'User ID and amount are required']);
    }
    
    $user_id = intval($input['user_id']);
    $amount = floatval($input['amount']);
    $payment_method = isset($input['payment_method']) ? sanitize_input($input['payment_method']) : 'card';
    $bank_details = isset($input['bank_details']) ? $input['bank_details'] : null;
    
    // Validate amount
    if ($amount < 10) {
        send_json_response(false, ['error' => 'Minimum deposit is R10']);
    }
    
    if ($amount > 10000) {
        send_json_response(false, ['error' => 'Maximum deposit is R10,000']);
    }
    
    // Verify user exists
    $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        send_json_response(false, ['error' => 'User not found'], 404);
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Generate transaction reference
        $transaction_ref = 'DEP-' . $user_id . '-' . time() . '-' . rand(1000, 9999);
        
        // In production, this would call a payment gateway API
        // For now, we simulate a successful payment
        $status = 'completed'; // Simulate successful payment
        
        // Record card transaction
        $bank_details_json = $bank_details ? json_encode($bank_details) : null;
        $stmt = $pdo->prepare("
            INSERT INTO card_transactions (
                user_id, type, amount, status, payment_method, 
                transaction_reference, bank_details, created_at
            ) VALUES (?, 'deposit', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id, $amount, $status, $payment_method, 
            $transaction_ref, $bank_details_json
        ]);
        
        if ($status === 'completed') {
            // Update user balance
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$amount, $user_id]);
            
            // Get updated balance
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $updated_user = $stmt->fetch();
            
            // Record transaction in main transactions table
            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    user_id, type, amount, balance_after, 
                    description, created_at
                ) VALUES (?, 'deposit', ?, ?, ?, NOW())
            ");
            $description = "Deposit via " . $payment_method . " - R" . number_format($amount, 2);
            $stmt->execute([$user_id, $amount, $updated_user['balance'], $description]);
            
            $pdo->commit();
            
            send_json_response(true, [
                'message' => 'Deposit successful!',
                'new_balance' => floatval($updated_user['balance']),
                'transaction_reference' => $transaction_ref
            ]);
        } else {
            $pdo->rollBack();
            send_json_response(false, ['error' => 'Payment processing failed']);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Process withdrawal
 */
function processWithdrawal($pdo, $input) {
    // Validate required fields
    if (empty($input['user_id']) || empty($input['amount'])) {
        send_json_response(false, ['error' => 'User ID and amount are required']);
    }
    
    $user_id = intval($input['user_id']);
    $amount = floatval($input['amount']);
    $bank_details = isset($input['bank_details']) ? $input['bank_details'] : null;
    
    // Validate amount
    if ($amount < 50) {
        send_json_response(false, ['error' => 'Minimum withdrawal is R50']);
    }
    
    // Verify user and check balance
    $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        send_json_response(false, ['error' => 'User not found'], 404);
    }
    
    if ($user['balance'] < $amount) {
        send_json_response(false, [
            'error' => 'Insufficient balance',
            'current_balance' => floatval($user['balance'])
        ]);
    }
    
    // Validate bank details
    if (!$bank_details || empty($bank_details['account_number']) || empty($bank_details['bank_name'])) {
        send_json_response(false, ['error' => 'Bank details are required for withdrawal']);
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Generate transaction reference
        $transaction_ref = 'WITH-' . $user_id . '-' . time() . '-' . rand(1000, 9999);
        
        // Withdrawals require manual processing
        $status = 'pending';
        
        // Record card transaction
        $bank_details_json = json_encode($bank_details);
        $stmt = $pdo->prepare("
            INSERT INTO card_transactions (
                user_id, type, amount, status, payment_method, 
                transaction_reference, bank_details, created_at
            ) VALUES (?, 'withdrawal', ?, ?, 'bank_transfer', ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $amount, $status, $transaction_ref, $bank_details_json]);
        
        // Deduct from balance (reserve amount)
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);
        
        // Get updated balance
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $updated_user = $stmt->fetch();
        
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, type, amount, balance_after, 
                description, created_at
            ) VALUES (?, 'withdrawal', ?, ?, ?, NOW())
        ");
        $description = "Withdrawal to bank - R" . number_format($amount, 2) . " (Pending)";
        $stmt->execute([$user_id, $amount, $updated_user['balance'], $description]);
        
        $pdo->commit();
        
        send_json_response(true, [
            'message' => 'Withdrawal request submitted. Processing time: 24-48 hours.',
            'new_balance' => floatval($updated_user['balance']),
            'transaction_reference' => $transaction_ref,
            'status' => $status
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get transaction history
 */
function getTransactionHistory($pdo, $input) {
    if (empty($input['user_id'])) {
        send_json_response(false, ['error' => 'User ID is required']);
    }
    
    $user_id = intval($input['user_id']);
    $limit = isset($input['limit']) ? intval($input['limit']) : 50;
    $offset = isset($input['offset']) ? intval($input['offset']) : 0;
    
    // Ensure limit is reasonable
    $limit = max(1, min($limit, 100));
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id, type, amount, balance_after, description, created_at
            FROM transactions
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $limit, $offset]);
        $transactions = $stmt->fetchAll();
        
        // Format transactions
        $formatted_transactions = array_map(function($t) {
            return [
                'id' => intval($t['id']),
                'type' => $t['type'],
                'amount' => floatval($t['amount']),
                'balance_after' => floatval($t['balance_after']),
                'description' => $t['description'],
                'date' => $t['created_at']
            ];
        }, $transactions);
        
        send_json_response(true, [
            'transactions' => $formatted_transactions,
            'count' => count($formatted_transactions)
        ]);
        
    } catch (PDOException $e) {
        log_error("Get transaction history failed: " . $e->getMessage(), ['user_id' => $user_id]);
        send_json_response(false, ['error' => 'Failed to retrieve transaction history'], 500);
    }
}