<?php
/**
 * General Interactions API
 * Handles Support Tickets, Feedback, and Password Resets
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
        case 'submit_support':
            handleSupport($pdo, $input);
            break;

        case 'submit_feedback':
            handleFeedback($pdo, $input);
            break;

        case 'send_password_reset':
            handlePasswordReset($pdo, $input);
            break;

        default:
            send_json_response(false, ['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    send_json_response(false, ['error' => 'An error occurred: ' . $e->getMessage()], 500);
}

/**
 * Handle Support Request
 */
function handleSupport($pdo, $input)
{
    $name = sanitize_input($input['name'] ?? '');
    $email = sanitize_input($input['email'] ?? '');
    $category = sanitize_input($input['category'] ?? '');
    $message = sanitize_input($input['message'] ?? '');

    if (!$name || !$email || !$message) {
        send_json_response(false, ['error' => 'Missing required fields']);
    }

    // In a real app, we would insert into a support_tickets table
    // For now, we simulate success and log it if possible
    send_json_response(true, ['message' => 'Your support ticket has been received. We will contact you soon.']);
}

/**
 * Handle Game Feedback
 */
function handleFeedback($pdo, $input)
{
    $user_id = $input['user_id'] ?? null;
    $rating = intval($input['rating'] ?? 0);
    $comment = sanitize_input($input['comment'] ?? '');

    if ($rating < 1) {
        send_json_response(false, ['error' => 'Rating is required']);
    }

    // In a real app, we'd update a field in the game_sessions table
    // The current schema has rating/comment fields in game_sessions, so we use that if session_id is provided
    if (isset($input['session_id'])) {
        $stmt = $pdo->prepare("UPDATE game_sessions SET rating = ?, comment = ? WHERE session_id = ?");
        $stmt->execute([$rating, $comment, $input['session_id']]);
    }

    send_json_response(true, ['message' => 'Thank you for your feedback!']);
}

/**
 * Handle Password Reset
 */
function handlePasswordReset($pdo, $input)
{
    $email = sanitize_input($input['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json_response(false, ['error' => 'Invalid email address']);
    }

    // Simulate sending email
    send_json_response(true, ['message' => 'A password reset link has been sent to your email.']);
}
