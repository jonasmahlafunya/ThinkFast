<?php
require_once 'db_config.php';

// Get timeframe from query parameter (default to allTime)
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'allTime';

try {
    $where_clause = "";
    $params = [];

    if ($timeframe === 'daily') {
        $where_clause = "WHERE created_at >= CURDATE()";
    } elseif ($timeframe === 'weekly') {
        $where_clause = "WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    }

    // Calculate payouts: Stake * 3 for every win (10 correct answers)
    // Joining with users table to get the actual first name instead of the email/username
    $query = "
        SELECT 
            u.first_name,
            gs.username, 
            SUM(CASE WHEN gs.correct_answers = 10 THEN gs.stake_amount * 3 ELSE 0 END) as total_payout,
            COUNT(gs.id) as total_games_played,
            COUNT(CASE WHEN gs.correct_answers = 10 THEN 1 END) as games_won,
            COUNT(CASE WHEN gs.correct_answers < 10 THEN 1 END) as games_lost
        FROM game_sessions gs
        LEFT JOIN users u ON (gs.username = u.email OR gs.username = u.username)
        $where_clause
        GROUP BY gs.username, u.first_name
        HAVING total_payout > 0
        ORDER BY total_payout DESC 
        LIMIT 10
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If All Time and no results from game_sessions (e.g. legacy data), fallback to total_winnings in users table
    if (empty($leaderboard) && $timeframe === 'allTime') {
        $stmt = $pdo->query("
            SELECT username, total_winnings as total_payout, total_games_played 
            FROM users 
            WHERE total_games_played > 0 
            ORDER BY total_winnings DESC 
            LIMIT 10
        ");
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    send_json_response(true, ['leaderboard' => $leaderboard, 'timeframe' => $timeframe]);
} catch (Exception $e) {
    send_json_response(false, ['error' => 'Failed to fetch leaderboard: ' . $e->getMessage()]);
}
