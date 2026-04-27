<?php
include 'db_config.php';

try {
    // Get all categories
    $stmt = $pdo->query("SELECT DISTINCT category FROM questions");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $questions = [];

    foreach ($categories as $category) {
        $questions[$category] = [
            'easy' => [],
            'medium' => [],
            'hard' => []
        ];

        // Get questions for each difficulty level
        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            $stmt = $pdo->prepare("SELECT * FROM questions WHERE category = ? AND difficulty = ?");
            $stmt->execute([$category, $difficulty]);
            $categoryQuestions = $stmt->fetchAll();

            foreach ($categoryQuestions as $q) {
                $questionData = [
                    'id' => $q['id'],
                    'question' => $q['question_text'],
                    'answer' => $q['correct_answer'],
                    'explanation' => $q['explanation'] ?? '',
                    'hint' => $q['hint'] ?? ''
                ];

                // Add optional fields if they exist
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

                $questions[$category][$difficulty][] = $questionData;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load questions'
    ]);
}
?>