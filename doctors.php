<?php
// ============================================================
//  MediCore/doctors.php
//  GET /doctors.php          → list all doctors
//  GET /doctors.php?id=xxx   → single doctor
// ============================================================
require_once __DIR__ . '/Config.php';

$user   = auth_required();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id) {
        $stmt = $db->prepare("
            SELECT d.id, d.license_number, d.specialization, d.status, d.rating,
                   u.name, u.email, u.phone,
                   dep.name AS department, dep.id AS department_id
            FROM doctors d
            JOIN users u ON u.id = d.user_id
            JOIN departments dep ON dep.id = d.department_id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if (!$doc) json_response(['error' => 'Doctor not found'], 404);
        json_response(['data' => $doc]);
    }

    // List all doctors
    $sql = "
        SELECT d.id, d.license_number, d.specialization, d.status, d.rating,
               u.name, u.email, u.phone, u.id AS user_id,
               dep.name AS department, dep.id AS department_id
        FROM doctors d
        JOIN users u ON u.id = d.user_id
        JOIN departments dep ON dep.id = d.department_id
        ORDER BY u.name ASC
    ";
    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll();

    json_response(['data' => $rows, 'total' => count($rows)]);
}

json_response(['error' => 'Method not allowed'], 405);
