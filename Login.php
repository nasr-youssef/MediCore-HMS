<?php
// ============================================================
//  MediCore/Login.php — Authentication endpoint
// ============================================================
require_once __DIR__ . '/Config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body  = json_decode(file_get_contents('php://input'), true);
$email = trim($body['email'] ?? '');
$pass  = $body['password'] ?? '';

if (!$email || !$pass) {
    json_response(['error' => 'Email and password are required'], 422);
}

$db   = getDB();
$stmt = $db->prepare("SELECT id, name, email, password_hash, role, phone, is_active FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) {
    json_response(['error' => 'Invalid email or password'], 401);
}

if (!$user['is_active']) {
    json_response(['error' => 'Account is deactivated'], 403);
}

// Start session
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user'] = [
    'id'    => $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
    'phone' => $user['phone'],
];

// Get role-specific data
$extra = [];
if ($user['role'] === 'doctor') {
    $stmt = $db->prepare("
        SELECT d.id AS doctor_id, d.specialization, d.status, d.rating,
               dep.name AS department
        FROM doctors d
        JOIN departments dep ON dep.id = d.department_id
        WHERE d.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $extra = $stmt->fetch() ?: [];
} elseif ($user['role'] === 'patient') {
    $stmt = $db->prepare("
        SELECT p.id AS patient_id, p.patient_code, p.blood_type, p.status,
               p.date_of_birth, p.allergies
        FROM patients p WHERE p.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $extra = $stmt->fetch() ?: [];
}

json_response([
    'success' => true,
    'user' => array_merge([
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'phone' => $user['phone'],
    ], $extra),
]);