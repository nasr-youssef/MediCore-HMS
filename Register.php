<?php
// ============================================================
//  MediCore/Register.php — Patient self-registration
//  Creates user + patient profile in one transaction
// ============================================================
require_once __DIR__ . '/Config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);

$name      = trim($body['name'] ?? '');
$email     = trim($body['email'] ?? '');
$pass      = $body['password'] ?? '';
$phone     = trim($body['phone'] ?? '');
$dob       = $body['date_of_birth'] ?? null;
$blood     = $body['blood_type'] ?? null;
$allergies = trim($body['allergies'] ?? '');

if (!$name || !$email || !$pass) {
    json_response(['error' => 'Name, email and password are required'], 422);
}

if (strlen($pass) < 6) {
    json_response(['error' => 'Password must be at least 6 characters'], 422);
}

$db = getDB();

// Check if email already exists
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_response(['error' => 'Email already exists'], 409);
}

$db->beginTransaction();
try {
    // Hash password
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

    // Generate user ID
    $userId = 'user-' . uniqid();

    // Insert user
    $stmt = $db->prepare("
        INSERT INTO users (id, name, email, password_hash, role, phone, is_active)
        VALUES (?, ?, ?, ?, 'patient', ?, 1)
    ");
    $stmt->execute([$userId, $name, $email, $hash, $phone ?: null]);

    // Generate patient code
    $count = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $code = 'P-' . str_pad($count + 1043, 4, '0', STR_PAD_LEFT);

    // Create patient profile
    $patientId = 'pat-' . uniqid();
    $stmt = $db->prepare("
        INSERT INTO patients (id, user_id, patient_code, date_of_birth, blood_type, allergies, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([
        $patientId, $userId, $code,
        $dob ?: null,
        $blood ?: null,
        $allergies ?: null
    ]);

    $db->commit();

    json_response([
        'success' => true,
        'message' => 'Account created successfully',
        'patient_code' => $code
    ], 201);

} catch (Exception $e) {
    $db->rollBack();
    json_response(['error' => 'Registration failed: ' . $e->getMessage()], 500);
}