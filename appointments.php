<?php
// ============================================================
//  MediCore/appointments.php
// ============================================================
require_once __DIR__ . '/Config.php';

$user   = auth_required();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Helper to update doctor status based on active scheduled appointments
function syncDoctorStatus($db, $doctor_id) {
    // Check if doctor has any 'scheduled' appointments
    // Requirement: doctor becomes busy if there is at least one scheduled appointment
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'scheduled'");
    $stmt->execute([$doctor_id]);
    $count = $stmt->fetchColumn();

    $newStatus = ($count > 0) ? 'busy' : 'available';

    $stmt = $db->prepare("UPDATE doctors SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $doctor_id]);
}

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $id     = $_GET['id']     ?? null;
    $date   = $_GET['date']   ?? null;
    $status = $_GET['status'] ?? null;

    $sql = "
        SELECT a.id, a.appt_ref, a.patient_id, a.doctor_id, a.appt_date, a.appt_time, a.type, a.status, a.notes,
               pu.name AS patient_name, p.patient_code,
               du.name AS doctor_name, dep.name AS department
        FROM appointments a
        JOIN patients p    ON p.id  = a.patient_id
        JOIN users pu      ON pu.id = p.user_id
        JOIN doctors doc   ON doc.id = a.doctor_id
        JOIN users du      ON du.id  = doc.user_id
        JOIN departments dep ON dep.id = doc.department_id
        WHERE 1=1
    ";
    $params = [];

    if ($id) { $sql .= " AND a.id = ?"; $params[] = $id; }

    // Role-based filtering
    if ($user['role'] === 'doctor') {
        $sql .= " AND doc.user_id = ?"; $params[] = $user['id'];
    } elseif ($user['role'] === 'patient') {
        $sql .= " AND p.user_id = ?"; $params[] = $user['id'];
    }

    if ($date)   { $sql .= " AND a.appt_date = ?"; $params[] = $date; }
    if ($status) { $sql .= " AND a.status = ?";    $params[] = $status; }

    $sql .= " ORDER BY a.appt_date DESC, a.appt_time ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_response(['data' => $stmt->fetchAll()]);
}

// ── POST — create appointment ────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $required = ['patient_id','doctor_id','appt_date','appt_time','type'];
    foreach ($required as $f) {
        if (empty($body[$f])) json_response(['error' => "Field '$f' is required"], 422);
    }

    // Check doctor status
    $stmt = $db->prepare("SELECT status FROM doctors WHERE id = ?");
    $stmt->execute([$body['doctor_id']]);
    $docStatus = $stmt->fetchColumn();
    if ($docStatus === 'busy') {
        json_response(['error' => 'Doctor is currently busy and cannot accept new appointments'], 409);
    }

    // Check no double-booking
    $stmt = $db->prepare("
        SELECT id FROM appointments
        WHERE doctor_id = ? AND appt_date = ? AND appt_time = ?
        AND status NOT IN ('cancelled')
    ");
    $stmt->execute([$body['doctor_id'], $body['appt_date'], $body['appt_time']]);
    if ($stmt->fetch()) {
        json_response(['error' => 'Doctor already has an appointment at this time'], 409);
    }

    // Generate ref
    $count = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $ref   = 'A-' . str_pad($count + 101, 3, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("
        INSERT INTO appointments
          (id, appt_ref, patient_id, doctor_id, appt_date, appt_time, type, status, notes)
        VALUES (UUID(), ?, ?, ?, ?, ?, ?, 'scheduled', ?)
    ");
    $stmt->execute([
        $ref, $body['patient_id'], $body['doctor_id'],
        $body['appt_date'], $body['appt_time'],
        $body['type'], $body['notes'] ?? null,
    ]);

    // Sync doctor status
    syncDoctorStatus($db, $body['doctor_id']);

    json_response(['success' => true, 'appt_ref' => $ref], 201);
}

// ── PUT — update status ──────────────────────────────────────
if ($method === 'PUT') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = $_GET['id'] ?? $body['id'] ?? null;
    $status = $body['status'] ?? null;

    if (!$id || !$status) json_response(['error' => 'id and status required'], 422);

    $allowed = ['scheduled','completed','cancelled','pending'];
    if (!in_array($status, $allowed)) json_response(['error' => 'Invalid status'], 422);

    $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    // Get doctor_id to sync status
    $stmt = $db->prepare("SELECT doctor_id FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    $doctor_id = $stmt->fetchColumn();
    if ($doctor_id) {
        syncDoctorStatus($db, $doctor_id);
    }

    json_response(['success' => true]);
}

// ── DELETE — cancel appointment ──────────────────────────────
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) json_response(['error' => 'id required'], 422);

    $stmt = $db->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$id]);

    // Get doctor_id to sync status
    $stmt = $db->prepare("SELECT doctor_id FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    $doctor_id = $stmt->fetchColumn();
    if ($doctor_id) {
        syncDoctorStatus($db, $doctor_id);
    }

    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);