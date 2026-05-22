<?php
// ============================================================
//  MediCore/Config.php — Database connection & helpers
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'medicore_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default = empty
define('DB_CHARSET', 'utf8mb4');

// ── CORS — call at the very top of every API file ──────────
function cors_headers(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Credentials: true');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

cors_headers();

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

// JSON response helper
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function auth_required(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user'])) {
        json_response(['error' => 'Unauthorized'], 401);
    }
    return $_SESSION['user'];
}

function role_required(string ...$roles): array {
    $user = auth_required();
    if (!in_array($user['role'], $roles)) {
        json_response(['error' => 'Forbidden'], 403);
    }
    return $user;
}