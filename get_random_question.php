<?php
include 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$category = $input['category'] ?? '';
$difficulty = $input['difficulty'] ?? 'easy';
$usedQuestions = $input['used_questions'] ?? [];

if (empty($category)) {
    echo json_encode(['success' => false, 'error' => 'Category is required']);
    exit;
}

try {
    // Build query to exclude used questions
    $query = "SELECT * FROM questions WHERE category = ? AND difficulty = ?";
    $params = [$category, $difficulty];

    if (!empty($usedQuestions)) {
        $placeholders = str_repeat('?,', count($usedQuestions) - 1) . '?';
        $query .= " AND id NOT IN ($placeholders)";
        $params = array_merge($params, $usedQuestions);
    }

    $query .= " ORDER BY RAND() LIMIT 1";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $question = $stmt->fetch();

    if ($question) {
        $questionData = [
            'id' => $question['id'],
            'question' => $question['question_text'],
            'answer' => $question['correct_answer'],
            'explanation' => $question['explanation'] ?? '',
            'hint' => $question['hint'] ?? ''
        ];

        if (!empty($question['options'])) {
            $decoded = json_decode($question['options'], true);
            $questionData['options'] = is_array($decoded) ? $decoded : null;
        }
        if (!empty($question['emojis'])) {
            $questionData['emojis'] = $question['emojis'];
        }
        if (!empty($question['emoji'])) {
            $questionData['emoji'] = $question['emoji'];
        }
        if (!empty($question['word'])) {
            $questionData['word'] = $question['word'];
        }

        echo json_encode([
            'success' => true,
            'question' => $questionData
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No questions available'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load question'
    ]);
}
?>