<?php
/**
 * Questions Database API
 * Handles fetching questions by category and difficulty
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
        case 'get_questions':
            getQuestions($pdo, $input);
            break;
            
        case 'get_categories':
            getCategories($pdo);
            break;
            
        case 'get_random_question':
            getRandomQuestion($pdo, $input);
            break;
            
        default:
            send_json_response(false, ['error' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    log_error("Questions API error: " . $e->getMessage(), ['action' => $action]);
    send_json_response(false, ['error' => 'An error occurred. Please try again.'], 500);
}

/**
 * Get questions by category and difficulty
 */
function getQuestions($pdo, $input) {
    // Validate required parameters
    if (empty($input['category']) || empty($input['difficulty'])) {
        send_json_response(false, ['error' => 'Category and difficulty are required']);
    }
    
    $category = sanitize_input($input['category']);
    $difficulty = sanitize_input($input['difficulty']);
    $limit = isset($input['limit']) ? intval($input['limit']) : 20;
    
    // Validate difficulty
    $valid_difficulties = ['easy', 'medium', 'hard'];
    if (!in_array($difficulty, $valid_difficulties)) {
        send_json_response(false, ['error' => 'Invalid difficulty level']);
    }
    
    // Ensure limit is reasonable
    $limit = max(1, min($limit, 100));
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, category, difficulty, question_text, emojis, options, explanation
            FROM questions 
            WHERE category = ? AND difficulty = ? 
            ORDER BY RAND() 
            LIMIT ?
        ");
        $stmt->execute([$category, $difficulty, $limit]);
        $questions = $stmt->fetchAll();
        
        // If no questions found, try any difficulty in the same category
        if (empty($questions)) {
            $stmt = $pdo->prepare("
                SELECT id, category, difficulty, question_text, emojis, options, explanation
                FROM questions 
                WHERE category = ? 
                ORDER BY RAND() 
                LIMIT ?
            ");
            $stmt->execute([$category, $limit]);
            $questions = $stmt->fetchAll();
        }
        
        // Process questions
        $formatted_questions = [];
        foreach ($questions as $q) {
            $question = [
                'id' => intval($q['id']),
                'category' => $q['category'],
                'difficulty' => $q['difficulty'],
                'question' => $q['question_text'],
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
            
            $formatted_questions[] = $question;
        }
        
        send_json_response(true, [
            'questions' => $formatted_questions,
            'count' => count($formatted_questions)
        ]);
        
    } catch (PDOException $e) {
        log_error("Database error in getQuestions: " . $e->getMessage());
        send_json_response(false, ['error' => 'Failed to fetch questions'], 500);
    }
}

/**
 * Get all available categories
 */
function getCategories($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT category, COUNT(*) as count 
            FROM questions 
            GROUP BY category 
            ORDER BY category
        ");
        $categories = $stmt->fetchAll();
        
        send_json_response(true, [
            'categories' => array_column($categories, 'category'),
            'details' => $categories
        ]);
        
    } catch (PDOException $e) {
        log_error("Database error in getCategories: " . $e->getMessage());
        send_json_response(false, ['error' => 'Failed to fetch categories'], 500);
    }
}

/**
 * Get a random question (excluding used ones)
 */
function getRandomQuestion($pdo, $input) {
    // Validate required parameters
    if (empty($input['category'])) {
        send_json_response(false, ['error' => 'Category is required']);
    }
    
    $category = sanitize_input($input['category']);
    $difficulty = isset($input['difficulty']) ? sanitize_input($input['difficulty']) : 'easy';
    $used_questions = isset($input['used_questions']) && is_array($input['used_questions']) 
        ? array_map('intval', $input['used_questions']) 
        : [];
    
    // Validate difficulty
    $valid_difficulties = ['easy', 'medium', 'hard'];
    if (!in_array($difficulty, $valid_difficulties)) {
        $difficulty = 'easy';
    }
    
    try {
        // Build query
        $query = "
            SELECT id, category, difficulty, question_text, emojis, options, explanation
            FROM questions 
            WHERE category = ? AND difficulty = ?
        ";
        $params = [$category, $difficulty];
        
        // Exclude used questions
        if (!empty($used_questions)) {
            $placeholders = str_repeat('?,', count($used_questions) - 1) . '?';
            $query .= " AND id NOT IN ($placeholders)";
            $params = array_merge($params, $used_questions);
        }
        
        $query .= " ORDER BY RAND() LIMIT 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $q = $stmt->fetch();
        
        if (!$q) {
            send_json_response(false, ['error' => 'No more questions available']);
        }
        
        // Format question
        $question = [
            'id' => intval($q['id']),
            'category' => $q['category'],
            'difficulty' => $q['difficulty'],
            'question' => $q['question_text'],
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
        log_error("Database error in getRandomQuestion: " . $e->getMessage());
        send_json_response(false, ['error' => 'Failed to fetch question'], 500);
    }
}