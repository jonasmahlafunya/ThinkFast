<?php
/**
 * Database Configuration
 * ThinkFast IQ Game
 * 
 * SECURITY NOTE: Move credentials to environment variables in production
 */

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

// Set headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Database credentials
// TODO: Move to environment variables or config file outside webroot
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'nexasyst_thinkfast';
$username = getenv('DB_USER') ?: 'nexasyst_thabo';
$password = getenv('DB_PASS') ?: 'Mthombeni.11@';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database connection failed. Please try again later.'
    ]);
    exit;
}

/**
 * Sanitize input string
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Send JSON response
 */
function send_json_response($success, $data = [], $http_code = 200) {
    http_response_code($http_code);
    echo json_encode(array_merge(['success' => $success], $data));
    exit;
}

/**
 * Log error securely
 */
function log_error($message, $context = []) {
    $log_message = date('Y-m-d H:i:s') . " - " . $message;
    if (!empty($context)) {
        $log_message .= " | Context: " . json_encode($context);
    }
    error_log($log_message);
}