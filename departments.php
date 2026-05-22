<?php
// ============================================================
//  MediCore/departments.php
//  GET /departments.php → list all departments
// ============================================================
require_once __DIR__ . '/Config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, code, capacity FROM departments ORDER BY name ASC");
    $rows = $stmt->fetchAll();
    json_response(['data' => $rows, 'total' => count($rows)]);
}

json_response(['error' => 'Method not allowed'], 405);
