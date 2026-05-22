<?php
// ============================================================
//  MediCore/beds.php
//  GET /beds.php            → list all beds with ward/dept info
//  GET /beds.php?ward=xxx   → beds for a specific ward
// ============================================================
require_once __DIR__ . '/Config.php';

$user   = auth_required();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $ward_id = $_GET['ward'] ?? null;

    $sql = "
        SELECT b.id, b.bed_number, b.status,
               w.id AS ward_id, w.name AS ward_name, w.ward_type, w.total_beds,
               dep.name AS department, dep.id AS department_id
        FROM beds b
        JOIN wards w ON w.id = b.ward_id
        JOIN departments dep ON dep.id = w.department_id
    ";
    $params = [];

    if ($ward_id) {
        $sql .= " WHERE w.id = ?";
        $params[] = $ward_id;
    }

    $sql .= " ORDER BY dep.name, w.name, b.bed_number";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $beds = $stmt->fetchAll();

    // Also get ward summaries
    $wardSql = "
        SELECT w.id, w.name AS ward_name, w.ward_type, w.total_beds,
               dep.name AS department,
               COUNT(b.id) AS bed_count,
               SUM(CASE WHEN b.status='occupied' THEN 1 ELSE 0 END) AS occupied,
               SUM(CASE WHEN b.status='available' THEN 1 ELSE 0 END) AS available,
               SUM(CASE WHEN b.status='reserved' THEN 1 ELSE 0 END) AS reserved,
               SUM(CASE WHEN b.status='maintenance' THEN 1 ELSE 0 END) AS maintenance
        FROM wards w
        JOIN departments dep ON dep.id = w.department_id
        LEFT JOIN beds b ON b.ward_id = w.id
        GROUP BY w.id
        ORDER BY dep.name, w.name
    ";
    $wardStmt = $db->query($wardSql);
    $wards = $wardStmt->fetchAll();

    json_response([
        'beds' => $beds,
        'wards' => $wards,
        'total_beds' => count($beds),
    ]);
}

// ── PUT — update bed status ──────────────────────────────────
if ($method === 'PUT') {
    role_required('admin');
    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = $_GET['id'] ?? $body['id'] ?? null;
    $status = $body['status'] ?? null;

    if (!$id || !$status) json_response(['error' => 'id and status required'], 422);

    $allowed = ['available','occupied','reserved','maintenance'];
    if (!in_array($status, $allowed)) json_response(['error' => 'Invalid status'], 422);

    $stmt = $db->prepare("UPDATE beds SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
