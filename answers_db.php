<?php
/**
 * Answers Validation API
 * Handles answer verification and question retrieval with answers
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
        case 'validate_answer':
            validateAnswer($pdo, $input);
            break;
            
        case 'get_question_with_answer':
            getQuestionWithAnswer($pdo, $input);
            break;
            
        default:
            send_json_response(false, ['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    log_error("Answers API error: " . $e->getMessage(), ['action' => $action]);
    send_json_response(false, ['error' => 'An error occurred. Please try again.'], 500);
}

/**
 * Validate user's answer
 */
function validateAnswer($pdo, $input) {
    // Validate required parameters
    if (!isset($input['question_id']) || !isset($input['user_answer'])) {
        send_json_response(false, ['error' => 'Question ID and answer are required']);
    }
    
    $question_id = intval($input['question_id']);
    $user_answer = $input['user_answer']; // Don't sanitize yet - need exact match
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, correct_answer, explanation 
            FROM questions 
            WHERE id = ?
        ");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch();
        
        if (!$question) {
            send_json_response(false, ['error' => 'Question not found'], 404);
        }
        
        // Normalize answers for comparison
        $normalized_user = normalizeAnswer($user_answer);
        $normalized_correct = normalizeAnswer($question['correct_answer']);
        
        $is_correct = ($normalized_user === $normalized_correct);
        
        send_json_response(true, [
            'correct' => $is_correct,
            'correct_answer' => $question['correct_answer'],
            'explanation' => $question['explanation'] ?? ''
        ]);
        
    } catch (PDOException $e) {
        log_error("Database error in validateAnswer: " . $e->getMessage());
        send_json_response(false, ['error' => 'Validation failed'], 500);
    }
}

/**
 * Get question with its answer
 */
function getQuestionWithAnswer($pdo, $input) {
    if (!isset($input['question_id'])) {
        send_json_response(false, ['error' => 'Question ID is required']);
    }
    
    $question_id = intval($input['question_id']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, category, difficulty, question_text, emojis, 
                   options, correct_answer, explanation
            FROM questions 
            WHERE id = ?
        ");
        $stmt->execute([$question_id]);
        $q = $stmt->fetch();
        
        if (!$q) {
            send_json_response(false, ['error' => 'Question not found'], 404);
        }
        
        // Format question
        $question = [
            'id' => intval($q['id']),
            'category' => $q['category'],
            'difficulty' => $q['difficulty'],
            'question' => $q['question_text'],
            'correct_answer' => $q['correct_answer'],
            'explanation' => $q['explanation'] ?? ''
        ];
        
        // Add emojis if present
        if (!empty($q['emojis'])) {
            $question['emojis'] = $q['emojis'];
        }
        
        // Parse options if present
        if (!empty($q['options']) && $q['options'] !== 'null') {
            $decoded = json_decode($q['options'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $question['options'] = $decoded;
            }
        }
        
        send_json_response(true, ['question' => $question]);
        
    } catch (PDOException $e) {
        log_error("Database error in getQuestionWithAnswer: " . $e->getMessage());
        send_json_response(false, ['error' => 'Failed to fetch question'], 500);
    }
}

/**
 * Normalize answer for comparison
 * Handles case-insensitive comparison and common variations
 */
function normalizeAnswer($answer) {
    // Convert to lowercase and trim
    $normalized = strtolower(trim($answer));
    
    // Remove extra whitespace
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    
    // Remove common punctuation
    $normalized = str_replace(['.', ',', '!', '?', ';', ':'], '', $normalized);
    
    // Handle common variations
    $variations = [
        'colour' => 'color',
        'grey' => 'gray',
        'centre' => 'center',
        'metre' => 'meter',
        'litre' => 'liter'
    ];
    
    foreach ($variations as $from => $to) {
        $normalized = str_replace($from, $to, $normalized);
    }
    
    return $normalized;
}