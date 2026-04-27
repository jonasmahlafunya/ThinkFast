<?php
/**
 * Database Connection Test Script
 * Tests database connectivity and displays system information
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'nexasyst_thinkfast';
$username = getenv('DB_USER') ?: 'nexasyst_thabo';
$password = getenv('DB_PASS') ?: 'Mthombeni.11@';

$response = [
    'success' => false,
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];

try {
    // Test connection
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $response['success'] = true;
    $response['message'] = 'Database connection successful';
    $response['database'] = $dbname;
    $response['host'] = $host;
    
    // Test tables
    $tables = [];
    $table_names = ['users', 'questions', 'game_sessions', 'game_questions', 'transactions', 'card_transactions'];
    
    foreach ($table_names as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $tables[$table] = [
                'exists' => true,
                'count' => intval($result['count'])
            ];
        } catch (PDOException $e) {
            $tables[$table] = [
                'exists' => false,
                'error' => 'Table not found'
            ];
        }
    }
    
    $response['tables'] = $tables;
    
    // Database statistics
    $stats = [];
    
    // Total users
    if ($tables['users']['exists']) {
        $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(balance) as total_balance FROM users");
        $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['users'] = [
            'total' => intval($user_stats['total']),
            'total_balance' => floatval($user_stats['total_balance'] ?? 0)
        ];
    }
    
    // Total questions by category
    if ($tables['questions']['exists']) {
        $stmt = $pdo->query("
            SELECT category, difficulty, COUNT(*) as count 
            FROM questions 
            GROUP BY category, difficulty 
            ORDER BY category, difficulty
        ");
        $stats['questions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Recent games
    if ($tables['game_sessions']['exists']) {
        $stmt = $pdo->query("
            SELECT COUNT(*) as total_games,
                   SUM(correct_answers) as total_correct,
                   SUM(wrong_answers) as total_wrong
            FROM game_sessions
        ");
        $stats['games'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $response['statistics'] = $stats;
    
    // Check for common issues
    $warnings = [];
    
    if ($tables['questions']['count'] < 10) {
        $warnings[] = 'Low number of questions. Run setup_questions.php to add more.';
    }
    
    if ($tables['users']['count'] === 0) {
        $warnings[] = 'No users registered yet.';
    }
    
    if (!empty($warnings)) {
        $response['warnings'] = $warnings;
    }
    
    // System health
    $response['health'] = [
        'database' => 'healthy',
        'connection_time' => '< 1s',
        'status' => 'operational'
    ];
    
} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database connection failed';
    $response['error'] = $e->getMessage();
    $response['error_code'] = $e->getCode();
    $response['health'] = [
        'database' => 'unhealthy',
        'status' => 'error'
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);