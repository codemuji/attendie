<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$todayDate = date('Y-m-d');
$now = new DateTime();
$nowString = $now->format('Y-m-d H:i:s');

$settings = getShiftSettings($pdo);
$shiftStart = $settings['shift_start_time'] ?? '10:00:00';
$graceMinutes = (int)($settings['grace_period_minutes'] ?? 15);
$fullDayHours = (float)($settings['full_day_hours'] ?? 8.0);
$halfDayHours = (float)($settings['half_day_hours'] ?? 4.0);

// Calculate grace cutoff datetime for today
$graceCutoff = new DateTime($todayDate . ' ' . $shiftStart);
$graceCutoff->modify("+{$graceMinutes} minutes");

// Check today's current log
$stmt = $pdo->prepare("SELECT * FROM `attendance_logs` WHERE `log_date` = ?");
$stmt->execute([$todayDate]);
$log = $stmt->fetch();

if (!$log) {
    // Perform CHECK-IN
    $arrival = evaluateArrival($now, $todayDate, $settings);

    $insert = $pdo->prepare("
        INSERT INTO `attendance_logs` 
        (`log_date`, `check_in`, `status_in`, `late_minutes`, `status_day`) 
        VALUES (?, ?, ?, ?, 'INCOMPLETE')
    ");
    $insert->execute([$todayDate, $nowString, $arrival['status_in'], $arrival['late_minutes']]);

    echo json_encode([
        'success' => true,
        'action' => 'CHECK_IN',
        'message' => 'Checked in successfully at ' . $now->format('h:i A'),
        'timestamp' => $nowString,
        'status_in' => $arrival['status_in'],
        'late_minutes' => $arrival['late_minutes'],
        'is_late' => $arrival['is_late']
    ]);
    exit;
}

if (!empty($log['check_in']) && empty($log['check_out'])) {
    // Perform CHECK-OUT
    $checkInTime = new DateTime($log['check_in']);
    $workedSeconds = max(0, $now->getTimestamp() - $checkInTime->getTimestamp());
    $statusDay = calculateDayStatus($workedSeconds, $settings);

    $update = $pdo->prepare("
        UPDATE `attendance_logs` 
        SET `check_out` = ?, `worked_seconds` = ?, `status_day` = ? 
        WHERE `id` = ?
    ");
    $update->execute([$nowString, $workedSeconds, $statusDay, $log['id']]);

    echo json_encode([
        'success' => true,
        'action' => 'CHECK_OUT',
        'message' => 'Checked out successfully at ' . $now->format('h:i A'),
        'timestamp' => $nowString,
        'worked_seconds' => $workedSeconds,
        'worked_formatted' => formatWorkedSeconds($workedSeconds),
        'status_day' => $statusDay
    ]);
    exit;
}

// Already checked out
http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Today\'s attendance has already been completed. Punches are locked and immutable.'
]);
