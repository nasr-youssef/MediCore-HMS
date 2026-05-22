<?php
// ============================================================
//  MediCore/logout.php — Destroy session
// ============================================================
require_once __DIR__ . '/Config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();

json_response(['success' => true, 'message' => 'Logged out']);
