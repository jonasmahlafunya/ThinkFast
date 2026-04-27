<?php
/**
 * Database Setup Script
 * Initializes questions table and inserts sample questions
 * 
 * WARNING: Only run this once during initial setup!
 */

require_once 'db_config.php';

try {
    // Sample questions data
    $sample_questions = [
        // Spelling - Easy
        ['spelling', 'easy', 'What is the correct spelling?', null, '["aple", "apple", "appel", "apl"]', 'apple', 'The correct spelling is "apple".'],
        ['spelling', 'easy', 'What is the correct spelling?', null, '["bananna", "bannana", "banana", "banan"]', 'banana', 'The correct spelling is "banana".'],
        ['spelling', 'easy', 'What is the correct spelling?', null, '["elefant", "elephant", "elephent", "elaphant"]', 'elephant', 'The correct spelling is "elephant".'],
        ['spelling', 'easy', 'What is the correct spelling?', null, '["tomorow", "tomorrow", "tommorow", "tommorrow"]', 'tomorrow', 'The correct spelling is "tomorrow".'],
        
        // Spelling - Medium
        ['spelling', 'medium', 'What is the correct spelling?', null, '["necesary", "necessary", "necessery", "neccesary"]', 'necessary', 'The correct spelling is "necessary".'],
        ['spelling', 'medium', 'What is the correct spelling?', null, '["occassion", "occasion", "ocasion", "occation"]', 'occasion', 'The correct spelling is "occasion".'],
        ['spelling', 'medium', 'What is the correct spelling?', null, '["recieve", "receive", "receve", "receiv"]', 'receive', 'The correct spelling is "receive".'],
        
        // Spelling - Hard
        ['spelling', 'hard', 'What is the correct spelling?', null, '["conscientious", "consciencious", "conscientous", "consientious"]', 'conscientious', 'The correct spelling is "conscientious".'],
        ['spelling', 'hard', 'What is the correct spelling?', null, '["millennium", "millenium", "milenium", "millennuim"]', 'millennium', 'The correct spelling is "millennium".'],
        
        // Images/Emojis - Easy
        ['images', 'easy', 'What animal is this?', '🐶', '["Cat", "Dog", "Bird", "Fish"]', 'Dog', 'This is a dog emoji.'],
        ['images', 'easy', 'What fruit is this?', '🍎', '["Apple", "Banana", "Orange", "Grape"]', 'Apple', 'This is an apple emoji.'],
        ['images', 'easy', 'What is this?', '🚗', '["Car", "Plane", "Boat", "Bicycle"]', 'Car', 'This is a car emoji.'],
        ['images', 'easy', 'What building is this?', '🏠', '["House", "School", "Hospital", "Factory"]', 'House', 'This is a house emoji.'],
        
        // Images - Medium
        ['images', 'medium', 'What weather is this?', '🌧️', '["Sunny", "Rainy", "Snowy", "Cloudy"]', 'Rainy', 'This represents rainy weather.'],
        ['images', 'medium', 'What is this?', '🎸', '["Piano", "Guitar", "Drum", "Violin"]', 'Guitar', 'This is a guitar emoji.'],
        
        // Guess the Word - Easy
        ['guesstheword', 'easy', 'Guess the phrase from emojis', '🍎 + 🌳', null, 'apple tree', 'Apple + Tree = Apple Tree'],
        ['guesstheword', 'easy', 'Guess the phrase from emojis', '☀️ + 🌸', null, 'sunflower', 'Sun + Flower = Sunflower'],
        
        // Math - Easy
        ['math', 'easy', '2 + 2 = ?', null, null, '4', '2 plus 2 equals 4.'],
        ['math', 'easy', '5 - 3 = ?', null, null, '2', '5 minus 3 equals 2.'],
        ['math', 'easy', '3 × 4 = ?', null, null, '12', '3 times 4 equals 12.'],
        ['math', 'easy', '10 ÷ 2 = ?', null, null, '5', '10 divided by 2 equals 5.'],
        
        // Math - Medium
        ['math', 'medium', '12 + 34 = ?', null, null, '46', '12 plus 34 equals 46.'],
        ['math', 'medium', '7 × 8 = ?', null, null, '56', '7 times 8 equals 56.'],
        ['math', 'medium', '100 - 37 = ?', null, null, '63', '100 minus 37 equals 63.'],
        
        // Math - Hard
        ['math', 'hard', '15% of 200 = ?', null, null, '30', '15% of 200 is 30.'],
        ['math', 'hard', '√144 = ?', null, null, '12', 'The square root of 144 is 12.'],
        
        // Geography - Easy
        ['geography', 'easy', 'What is the capital of South Africa?', null, '["Cape Town", "Pretoria", "Johannesburg", "Durban"]', 'Pretoria', 'Pretoria is the administrative capital of South Africa.'],
        ['geography', 'easy', 'Which continent is South Africa on?', null, '["Africa", "Asia", "Europe", "America"]', 'Africa', 'South Africa is on the African continent.'],
        ['geography', 'easy', 'What is the capital of France?', null, '["London", "Paris", "Berlin", "Rome"]', 'Paris', 'Paris is the capital of France.'],
        
        // Geography - Medium
        ['geography', 'medium', 'What is the largest ocean?', null, '["Atlantic", "Pacific", "Indian", "Arctic"]', 'Pacific', 'The Pacific Ocean is the largest ocean.'],
        ['geography', 'medium', 'Which country has the most people?', null, '["India", "China", "USA", "Indonesia"]', 'China', 'China has the world\'s largest population.'],
        
        // General Knowledge - Easy
        ['general', 'easy', 'How many days in a week?', null, null, '7', 'There are 7 days in a week.'],
        ['general', 'easy', 'How many months in a year?', null, null, '12', 'There are 12 months in a year.'],
        ['general', 'easy', 'What color is the sky on a clear day?', null, '["Red", "Blue", "Green", "Yellow"]', 'Blue', 'The sky is blue on a clear day.'],
        
        // General Knowledge - Medium
        ['general', 'medium', 'Who wrote Romeo and Juliet?', null, '["Charles Dickens", "William Shakespeare", "Jane Austen", "Mark Twain"]', 'William Shakespeare', 'William Shakespeare wrote Romeo and Juliet.'],
        ['general', 'medium', 'What is H2O?', null, '["Oxygen", "Water", "Hydrogen", "Carbon"]', 'Water', 'H2O is the chemical formula for water.'],
    ];
    
    // Insert questions
    $stmt = $pdo->prepare("
        INSERT INTO questions (
            category, difficulty, question_text, emojis, 
            options, correct_answer, explanation, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $inserted = 0;
    $errors = 0;
    
    foreach ($sample_questions as $q) {
        try {
            $stmt->execute([
                $q[0], // category
                $q[1], // difficulty
                $q[2], // question_text
                $q[3], // emojis
                $q[4], // options (JSON or null)
                $q[5], // correct_answer
                $q[6]  // explanation
            ]);
            $inserted++;
        } catch (PDOException $e) {
            $errors++;
            log_error("Failed to insert question: " . $e->getMessage(), [
                'question' => $q[2]
            ]);
        }
    }
    
    // Get total count
    $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM questions");
    $count = $count_stmt->fetch();
    
    // Get category breakdown
    $category_stmt = $pdo->query("
        SELECT category, COUNT(*) as count 
        FROM questions 
        GROUP BY category
    ");
    $categories = $category_stmt->fetchAll();
    
    send_json_response(true, [
        'message' => 'Setup completed successfully',
        'questions_inserted' => $inserted,
        'errors' => $errors,
        'total_questions' => intval($count['total']),
        'categories' => $categories
    ]);
    
} catch (Exception $e) {
    log_error("Setup failed: " . $e->getMessage());
    send_json_response(false, [
        'error' => 'Setup failed: ' . $e->getMessage()
    ], 500);
}