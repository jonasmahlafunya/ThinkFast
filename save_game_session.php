<?php
/**
 * Game Session Management API
 * Handles saving and retrieving game sessions
 */

require_once 'db_config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, ['error' => 'Invalid request method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    send_json_response(false, ['error' => 'Invalid request data'], 400);
}

// Validate required fields
$required_fields = [
    'session_id',
    'username',
    'stake_amount',
    'total_questions',
    'correct_answers',
    'wrong_answers',
    'final_balance'
];

foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        send_json_response(false, ['error' => "Missing required field: $field"]);
    }
}

// Sanitize and validate inputs
$session_id = sanitize_input($input['session_id']);
$username = sanitize_input($input['username']);
$stake_amount = floatval($input['stake_amount']);
$total_questions = intval($input['total_questions']);
$correct_answers = intval($input['correct_answers']);
$wrong_answers = intval($input['wrong_answers']);
$final_balance = floatval($input['final_balance']);
$rating = isset($input['rating']) ? intval($input['rating']) : 0;
$comment = isset($input['comment']) ? sanitize_input($input['comment']) : '';

// Validate session data
if (empty($session_id) || empty($username)) {
    send_json_response(false, ['error' => 'Session ID and username are required']);
}

if ($total_questions < 0 || $correct_answers < 0 || $wrong_answers < 0) {
    send_json_response(false, ['error' => 'Invalid game statistics']);
}

if ($rating < 0 || $rating > 5) {
    $rating = 0; // Reset invalid rating
}

// Start transaction
$pdo->beginTransaction();

try {
    // Check if session already exists (prevent duplicates)
    $stmt = $pdo->prepare("SELECT id FROM game_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);

    if ($stmt->fetch()) {
        // Update existing session
        $stmt = $pdo->prepare("
            UPDATE game_sessions 
            SET total_questions = ?, 
                correct_answers = ?, 
                wrong_answers = ?, 
                final_balance = ?, 
                rating = ?, 
                comment = ?
            WHERE session_id = ?
        ");
        $stmt->execute([
            $total_questions,
            $correct_answers,
            $wrong_answers,
            $final_balance,
            $rating,
            $comment,
            $session_id
        ]);
    } else {
        // Insert new session
        $stmt = $pdo->prepare("
            INSERT INTO game_sessions (
                session_id, user_id, username, stake_amount, 
                total_questions, correct_answers, wrong_answers, 
                final_balance, rating, comment, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $session_id,
            $username,
            $username,
            $stake_amount,
            $total_questions,
            $correct_answers,
            $wrong_answers,
            $final_balance,
            $rating,
            $comment
        ]);
    }

    // Update user statistics
    // net_gain is for career stats (Total Winnings)
    // payout_amount is the actual funds to return to the user's balance
    $net_gain = isset($input['net_gain']) ? floatval($input['net_gain']) : 0;
    $payout_amount = isset($input['payout_amount']) ? floatval($input['payout_amount']) : 0;

    $stmt = $pdo->prepare("
        UPDATE users 
        SET total_games_played = total_games_played + 1,
            total_correct_answers = total_correct_answers + ?,
            total_wrong_answers = total_wrong_answers + ?,
            total_winnings = total_winnings + ?,
            balance = balance + ?
        WHERE email = ? OR username = ?
    ");
    $stmt->execute([
        $correct_answers,
        $wrong_answers,
        max(0, $net_gain),
        $payout_amount,
        $username,
        $username
    ]);

    $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_id, type, amount, balance_after, description, created_at
            ) VALUES (?, 'game', ?, ?, ?, NOW())
        ");
        $desc = $net_gain > 0 ? "Game Win: R" . number_format($net_gain, 2) : ($net_gain < 0 ? "Game Loss: R" . number_format(abs($net_gain), 2) : "Game Draw: R0.00");
        $stmt->execute([$user['id'], $net_gain, $user['balance'], $desc]);
    }

    // Update best streak if applicable
    if ($correct_answers > 0) {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET best_streak = GREATEST(best_streak, ?)
            WHERE email = ? OR username = ?
        ");
        $stmt->execute([$correct_answers, $username, $username]);
    }

    $pdo->commit();

    send_json_response(true, [
        'message' => 'Game session saved successfully',
        'session_id' => $session_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    log_error("Save game session failed: " . $e->getMessage(), [
        'session_id' => $session_id,
        'username' => $username
    ]);
    send_json_response(false, ['error' => 'Failed to save game session'], 500);
}