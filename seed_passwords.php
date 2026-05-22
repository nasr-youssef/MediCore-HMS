<?php
// ============================================================
//  MediCore/seed_passwords.php
//  Run ONCE to set proper bcrypt hashes for demo accounts
//  Visit: http://localhost/MediCore/seed_passwords.php
// ============================================================
require_once __DIR__ . '/Config.php';

$db = getDB();

$accounts = [
    ['email' => 'admin@hospital.gov.eg',   'password' => 'admin123'],
    ['email' => 'doctor@hospital.gov.eg',  'password' => 'doctor123'],
    ['email' => 'emily@hospital.gov.eg',   'password' => 'doctor123'],
    ['email' => 'marcus@hospital.gov.eg',  'password' => 'doctor123'],
    ['email' => 'anna@hospital.gov.eg',    'password' => 'doctor123'],
    ['email' => 'patient@hospital.gov.eg', 'password' => 'patient123'],
    ['email' => 'robert@hospital.gov.eg',  'password' => 'patient123'],
    ['email' => 'amara@hospital.gov.eg',   'password' => 'patient123'],
];

$stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");

$results = [];
foreach ($accounts as $acc) {
    $hash = password_hash($acc['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt->execute([$hash, $acc['email']]);
    $results[] = $acc['email'] . ' → updated (' . ($stmt->rowCount() ? 'OK' : 'NOT FOUND') . ')';
}

header('Content-Type: text/html; charset=utf-8');
echo "<h2>MediCore — Password Seeder</h2>";
echo "<p>All demo account passwords have been updated:</p><ul>";
foreach ($results as $r) {
    echo "<li>$r</li>";
}
echo "</ul>";
echo "<p style='color:green;font-weight:bold'>✅ Done! You can now log in with the demo accounts.</p>";
echo "<p><a href='medicore-login.html'>← Go to Login</a></p>";
