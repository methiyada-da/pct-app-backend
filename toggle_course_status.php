<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);
$courseId = trim((string)($data['tutc_id'] ?? ''));
$status = filter_var($data['tutc_status'] ?? null, FILTER_VALIDATE_INT);
if ($courseId === '' || !in_array($status, [0, 1], true)) jsonResponse(['status' => 'error', 'message' => 'ข้อมูลสถานะไม่ถูกต้อง'], 400);

try {
    $owner = $pdo->prepare('SELECT tut_id, tutc_status FROM tutor_course WHERE tutc_id = :id');
    $owner->execute([':id' => $courseId]);
    $course = $owner->fetch();
    if (!$course) jsonResponse(['status' => 'error', 'message' => 'ไม่พบคอร์ส'], 404);
    if (!hash_equals((string)$course['tut_id'], $memberId)) {
        jsonResponse(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไขคอร์สนี้'], 403);
    }
    if ((int)$course['tutc_status'] === $status) {
        jsonResponse(['status' => 'error', 'message' => 'สถานะคอร์สไม่มีการเปลี่ยนแปลง'], 409);
    }
    if ($status === 1) {
        $schedule = $pdo->prepare('SELECT COUNT(*) FROM tutor_schedule WHERE tutc_id = :id');
        $schedule->execute([':id' => $courseId]);
        if ((int)$schedule->fetchColumn() === 0) jsonResponse(['status' => 'error', 'message' => 'คอร์สที่เปิดรับต้องมีตารางสอน'], 409);
    }
    $stmt = $pdo->prepare('UPDATE tutor_course SET tutc_status = :status WHERE tutc_id = :id AND tut_id = :owner');
    $stmt->execute([':status' => $status, ':id' => $courseId, ':owner' => $memberId]);
    if ($stmt->rowCount() === 1) jsonResponse(['status' => 'success', 'message' => 'อัปเดตสถานะสำเร็จ']);
    jsonResponse(['status' => 'error', 'message' => 'ไม่สามารถอัปเดตสถานะคอร์สได้'], 409);
} catch (PDOException $e) {
    safeLog('course_status_failed', ['member_id' => $memberId, 'course_id' => $courseId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนสถานะ'], 500);
}
