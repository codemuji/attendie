<?php
/**
 * Attendie - Database Configuration & Auto-Migration
 * Compatible with Laragon local development & cPanel hosting.
 */

// Database credentials
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_name = getenv('DB_NAME') ?: 'attendie';

// Set timezone (configurable via APP_TIMEZONE env, defaults to Asia/Kolkata)
$appTimezone = getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';
date_default_timezone_set($appTimezone);

try {
    // Connect to server first to ensure database exists
    $initPdo = new PDO("mysql:host={$db_host};port={$db_port}", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $initPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Connect to specific database
    $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create tables if they do not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `attendance_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `log_date` DATE NOT NULL UNIQUE,
            `check_in` DATETIME DEFAULT NULL,
            `check_out` DATETIME DEFAULT NULL,
            `worked_seconds` INT DEFAULT 0,
            `status_in` ENUM('ON_TIME', 'LATE') DEFAULT 'ON_TIME',
            `status_day` ENUM('FULL_DAY', 'HALF_DAY', 'INCOMPLETE', 'ABSENT') DEFAULT 'INCOMPLETE',
            `late_minutes` INT DEFAULT 0,
            `notes` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (`log_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS `settings` (
            `key_name` VARCHAR(64) PRIMARY KEY,
            `key_value` VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Default settings
    $defaults = [
        'shift_start_time' => '10:00:00',
        'grace_period_minutes' => '15',
        'shift_end_time' => '18:30:00',
        'full_day_hours' => '8.0',
        'half_day_hours' => '4.0',
        'work_days' => 'Mon,Tue,Wed,Thu,Fri,Sat',
    ];

    foreach ($defaults as $key => $val) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO `settings` (`key_name`, `key_value`) VALUES (?, ?)");
        $stmt->execute([$key, $val]);
    }

} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection or initialization failed: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Helper to fetch all settings as an associative array
 */
function getShiftSettings(PDO $pdo): array {
    $stmt = $pdo->query("SELECT `key_name`, `key_value` FROM `settings`");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key_name']] = $row['key_value'];
    }
    return $settings;
}

/**
 * Helper: Format seconds into human-readable hours and minutes (e.g. "8h 27m")
 */
function formatWorkedSeconds(int $seconds): string {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return "{$h}h {$m}m";
}

/**
 * Helper: Calculate day status (FULL_DAY, HALF_DAY, INCOMPLETE) based on shift policy
 */
function calculateDayStatus(int $workedSeconds, array $settings): string {
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

/**
 * Helper: Evaluate arrival status and late minutes based on grace window
 */
function evaluateArrival(DateTime $punchTime, string $logDate, array $settings): array {
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
