<?php
include 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$category = $input['category'] ?? '';
$difficulty = $input['difficulty'] ?? 'easy';

if (empty($category)) {
    echo json_encode(['success' => false, 'error' => 'Category is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE category = ? AND difficulty = ?");
    $stmt->execute([$category, $difficulty]);
    $questions = $stmt->fetchAll();

    $formattedQuestions = [];
    foreach ($questions as $q) {
        $questionData = [
            'id' => $q['id'],
            'question' => $q['question_text'],
            'answer' => $q['correct_answer'],
            'explanation' => $q['explanation'] ?? '',
            'hint' => $q['hint'] ?? ''
        ];

        if (!empty($q['options'])) {
            $decoded = json_decode($q['options'], true);
            $questionData['options'] = is_array($decoded) ? $decoded : null;
        }
        if (!empty($q['emojis'])) {
            $questionData['emojis'] = $q['emojis'];
        }
        if (!empty($q['emoji'])) {
            $questionData['emoji'] = $q['emoji'];
        }
        if (!empty($q['word'])) {
            $questionData['word'] = $q['word'];
        }

        $formattedQuestions[] = $questionData;
    }

    echo json_encode([
        'success' => true,
        'questions' => $formattedQuestions
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load questions'
    ]);
}
?>