<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$todayDate = date('Y-m-d');
$now = new DateTime();
$serverTimeString = $now->format('Y-m-d H:i:s');
$currentTimeFormatted = $now->format('h:i:s A');
$currentDateFormatted = $now->format('l, d M Y');

$settings = getShiftSettings($pdo);

// Fetch today's record if any
$stmt = $pdo->prepare("SELECT * FROM `attendance_logs` WHERE `log_date` = ?");
$stmt->execute([$todayDate]);
$todayLog = $stmt->fetch();

$state = 'NOT_CHECKED_IN';
$elapsedSeconds = 0;

if ($todayLog) {
    if (!empty($todayLog['check_in']) && empty($todayLog['check_out'])) {
        $state = 'CHECKED_IN';
        $checkInTime = new DateTime($todayLog['check_in']);
        $elapsedSeconds = max(0, $now->getTimestamp() - $checkInTime->getTimestamp());
    } elseif (!empty($todayLog['check_in']) && !empty($todayLog['check_out'])) {
        $state = 'COMPLETED';
        $elapsedSeconds = (int)$todayLog['worked_seconds'];
    }
}

// Calculate simple streak of present days leading up to today
$streakStmt = $pdo->query("SELECT `log_date` FROM `attendance_logs` WHERE `check_in` IS NOT NULL ORDER BY `log_date` DESC LIMIT 60");
$dates = $streakStmt->fetchAll(PDO::FETCH_COLUMN);
$streak = count($dates);

echo json_encode([
    'success' => true,
    'state' => $state,
    'server_time' => $serverTimeString,
    'current_time_formatted' => $currentTimeFormatted,
    'current_date_formatted' => $currentDateFormatted,
    'today_date' => $todayDate,
    'today_log' => $todayLog,
    'elapsed_seconds' => $elapsedSeconds,
    'settings' => $settings,
    'streak' => $streak
]);
