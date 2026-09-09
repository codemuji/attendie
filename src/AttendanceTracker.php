<?php
/**
 * Attendie - Deep Domain Module: AttendanceTracker
 * Encapsulates the entire Workday lifecycle, punch invariants, and shift policy rules.
 */

class AttendanceException extends Exception {}
class PunchLockedException extends AttendanceException {}

class AttendanceTracker {
    private PDO $pdo;
    private ?DateTime $clock;

    public function __construct(PDO $pdo, ?DateTime $clock = null) {
        $this->pdo = $pdo;
        $this->clock = $clock;
    }

    private function getNow(): DateTime {
        return $this->clock ? clone $this->clock : new DateTime();
    }

    /**
     * Get shift policy settings from database
     */
    public function getSettings(): array {
        $stmt = $this->pdo->query("SELECT `key_name`, `key_value` FROM `settings`");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key_name']] = $row['key_value'];
        }
        return $settings;
    }

    /**
     * Inspect today's status, active shift timer, and streak
     */
    public function today(): array {
        $now = $this->getNow();
        $todayDate = $now->format('Y-m-d');
        $serverTimeString = $now->format('Y-m-d H:i:s');
        $currentTimeFormatted = $now->format('h:i:s A');
        $currentDateFormatted = $now->format('l, d M Y');

        $settings = $this->getSettings();

        $stmt = $this->pdo->prepare("SELECT * FROM `attendance_logs` WHERE `log_date` = ?");
        $stmt->execute([$todayDate]);
        $todayLog = $stmt->fetch(PDO::FETCH_ASSOC);

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

        $streakStmt = $this->pdo->query("SELECT `log_date` FROM `attendance_logs` WHERE `check_in` IS NOT NULL ORDER BY `log_date` DESC LIMIT 60");
        $dates = $streakStmt->fetchAll(PDO::FETCH_COLUMN);
        $streak = count($dates);

        return [
            'success' => true,
            'state' => $state,
            'server_time' => $serverTimeString,
            'current_time_formatted' => $currentTimeFormatted,
            'current_date_formatted' => $currentDateFormatted,
            'today_date' => $todayDate,
            'today_log' => $todayLog ?: null,
            'elapsed_seconds' => $elapsedSeconds,
            'settings' => $settings,
            'streak' => $streak
        ];
    }

    /**
     * Execute an atomic Check-In or Check-Out for the current date
     * @throws PunchLockedException if attendance for today is already completed
     */
    public function punch(?DateTime $overrideTime = null): array {
        $now = $overrideTime ?: $this->getNow();
        $todayDate = $now->format('Y-m-d');
        $nowString = $now->format('Y-m-d H:i:s');
        $settings = $this->getSettings();

        $stmt = $this->pdo->prepare("SELECT * FROM `attendance_logs` WHERE `log_date` = ?");
        $stmt->execute([$todayDate]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$log) {
            // Check-In Action
            $arrival = $this->evaluateArrival($now, $todayDate, $settings);

            $insert = $this->pdo->prepare("
                INSERT INTO `attendance_logs` 
                (`log_date`, `check_in`, `status_in`, `late_minutes`, `status_day`) 
                VALUES (?, ?, ?, ?, 'INCOMPLETE')
            ");
            $insert->execute([$todayDate, $nowString, $arrival['status_in'], $arrival['late_minutes']]);

            return [
                'success' => true,
                'action' => 'CHECK_IN',
                'message' => 'Checked in successfully at ' . $now->format('h:i A'),
                'timestamp' => $nowString,
                'status_in' => $arrival['status_in'],
                'late_minutes' => $arrival['late_minutes'],
                'is_late' => $arrival['is_late']
            ];
        }

        if (!empty($log['check_in']) && empty($log['check_out'])) {
            // Check-Out Action
            $checkInTime = new DateTime($log['check_in']);
            $workedSeconds = max(0, $now->getTimestamp() - $checkInTime->getTimestamp());
            $statusDay = $this->evaluateDayStatus($workedSeconds, $settings);

            $update = $this->pdo->prepare("
                UPDATE `attendance_logs` 
                SET `check_out` = ?, `worked_seconds` = ?, `status_day` = ? 
                WHERE `id` = ?
            ");
            $update->execute([$nowString, $workedSeconds, $statusDay, $log['id']]);

            return [
                'success' => true,
                'action' => 'CHECK_OUT',
                'message' => 'Checked out successfully at ' . $now->format('h:i A'),
                'timestamp' => $nowString,
                'worked_seconds' => $workedSeconds,
                'worked_formatted' => $this->formatDuration($workedSeconds),
                'status_day' => $statusDay
            ];
        }

        // Both check-in and check-out exist: sealed
        throw new PunchLockedException("Today's attendance has already been completed. Punches are locked and immutable.");
    }

    /**
     * Generate the monthly report with complete daily breakdown, metrics, and dispute highlights
     */
    public function monthlyReport(string $monthParam): array {
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = $this->getNow()->format('Y-m');
        }

        $startDate = $monthParam . '-01';
        $daysInMonth = (int)date('t', strtotime($startDate));
        $endDate = $monthParam . '-' . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);
        $todayDate = $this->getNow()->format('Y-m-d');
        $settings = $this->getSettings();

        $stmt = $this->pdo->prepare("
            SELECT * FROM `attendance_logs` 
            WHERE `log_date` BETWEEN ? AND ? 
            ORDER BY `log_date` DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        for ($d = $daysInMonth; $d >= 1; $d--) {
            $dateStr = $monthParam . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            $timestamp = strtotime($dateStr);
            $dayOfWeek = date('D', $timestamp);
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
                'badge_type' => 'neutral',
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
                    $seconds = max(0, $this->getNow()->getTimestamp() - strtotime($log['check_in']));
                    $item['badge_label'] = 'ACTIVE (IN PROGRESS)';
                    $item['badge_type'] = 'blue';
                }
                $item['worked_seconds'] = $seconds;
                $totalWorkedSeconds += $seconds;
                $item['worked_formatted'] = $this->formatDuration($seconds);

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

        return [
            'success' => true,
            'month' => $monthParam,
            'month_name' => date('F Y', strtotime($startDate)),
            'summary' => [
                'total_present' => $totalPresent,
                'total_late' => $totalLate,
                'total_half_day' => $totalHalfDay,
                'total_absent' => $totalAbsent,
                'total_worked_seconds' => $totalWorkedSeconds,
                'total_worked_formatted' => $this->formatDuration($totalWorkedSeconds),
                'discrepancy_count' => $discrepancyCount,
            ],
            'settings' => $settings,
            'days' => $history
        ];
    }

    public function formatDuration(int $seconds): string {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        return "{$h}h {$m}m";
    }

    public function evaluateDayStatus(int $workedSeconds, array $settings): string {
        $fullDayHours = (float)($settings['full_day_hours'] ?? 8.0);
        $halfDayHours = (float)($settings['half_day_hours'] ?? 4.0);
        $workedHours = $workedSeconds / 3600;

        if ($workedHours >= $fullDayHours) {
            return 'FULL_DAY';
        } elseif ($workedHours >= $halfDayHours) {
            return 'HALF_DAY';
        }
        return 'INCOMPLETE';
    }

    public function evaluateArrival(DateTime $punchTime, string $logDate, array $settings): array {
        $shiftStart = $settings['shift_start_time'] ?? '10:00:00';
        $graceMinutes = (int)($settings['grace_period_minutes'] ?? 15);

        $graceCutoff = new DateTime($logDate . ' ' . $shiftStart);
        $graceCutoff->modify("+{$graceMinutes} minutes");

        $isLate = $punchTime > $graceCutoff;
        $lateMinutes = 0;

        if ($isLate) {
            $diffSeconds = $punchTime->getTimestamp() - $graceCutoff->getTimestamp();
            $lateMinutes = (int)ceil($diffSeconds / 60);
        }

        return [
            'status_in' => $isLate ? 'LATE' : 'ON_TIME',
            'late_minutes' => $lateMinutes,
            'is_late' => $isLate
        ];
    }
}
