<?php
// ============================================================
//  MediCore/invoices.php
//  GET  /invoices.php        → list invoices (role-filtered)
//  PUT  /invoices.php?id=x   → update invoice status
// ============================================================
require_once __DIR__ . '/Config.php';

$user   = auth_required();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sql = "
        SELECT i.id, i.invoice_ref, i.service_name, i.amount,
               i.insurance_coverage, i.status, i.due_date, i.paid_at, i.created_at,
               pu.name AS patient_name, p.patient_code
        FROM invoices i
        JOIN patients p ON p.id = i.patient_id
        JOIN users pu ON pu.id = p.user_id
        WHERE 1=1
    ";
    $params = [];

    // Role-based filtering
    if ($user['role'] === 'patient') {
        $sql .= " AND p.user_id = ?";
        $params[] = $user['id'];
    }

    $status = $_GET['status'] ?? null;
    if ($status) {
        $sql .= " AND i.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY i.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    json_response(['data' => $rows, 'total' => count($rows)]);
}

// ── POST — create invoice ────────────────────────────────────
if ($method === 'POST') {
    role_required('admin');
    $body = json_decode(file_get_contents('php://input'), true);

    $required = ['patient_id', 'service_name', 'amount'];
    foreach ($required as $f) {
        if (empty($body[$f])) json_response(['error' => "Field '$f' is required"], 422);
    }

    $count = $db->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
    $ref   = 'INV-' . str_pad($count + 2242, 4, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("
        INSERT INTO invoices
          (id, invoice_ref, patient_id, appointment_id, service_name, amount, insurance_coverage, status, due_date)
        VALUES (UUID(), ?, ?, ?, ?, ?, ?, 'issued', ?)
    ");
    $stmt->execute([
        $ref,
        $body['patient_id'],
        $body['appointment_id'] ?? null,
        $body['service_name'],
        $body['amount'],
        $body['insurance_coverage'] ?? 0,
        $body['due_date'] ?? null,
    ]);

    json_response(['success' => true, 'invoice_ref' => $ref], 201);
}

if ($method === 'PUT') {
    role_required('admin');
    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = $_GET['id'] ?? $body['id'] ?? null;
    $status = $body['status'] ?? null;

    if (!$id || !$status) json_response(['error' => 'id and status required'], 422);

    $allowed = ['issued','pending','paid','overdue','cancelled'];
    if (!in_array($status, $allowed)) json_response(['error' => 'Invalid status'], 422);

    $updates = "status = ?";
    $params  = [$status];

    if ($status === 'paid') {
        $updates .= ", paid_at = NOW()";
    }

    $params[] = $id;
    $stmt = $db->prepare("UPDATE invoices SET $updates WHERE id = ?");
    $stmt->execute($params);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
