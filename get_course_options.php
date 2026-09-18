<?php
require_once 'helper.php';
setCorsHeaders('GET, OPTIONS');
handlePreflight();
requireMethod('GET');
require_once 'config.php';

try {
    $pdo->exec("SET NAMES utf8mb4");

    $groups = $pdo->query("SELECT cg_id, cg_name FROM course_group ORDER BY cg_name")->fetchAll(PDO::FETCH_ASSOC);
    $courses = $pdo->query("SELECT crs_id, crs_name, cg_id FROM course ORDER BY crs_name")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "course_groups" => $groups,
        "courses" => $courses,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    safeLog('get_course_options_failed', ['error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการโหลดรายวิชา'], 500);
}
?>
