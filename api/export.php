<?php
require_once __DIR__ . '/../config/db.php';

$monthParam = $_GET['month'] ?? date('Y-m');
$report = $attendanceTracker->monthlyReport($monthParam);

$filename = "attendie_report_{$monthParam}.csv";

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filename}\"");

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header title
fputcsv($output, ["ATTENDIE - PERSONAL ATTENDANCE & DISCREPANCY AUDIT REPORT"]);
fputcsv($output, ["Month: " . $report['month_name'], "Generated At: " . date('Y-m-d H:i:s')]);
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

// Sort chronological (oldest to newest for export)
$days = array_reverse($report['days']);

foreach ($days as $day) {
    if ($day['is_sunday']) {
        fputcsv($output, [$day['date'], $day['day_name'], '-', '-', '-', 'OFF', 'SCHEDULED OFF', 0, 'Official Weekend Day Off']);
        continue;
    }

    if ($day['has_log']) {
        $inTime = $day['check_in'] ?: '-';
        $outTime = $day['check_out'] ?: ($day['is_today'] ? 'In Progress' : '-');
        $note = $day['discrepancy'] && $day['discrepancy_reason'] ? $day['discrepancy_reason'] : 'Verified & Valid';

        fputcsv($output, [
            $day['date'],
            $day['day_name'],
            $inTime,
            $outTime,
            $day['worked_formatted'],
            $day['status_in'] ?: '-',
            $day['status_day'] ?: '-',
            $day['late_minutes'],
            $note
        ]);
    } elseif (!$day['is_future']) {
        fputcsv($output, [$day['date'], $day['day_name'], 'ABSENT', 'ABSENT', '0h 0m', 'ABSENT', 'ABSENT', 0, 'No attendance recorded on official workday']);
    } else {
        fputcsv($output, [$day['date'], $day['day_name'], '-', '-', '-', 'UPCOMING', 'UPCOMING', 0, 'Future calendar date']);
    }
}

fclose($output);
exit;
