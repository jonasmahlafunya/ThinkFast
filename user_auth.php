<?php
/**
 * User Authentication API
 * Handles user registration, login, and profile management
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
        case 'register':
            handleRegister($pdo, $input);
            break;

        case 'login':
            handleLogin($pdo, $input);
            break;

        case 'get_user_stats':
            getUserStats($pdo, $input);
            break;

        case 'deduct_stake':
            deductStake($pdo, $input);
            break;

        default:
            send_json_response(false, ['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    log_error("Auth error: " . $e->getMessage(), ['action' => $action]);
    send_json_response(false, ['error' => 'An error occurred. Please try again.'], 500);
}

/**
 * Handle user registration
 */
function handleRegister($pdo, $input)
{
    // Validate required fields
    $required = ['email', 'password', 'first_name', 'last_name', 'cellphone'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            send_json_response(false, ['error' => 'All fields are required']);
        }
    }

    // Sanitize inputs
    $email = sanitize_input($input['email']);
    $password = $input['password'];
    $first_name = sanitize_input($input['first_name']);
    $last_name = sanitize_input($input['last_name']);
    $cellphone = sanitize_input($input['cellphone']);
    $referred_by = isset($input['referred_by']) ? sanitize_input($input['referred_by']) : null;

    // Validate email
    if (!validate_email($email)) {
        send_json_response(false, ['error' => 'Invalid email format']);
    }

    // Validate password strength
    if (strlen($password) < 8) {
        send_json_response(false, ['error' => 'Password must be at least 8 characters long']);
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        send_json_response(false, ['error' => 'Password must contain at least one uppercase letter and one number']);
    }

    // Validate phone number (South African format)
    $cellphone = preg_replace('/[^0-9]/', '', $cellphone);
    if (strlen($cellphone) < 10) {
        send_json_response(false, ['error' => 'Invalid cellphone number']);
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        send_json_response(false, ['error' => 'Email already registered']);
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Generate unique referral code
    $referral_code = 'TF-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, password_hash, email, first_name, last_name, 
                cellphone, referral_code, referred_by, balance, 
                welcome_bonus_claimed, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $email,
            $password_hash,
            $email,
            $first_name,
            $last_name,
            $cellphone,
            $referral_code,
            $referred_by,
            0.00,
            0
        ]);

        $user_id = $pdo->lastInsertId();

        // Award welcome bonus
        $welcome_bonus = 10.00;
        $stmt = $pdo->prepare("
            UPDATE users 
            SET balance = balance + ?, welcome_bonus_claimed = 1 
            WHERE id = ?
        ");
        $stmt->execute([$welcome_bonus, $user_id]);

        // Record welcome bonus transaction
        $transaction_ref = 'WB-' . $user_id . '-' . time();
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, type, amount, balance_after, description, created_at
            ) VALUES (?, 'bonus', ?, ?, 'Welcome bonus', NOW())
        ");
        $stmt->execute([$user_id, $welcome_bonus, $welcome_bonus]);

        // Handle referral bonus if applicable
        if (!empty($referred_by)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
            $stmt->execute([$referred_by]);
            $referrer = $stmt->fetch();

            if ($referrer) {
                $referral_bonus = 10.00;

                // Update referrer balance
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET balance = balance + ? 
                    WHERE id = ?
                ");
                $stmt->execute([$referral_bonus, $referrer['id']]);

                // Get referrer's new balance
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$referrer['id']]);
                $referrer_data = $stmt->fetch();

                // Record referral bonus transaction
                $ref_transaction_ref = 'REF-' . $referrer['id'] . '-' . time();
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (
                        user_id, type, amount, balance_after, description, created_at
                    ) VALUES (?, 'bonus', ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $referrer['id'],
                    $referral_bonus,
                    $referrer_data['balance'],
                    'Referral bonus for inviting new user'
                ]);
            }
        }

        $pdo->commit();

        send_json_response(true, [
            'message' => 'Registration successful! You received R10 welcome bonus!',
            'user' => [
                'id' => $user_id,
                'username' => $email,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'cellphone' => $cellphone,
                'balance' => $welcome_bonus,
                'referral_code' => $referral_code
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle user login
 */
function handleLogin($pdo, $input)
{
    // Validate inputs
    if (empty($input['email']) || empty($input['password'])) {
        send_json_response(false, ['error' => 'Email and password are required']);
    }

    $email = sanitize_input($input['email']);
    $password = $input['password'];

    if (!validate_email($email)) {
        send_json_response(false, ['error' => 'Invalid email format']);
    }

    // Get user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify password
    if (!$user || !password_verify($password, $user['password_hash'])) {
        send_json_response(false, ['error' => 'Invalid email or password']);
    }

    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    send_json_response(true, [
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'username' => $user['email'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'cellphone' => $user['cellphone'],
            'balance' => floatval($user['balance']),
            'total_games_played' => intval($user['total_games_played']),
            'total_winnings' => floatval($user['total_winnings']),
            'total_correct_answers' => intval($user['total_correct_answers']),
            'total_wrong_answers' => intval($user['total_wrong_answers']),
            'best_streak' => intval($user['best_streak']),
            'referral_code' => $user['referral_code']
        ]
    ]);
}

/**
 * Get user statistics
 */
function getUserStats($pdo, $input)
{
    if (empty($input['user_id'])) {
        send_json_response(false, ['error' => 'User ID is required']);
    }

    $user_id = intval($input['user_id']);

    $stmt = $pdo->prepare("
        SELECT 
            username, email, first_name, last_name, cellphone, 
            balance, total_games_played, total_winnings, 
            total_correct_answers, total_wrong_answers, 
            best_streak, referral_code
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        send_json_response(false, ['error' => 'User not found'], 404);
    }

    // Convert numeric fields
    $user['balance'] = floatval($user['balance']);
    $user['total_games_played'] = intval($user['total_games_played']);
    $user['total_winnings'] = floatval($user['total_winnings']);
    $user['total_correct_answers'] = intval($user['total_correct_answers']);
    $user['total_wrong_answers'] = intval($user['total_wrong_answers']);
    $user['best_streak'] = intval($user['best_streak']);

    send_json_response(true, ['user' => $user]);
}

/**
 * Deduct game stake from user balance at start of game
 */
function deductStake($pdo, $input)
{
    if (empty($input['user_id']) || !isset($input['amount'])) {
        send_json_response(false, ['error' => 'User ID and amount are required']);
    }

    $user_id = intval($input['user_id']);
    $amount = floatval($input['amount']);

    if ($amount <= 0) {
        send_json_response(false, ['error' => 'Invalid stake amount']);
    }

    $pdo->beginTransaction();
    try {
        // Verify balance
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception("User not found");
        }

        if ($user['balance'] < $amount) {
            throw new Exception("Insufficient balance");
        }

        // Deduct balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);

        // Record transaction
        $new_balance = $user['balance'] - $amount;
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, type, amount, balance_after, description, created_at
            ) VALUES (?, 'stake', ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, -$amount, $new_balance, "Stake for game session"]);

        $pdo->commit();
        send_json_response(true, [
            'message' => 'Stake deducted successfully',
            'new_balance' => $new_balance
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        send_json_response(false, ['error' => $e->getMessage()]);
    }
}