<?php
// ============================================================
//  MediCore/patients.php
//  GET  /patients.php         → list all patients (admin/doctor)
//  POST /patients.php         → create patient   (admin)
//  GET  /patients.php?id=xxx  → single patient
// ============================================================
require_once __DIR__ . '/Config.php';

$user = auth_required();
$db   = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $id     = $_GET['id'] ?? null;
    $search = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? '';

    // If patient, force $id to their own ID and clear search
    if ($user['role'] === 'patient') {
        $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => 'Patient record not found'], 404);
        $id = $row['id'];
        $search = ''; $status = '';
    }

    if ($id) {
        $stmt = $db->prepare("
            SELECT p.*, u.name, u.email, u.phone,
                   du.name AS doctor_name, dep.name AS department
            FROM patients p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN appointments a ON a.patient_id = p.id
            LEFT JOIN doctors doc ON doc.id = a.doctor_id
            LEFT JOIN users du ON du.id = doc.user_id
            LEFT JOIN departments dep ON dep.id = doc.department_id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $patient = $stmt->fetch();
        if (!$patient) json_response(['error' => 'Patient not found'], 404);
        json_response(['data' => $patient]);
    }

    $sql  = "SELECT p.id, p.patient_code, p.blood_type, p.status, p.date_of_birth,
                    u.name, u.email, u.phone
             FROM patients p
             JOIN users u ON u.id = p.user_id
             WHERE 1=1";
    $params = [];

    if ($search) {
        $sql .= " AND (u.name LIKE ? OR p.patient_code LIKE ? OR u.email LIKE ?)";
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like]);
    }
    if ($status) {
        $sql .= " AND p.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY u.name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    json_response(['data' => $rows, 'total' => count($rows)]);
}

// ── POST — create patient ────────────────────────────────────
if ($method === 'POST') {
    role_required('admin');

    $body = json_decode(file_get_contents('php://input'), true);

    $required = ['name','email','password','date_of_birth','blood_type'];
    foreach ($required as $f) {
        if (empty($body[$f])) json_response(['error' => "Field '$f' is required"], 422);
    }

    $db->beginTransaction();
    try {
        // Create user
        $hash   = password_hash($body['password'], PASSWORD_BCRYPT, ['cost'=>12]);
        $stmt   = $db->prepare("
            INSERT INTO users (id, name, email, password_hash, role, phone)
            VALUES (UUID(), ?, ?, ?, 'patient', ?)
        ");
        $stmt->execute([$body['name'], $body['email'], $hash, $body['phone'] ?? null]);

        // Auto-generate patient code
        $count = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
        $code  = 'P-' . str_pad($count + 1043, 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("
            INSERT INTO patients (id, user_id, patient_code, date_of_birth, blood_type, allergies, status)
            VALUES (UUID(),
                    (SELECT id FROM users WHERE email = ? LIMIT 1),
                    ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $body['email'], $code,
            $body['date_of_birth'], $body['blood_type'],
            $body['allergies'] ?? null,
        ]);

        $db->commit();
        json_response(['success' => true, 'patient_code' => $code], 201);

    } catch (Exception $e) {
        $db->rollBack();
        json_response(['error' => $e->getMessage()], 500);
    }
}

// ── PUT — update patient ─────────────────────────────────────
if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = $_GET['id'] ?? $body['id'] ?? null;
    if (!$id) json_response(['error' => 'Patient id required'], 422);

    // Access check
    if ($user['role'] === 'patient') {
        $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row || $row['id'] !== $id) json_response(['error' => 'Access denied'], 403);
        
        // Patients cannot update their own status
        unset($body['status']);
    } elseif ($user['role'] !== 'admin') {
        json_response(['error' => 'Access denied'], 403);
    }

    $db->beginTransaction();
    try {
        // Get user_id for this patient
        $stmt = $db->prepare("SELECT user_id FROM patients WHERE id = ?");
        $stmt->execute([$id]);
        $pat = $stmt->fetch();
        if (!$pat) json_response(['error' => 'Patient not found'], 404);

        // Update user fields if provided
        $userFields = [];
        $userParams = [];
        if (!empty($body['name']))  { $userFields[] = "name = ?";  $userParams[] = $body['name']; }
        if (!empty($body['email'])) { $userFields[] = "email = ?"; $userParams[] = $body['email']; }
        if (isset($body['phone']))  { $userFields[] = "phone = ?"; $userParams[] = $body['phone']; }
        if ($userFields) {
            $userParams[] = $pat['user_id'];
            $db->prepare("UPDATE users SET " . implode(', ', $userFields) . " WHERE id = ?")->execute($userParams);
        }

        // Update patient fields if provided
        $patFields = [];
        $patParams = [];
        if (isset($body['date_of_birth'])) { $patFields[] = "date_of_birth = ?"; $patParams[] = $body['date_of_birth']; }
        if (isset($body['blood_type']))    { $patFields[] = "blood_type = ?";    $patParams[] = $body['blood_type']; }
        if (isset($body['allergies']))     { $patFields[] = "allergies = ?";     $patParams[] = $body['allergies']; }
        if (isset($body['status']))        { $patFields[] = "status = ?";        $patParams[] = $body['status']; }
        if ($patFields) {
            $patParams[] = $id;
            $db->prepare("UPDATE patients SET " . implode(', ', $patFields) . " WHERE id = ?")->execute($patParams);
        }

        $db->commit();
        json_response(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        json_response(['error' => $e->getMessage()], 500);
    }
}

// ── DELETE — deactivate patient ──────────────────────────────
if ($method === 'DELETE') {
    role_required('admin');
    $id = $_GET['id'] ?? null;
    if (!$id) json_response(['error' => 'Patient id required'], 422);

    $stmt = $db->prepare("SELECT user_id FROM patients WHERE id = ?");
    $stmt->execute([$id]);
    $pat = $stmt->fetch();
    if (!$pat) json_response(['error' => 'Patient not found'], 404);

    // Soft-delete: deactivate user
    $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$pat['user_id']]);
    $db->prepare("UPDATE patients SET status = 'discharged' WHERE id = ?")->execute([$id]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);