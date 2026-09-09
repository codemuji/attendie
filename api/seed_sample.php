<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

// Seed sample records for September 2026 if empty
$checkCount = $pdo->query("SELECT COUNT(*) FROM `attendance_logs`")->fetchColumn();

if ($checkCount == 0) {
    $samples = [
        // Sep 1 (Tue) - On-time Full Day
        ['2026-09-01', '2026-09-01 10:02:14', '2026-09-01 18:34:02', 30708, 'ON_TIME', 'FULL_DAY', 0],
        // Sep 2 (Wed) - On-time Full Day
        ['2026-09-02', '2026-09-02 09:58:45', '2026-09-02 18:31:10', 30745, 'ON_TIME', 'FULL_DAY', 0],
        // Sep 3 (Thu) - On-time Full Day
        ['2026-09-03', '2026-09-03 10:11:30', '2026-09-03 18:45:00', 30810, 'ON_TIME', 'FULL_DAY', 0],
        // Sep 4 (Fri) - On-time Full Day
        ['2026-09-04', '2026-09-04 10:05:12', '2026-09-04 18:35:50', 30638, 'ON_TIME', 'FULL_DAY', 0],
        // Sep 5 (Sat) - On-time Full Day
        ['2026-09-05', '2026-09-05 10:12:00', '2026-09-05 18:30:00', 29880, 'ON_TIME', 'FULL_DAY', 0],
        // Sep 6 (Sun) - Scheduled Day Off
        // Sep 7 (Mon) - On-time Full Day
        ['2026-09-07', '2026-09-07 09:55:20', '2026-09-07 18:30:15', 30895, 'ON_TIME', 'FULL_DAY', 0],
        // Sep 8 (Tue) - Late Arrival (+7m past grace) - DISPUTE CASE
        ['2026-09-08', '2026-09-08 10:22:40', '2026-09-08 18:41:00', 29900, 'LATE', 'FULL_DAY', 7],
        // Sep 9 (Wed) - On-time Full Day
        ['2026-09-09', '2026-09-09 10:08:15', '2026-09-09 18:35:40', 30445, 'ON_TIME', 'FULL_DAY', 0],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO `attendance_logs` 
        (`log_date`, `check_in`, `check_out`, `worked_seconds`, `status_in`, `status_day`, `late_minutes`) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($samples as $s) {
        $stmt->execute($s);
    }

    echo json_encode(['success' => true, 'message' => 'Seeded ' . count($samples) . ' sample attendance logs for September 2026']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Database already has ' . $checkCount . ' logs. Seeding skipped.']);
