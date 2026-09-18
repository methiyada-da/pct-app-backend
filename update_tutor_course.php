<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);

$tutorCourseId = trim((string)($data['tutc_id'] ?? ''));
$name = trim((string)($data['tutc_name'] ?? ''));
$description = trim((string)($data['tutc_desc'] ?? ''));
$price = filter_var($data['tutc_price'] ?? null, FILTER_VALIDATE_FLOAT);
$status = (int)($data['tutc_status'] ?? 0) === 1 ? 1 : 0;
if ($tutorCourseId === '' || $name === '' || $price === false || $price < 0) {
    jsonResponse(['status' => 'error', 'message' => 'ข้อมูลคอร์สไม่ถูกต้องหรือไม่ครบถ้วน'], 400);
}
$schedules = validateSchedules($data['schedules'] ?? [], $status === 1);

try {
    $owner = $pdo->prepare('SELECT tut_id FROM tutor_course WHERE tutc_id = :id');
    $owner->execute([':id' => $tutorCourseId]);
    $ownerId = $owner->fetchColumn();
    if (!$ownerId) jsonResponse(['status' => 'error', 'message' => 'ไม่พบคอร์ส'], 404);
    if (!hash_equals((string)$ownerId, $memberId)) jsonResponse(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไขคอร์สนี้'], 403);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE tutor_course SET tutc_name = :name, tutc_desc = :description,
        tutc_price = :price, tutc_status = :status WHERE tutc_id = :id AND tut_id = :owner');
    $stmt->execute([':name' => $name, ':description' => $description, ':price' => $price, ':status' => $status, ':id' => $tutorCourseId, ':owner' => $memberId]);
    $pdo->prepare('DELETE FROM tutor_schedule WHERE tutc_id = :id')->execute([':id' => $tutorCourseId]);
    $scheduleStmt = $pdo->prepare('INSERT INTO tutor_schedule (tutc_id, sch_day, sch_start, sch_end) VALUES (:id, :day, :start, :end)');
    foreach ($schedules as $schedule) {
        $scheduleStmt->execute([':id' => $tutorCourseId, ':day' => $schedule['sch_day'], ':start' => $schedule['sch_start'], ':end' => $schedule['sch_end']]);
    }
    $pdo->commit();
    jsonResponse(['status' => 'success', 'message' => 'แก้ไขคอร์สสำเร็จ']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    safeLog('course_update_failed', ['member_id' => $memberId, 'course_id' => $tutorCourseId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการแก้ไขคอร์ส'], 500);
}
