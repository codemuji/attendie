<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$monthParam = $_GET['month'] ?? date('Y-m');

try {
    echo json_encode($attendanceTracker->monthlyReport($monthParam));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
