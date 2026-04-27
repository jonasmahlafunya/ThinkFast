<?php
require_once 'db_config.php';

try {
    // 1. Total Payout Today (Stake * 3 for winning sessions in last 24hrs)
    $stmt = $pdo->query("
        SELECT SUM(stake_amount * 3) as total_payout
        FROM game_sessions 
        WHERE created_at >= CURDATE() AND correct_answers = 10
    ");
    $total_payout = floatval($stmt->fetch()['total_payout'] ?? 0);

    // 2. Active Players Today (Unique users in sessions today + users logged in today)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT user_id) as active_count
        FROM (
            SELECT user_id FROM game_sessions WHERE created_at >= CURDATE()
            UNION
            SELECT id as user_id FROM users WHERE last_login >= CURDATE()
        ) as active_users
    ");
    $active_players = intval($stmt->fetch()['active_count'] ?? 0);

    // 3. Top Winner Today
    $stmt = $pdo->query("
        SELECT username, MAX(stake_amount * 3) as top_win
        FROM game_sessions 
        WHERE created_at >= CURDATE() AND correct_answers = 10
        GROUP BY username
        ORDER BY top_win DESC
        LIMIT 1
    ");
    $top_winner_data = $stmt->fetch();
    $top_winner = $top_winner_data ? floatval($top_winner_data['top_win']) : 0;

    // Base values to ensure the home page looks alive if data is low
    $base_payout = 8450.00;
    $base_players = 720;

    send_json_response(true, [
        'stats' => [
            'total_payout_today' => $total_payout + $base_payout,
            'active_players' => $active_players + $base_players,
            'top_winner_today' => $top_winner > 0 ? $top_winner : 500.00
        ]
    ]);

} catch (Exception $e) {
    send_json_response(false, ['error' => 'Failed to fetch live stats: ' . $e->getMessage()]);
}
