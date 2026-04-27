<?php
/**
 * Question Data API
 * Handles saving individual question responses during gameplay
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
    'question_number',
    'category',
    'difficulty',
    'question_text',
    'user_answer',
    'correct_answer',
    'username'
];

foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        send_json_response(false, ['error' => "Missing required field: $field"]);
    }
}

// Sanitize and validate inputs
$session_id = sanitize_input($input['session_id']);
$question_number = intval($input['question_number']);
$category = sanitize_input($input['category']);
$difficulty = sanitize_input($input['difficulty']);
$question_text = sanitize_input($input['question_text']);
$user_answer = sanitize_input($input['user_answer']);
$correct_answer = sanitize_input($input['correct_answer']);
$is_correct = isset($input['is_correct']) && $input['is_correct'] ? 1 : 0;
$time_taken = isset($input['time_taken']) ? intval($input['time_taken']) : 0;
$username = sanitize_input($input['username']);

// Validate inputs
if (empty($session_id)) {
    send_json_response(false, ['error' => 'Session ID is required']);
}

if ($question_number < 1) {
    send_json_response(false, ['error' => 'Invalid question number']);
}

$valid_difficulties = ['easy', 'medium', 'hard'];
if (!in_array($difficulty, $valid_difficulties)) {
    $difficulty = 'easy';
}

try {
    // Ensure session exists (satisfy Foreign Key)
    $stmt = $pdo->prepare("SELECT id FROM game_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO game_sessions (session_id, user_id, username, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$session_id, $username, $username]);
    }

    // Check if this question has already been recorded for this session
    $stmt = $pdo->prepare("
        SELECT id FROM game_questions 
        WHERE session_id = ? AND question_number = ?
    ");
    $stmt->execute([$session_id, $question_number]);

    if ($stmt->fetch()) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE game_questions 
            SET user_answer = ?,
                correct_answer = ?,
                is_correct = ?,
                time_taken = ?
            WHERE session_id = ? AND question_number = ?
        ");
        $stmt->execute([
            $user_answer,
            $correct_answer,
            $is_correct,
            $time_taken,
            $session_id,
            $question_number
        ]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO game_questions (
                session_id, question_number, category, difficulty, 
                question_text, user_answer, correct_answer, is_correct, 
                time_taken, user_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $session_id,
            $question_number,
            $category,
            $difficulty,
            $question_text,
            $user_answer,
            $correct_answer,
            $is_correct,
            $time_taken,
            $username
        ]);
    }

    send_json_response(true, [
        'message' => 'Question data saved successfully',
        'question_number' => $question_number
    ]);

} catch (PDOException $e) {
    log_error("Save question data failed: " . $e->getMessage(), [
        'session_id' => $session_id,
        'question_number' => $question_number
    ]);
    send_json_response(false, ['error' => 'Failed to save question data'], 500);
}