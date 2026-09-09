<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$monthParam = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}

$startDate = $monthParam . '-01';
$daysInMonth = (int)date('t', strtotime($startDate));
$endDate = $monthParam . '-' . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);
$todayDate = date('Y-m-d');

$settings = getShiftSettings($pdo);

// Fetch all logs for this month
$stmt = $pdo->prepare("
    SELECT * FROM `attendance_logs` 
    WHERE `log_date` BETWEEN ? AND ? 
    ORDER BY `log_date` DESC
");
$stmt->execute([$startDate, $endDate]);
$logs = $stmt->fetchAll();

$logsByDate = [];
foreach ($logs as $l) {
    $logsByDate[$l['log_date']] = $l;
}

$history = [];
$totalPresent = 0;
$totalLate = 0;
$totalHalfDay = 0;
$totalAbsent = 0;
$totalWorkedSeconds = 0;
$discrepancyCount = 0;

// Iterate backwards from end of month or today down to 1
for ($d = $daysInMonth; $d >= 1; $d--) {
    $dateStr = $monthParam . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    $timestamp = strtotime($dateStr);
    $dayOfWeek = date('D', $timestamp); // Mon, Tue, ...
    $dayName = date('l', $timestamp);
    $isSunday = ($dayOfWeek === 'Sun');
    $isPast = ($dateStr < $todayDate);
    $isToday = ($dateStr === $todayDate);
    $isFuture = ($dateStr > $todayDate);

    $log = $logsByDate[$dateStr] ?? null;

    $item = [
        'date' => $dateStr,
        'date_formatted' => date('D, M d', $timestamp),
        'day_name' => $dayName,
        'is_sunday' => $isSunday,
        'is_today' => $isToday,
        'is_future' => $isFuture,
        'has_log' => ($log !== null),
        'check_in' => null,
        'check_out' => null,
        'worked_seconds' => 0,
        'worked_formatted' => '--:--',
        'status_in' => null,
        'status_day' => null,
        'late_minutes' => 0,
        'badge_label' => '',
        'badge_type' => 'neutral', // emerald, amber, rose, muted, blue
        'discrepancy' => false,
        'discrepancy_reason' => null
    ];

    if ($isFuture) {
        $item['badge_label'] = 'UPCOMING';
        $item['badge_type'] = 'muted';
    } elseif ($isSunday) {
        $item['badge_label'] = 'SCHEDULED OFF';
        $item['badge_type'] = 'muted';
    } elseif ($log) {
        $totalPresent++;
        $item['check_in'] = !empty($log['check_in']) ? date('h:i A', strtotime($log['check_in'])) : null;
        $item['check_out'] = !empty($log['check_out']) ? date('h:i A', strtotime($log['check_out'])) : null;
        $item['late_minutes'] = (int)$log['late_minutes'];
        $item['status_in'] = $log['status_in'];
        $item['status_day'] = $log['status_day'];
        
        $seconds = (int)$log['worked_seconds'];
        if ($isToday && !empty($log['check_in']) && empty($log['check_out'])) {
            $seconds = max(0, time() - strtotime($log['check_in']));
            $item['badge_label'] = 'ACTIVE (IN PROGRESS)';
            $item['badge_type'] = 'blue';
        }
        $item['worked_seconds'] = $seconds;
        $totalWorkedSeconds += $seconds;
        $item['worked_formatted'] = formatWorkedSeconds($seconds);

        if ($log['status_in'] === 'LATE') {
            $totalLate++;
            $item['discrepancy'] = true;
            $item['discrepancy_reason'] = "Late check-in (+{$log['late_minutes']}m past grace cutoff)";
        }

        if ($log['status_day'] === 'HALF_DAY') {
            $totalHalfDay++;
            $item['discrepancy'] = true;
            $item['discrepancy_reason'] = ($item['discrepancy_reason'] ? $item['discrepancy_reason'] . ' & ' : '') . 'Half-day duration (<8.0 hrs)';
        } elseif ($log['status_day'] === 'INCOMPLETE' && !$isToday) {
            $item['discrepancy'] = true;
            $item['discrepancy_reason'] = 'Incomplete shift (missing check-out or under 4.0 hrs)';
        }

        // Determine badge label
        if (!$isToday || !empty($log['check_out'])) {
            if ($log['status_in'] === 'ON_TIME' && $log['status_day'] === 'FULL_DAY') {
                $item['badge_label'] = 'FULL DAY • ON-TIME';
                $item['badge_type'] = 'emerald';
            } elseif ($log['status_in'] === 'LATE' && $log['status_day'] === 'FULL_DAY') {
                $item['badge_label'] = "LATE (+{$log['late_minutes']}m)";
                $item['badge_type'] = 'amber';
            } elseif ($log['status_day'] === 'HALF_DAY') {
                $item['badge_label'] = 'HALF DAY';
                $item['badge_type'] = 'amber';
            } else {
                $item['badge_label'] = $log['status_in'] === 'LATE' ? 'LATE / INCOMPLETE' : 'INCOMPLETE';
                $item['badge_type'] = 'rose';
            }
        }
    } elseif ($isPast) {
        // Mon-Sat workday with no punch
        $totalAbsent++;
        $item['badge_label'] = 'ABSENT / UNMARKED';
        $item['badge_type'] = 'rose';
        $item['discrepancy'] = true;
        $item['discrepancy_reason'] = 'No check-in recorded on company workday';
    }

    if ($item['discrepancy']) {
        $discrepancyCount++;
    }

    $history[] = $item;
}

echo json_encode([
    'success' => true,
    'month' => $monthParam,
    'month_name' => date('F Y', strtotime($startDate)),
    'summary' => [
        'total_present' => $totalPresent,
        'total_late' => $totalLate,
        'total_half_day' => $totalHalfDay,
        'total_absent' => $totalAbsent,
        'total_worked_seconds' => $totalWorkedSeconds,
        'total_worked_formatted' => formatWorkedSeconds($totalWorkedSeconds),
        'discrepancy_count' => $discrepancyCount,
    ],
    'settings' => $settings,
    'days' => $history
]);
