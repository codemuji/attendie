<?php
require_once __DIR__ . '/../config/db.php';

$monthParam = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}

$startDate = $monthParam . '-01';
$daysInMonth = (int)date('t', strtotime($startDate));
$endDate = $monthParam . '-' . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);
$todayDate = date('Y-m-d');

// Fetch logs
$stmt = $pdo->prepare("
    SELECT * FROM `attendance_logs` 
    WHERE `log_date` BETWEEN ? AND ? 
    ORDER BY `log_date` ASC
");
$stmt->execute([$startDate, $endDate]);
$logs = $stmt->fetchAll();

$logsByDate = [];
foreach ($logs as $l) {
    $logsByDate[$l['log_date']] = $l;
}

$filename = "attendie_report_{$monthParam}.csv";

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filename}\"");

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header title
fputcsv($output, ["ATTENDIE - PERSONAL ATTENDANCE & DISCREPANCY AUDIT REPORT"]);
fputcsv($output, ["Month: " . date('F Y', strtotime($startDate)), "Generated At: " . date('Y-m-d H:i:s')]);
fputcsv($output, ["Shift Policy: Start 10:00 AM (15m Grace) | End 06:30 PM | Full Day: 8.0h | Half Day: 4.0h"]);
fputcsv($output, []); // blank line

// Column headers
fputcsv($output, [
    'Date',
    'Day',
    'Check-In Time',
    'Check-Out Time',
    'Worked Hours',
    'Arrival Status',
    'Day Status',
    'Late Minutes',
    'Discrepancy Note'
]);

for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = $monthParam . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    $timestamp = strtotime($dateStr);
    $dayOfWeek = date('D', $timestamp);
    $dayName = date('l', $timestamp);
    $isSunday = ($dayOfWeek === 'Sun');
    $isPast = ($dateStr < $todayDate);
    $isToday = ($dateStr === $todayDate);

    $log = $logsByDate[$dateStr] ?? null;

    if ($isSunday) {
        fputcsv($output, [$dateStr, $dayName, '-', '-', '-', 'OFF', 'SCHEDULED OFF', 0, 'Official Weekend Day Off']);
        continue;
    }

    if ($log) {
        $inTime = !empty($log['check_in']) ? date('h:i A', strtotime($log['check_in'])) : '-';
        $outTime = !empty($log['check_out']) ? date('h:i A', strtotime($log['check_out'])) : ($isToday ? 'In Progress' : '-');
        
        $seconds = (int)$log['worked_seconds'];
        if ($isToday && empty($log['check_out']) && !empty($log['check_in'])) {
            $seconds = max(0, time() - strtotime($log['check_in']));
        }
        $durationFormatted = formatWorkedSeconds($seconds);

        $discrepancyNote = 'Verified & Valid';
        if ($log['status_in'] === 'LATE') {
            $discrepancyNote = "Late arrival (+{$log['late_minutes']} mins past 10:15 AM grace)";
        }
        if ($log['status_day'] === 'HALF_DAY') {
            $discrepancyNote .= ($discrepancyNote !== 'Verified & Valid' ? ' | ' : '') . 'Half-Day (<8h worked)';
        }

        fputcsv($output, [
            $dateStr,
            $dayName,
            $inTime,
            $outTime,
            $durationFormatted,
            $log['status_in'],
            $log['status_day'],
            $log['late_minutes'],
            $discrepancyNote
        ]);
    } elseif ($isPast) {
        fputcsv($output, [$dateStr, $dayName, 'ABSENT', 'ABSENT', '0h 0m', 'ABSENT', 'ABSENT', 0, 'No attendance recorded on official workday']);
    } else {
        fputcsv($output, [$dateStr, $dayName, '-', '-', '-', 'UPCOMING', 'UPCOMING', 0, 'Future calendar date']);
    }
}

fclose($output);
exit;
