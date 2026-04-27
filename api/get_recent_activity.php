<?php
/**
 * Recent Activity API
 * Fetches the latest winning sessions and achievements for the live activity feed
 */

require_once 'db_config.php';

try {
    // Fetch latest 10 winning sessions
    // Using a 10/10 score as a "Win"
    $stmt = $pdo->query("
        SELECT username, stake_amount * 3 as win_amount, created_at
        FROM game_sessions 
        WHERE correct_answers = 10
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recent_wins = $stmt->fetchAll();

    $activity = [];

    // Format real wins
    foreach ($recent_wins as $win) {
        $activity[] = [
            'type' => 'win',
            'user' => $win['username'],
            'amount' => floatval($win['win_amount']),
            'time' => $win['created_at']
        ];
    }

    // Add some simulated high-streak activity if real wins are low
    if (count($activity) < 3) {
        $mock_names = ['Zama', 'Liam', 'Sarah', 'Kabelo', 'Jessica'];
        for ($i = 0; $i < 5; $i++) {
            $activity[] = [
                'type' => 'streak',
                'user' => $mock_names[array_rand($mock_names)],
                'count' => rand(5, 12),
                'content' => 'just reached a ' . rand(5, 10) . ' question streak!'
            ];
        }
    }

    send_json_response(true, [
        'activity' => $activity
    ]);

} catch (Exception $e) {
    send_json_response(false, ['error' => 'Failed to fetch activity: ' . $e->getMessage()]);
}
