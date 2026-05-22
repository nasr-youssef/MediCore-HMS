<?php
// ============================================================
//  MediCore/medical_records.php
//  GET  /medical_records.php             → list records (role-filtered)
//  GET  /medical_records.php?patient=xxx → records for specific patient
//  POST /medical_records.php             → create record (doctor only)
// ============================================================
require_once __DIR__ . '/Config.php';

$user   = auth_required();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $patient_id = $_GET['patient'] ?? null;

    $sql = "
        SELECT mr.id, mr.record_type, mr.diagnosis, mr.notes,
               mr.icd10_code, mr.blood_pressure, mr.heart_rate,
               mr.temperature, mr.spo2, mr.weight, mr.height,
               mr.record_date, mr.created_at,
               pu.name AS patient_name, p.patient_code,
               du.name AS doctor_name
        FROM medical_records mr
        JOIN patients p ON p.id = mr.patient_id
        JOIN users pu ON pu.id = p.user_id
        JOIN doctors doc ON doc.id = mr.doctor_id
        JOIN users du ON du.id = doc.user_id
        WHERE 1=1
    ";
    $params = [];

    // Role-based filtering
    if ($user['role'] === 'patient') {
        $sql .= " AND p.user_id = ?";
        $params[] = $user['id'];
    } elseif ($user['role'] === 'doctor') {
        $sql .= " AND doc.user_id = ?";
        $params[] = $user['id'];
    }

    if ($patient_id) {
        $sql .= " AND mr.patient_id = ?";
        $params[] = $patient_id;
    }

    $sql .= " ORDER BY mr.record_date DESC, mr.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_response(['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    role_required('doctor');

    $body = json_decode(file_get_contents('php://input'), true);

    $required = ['patient_id', 'record_type'];
    foreach ($required as $f) {
        if (empty($body[$f])) json_response(['error' => "Field '$f' is required"], 422);
    }

    // Get doctor_id from user
    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $doctor = $stmt->fetch();
    if (!$doctor) json_response(['error' => 'Doctor profile not found'], 404);

    $stmt = $db->prepare("
        INSERT INTO medical_records
            (id, patient_id, doctor_id, appointment_id, record_type, diagnosis, notes,
             icd10_code, blood_pressure, heart_rate, temperature, spo2, weight, height, record_date)
        VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, CURRENT_DATE))
    ");
    $stmt->execute([
        $body['patient_id'],
        $doctor['id'],
        $body['appointment_id'] ?? null,
        $body['record_type'],
        $body['diagnosis'] ?? null,
        $body['notes'] ?? null,
        $body['icd10_code'] ?? null,
        $body['blood_pressure'] ?? null,
        $body['heart_rate'] ?? null,
        $body['temperature'] ?? null,
        $body['spo2'] ?? null,
        $body['weight'] ?? null,
        $body['height'] ?? null,
        $body['record_date'] ?? null,
    ]);

    json_response(['success' => true], 201);
}

// ── PUT — update record ──────────────────────────────────────
if ($method === 'PUT') {
    role_required('doctor', 'admin');
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = $_GET['id'] ?? $body['id'] ?? null;
    if (!$id) json_response(['error' => 'Record id required'], 422);

    $fields = [];
    $params = [];
    $allowed = ['diagnosis','notes','icd10_code','blood_pressure','heart_rate','temperature','spo2','weight','height','record_type'];
    foreach ($allowed as $f) {
        if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (!$fields) json_response(['error' => 'No fields to update'], 422);

    $params[] = $id;
    $db->prepare("UPDATE medical_records SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
