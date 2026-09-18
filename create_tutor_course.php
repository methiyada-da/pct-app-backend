<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);

$courseId = trim((string)($data['crs_id'] ?? ''));
$name = trim((string)($data['tutc_name'] ?? ''));
$description = trim((string)($data['tutc_desc'] ?? ''));
$price = filter_var($data['tutc_price'] ?? null, FILTER_VALIDATE_FLOAT);
$status = (int)($data['tutc_status'] ?? 0) === 1 ? 1 : 0;
if ($courseId === '' || $name === '' || $price === false || $price < 0) {
    jsonResponse(['status' => 'error', 'message' => 'ข้อมูลคอร์สไม่ถูกต้องหรือไม่ครบถ้วน'], 400);
}
$schedules = validateSchedules($data['schedules'] ?? [], $status === 1);

try {
    $approved = $pdo->prepare('SELECT tut_id FROM tutor WHERE tut_id = :id AND tut_status = 1');
    $approved->execute([':id' => $memberId]);
    if (!$approved->fetchColumn()) jsonResponse(['status' => 'error', 'message' => 'บัญชีติวเตอร์ยังไม่ได้รับอนุมัติ'], 403);

    $pdo->beginTransaction();
    $lastId = $pdo->query('SELECT tutc_id FROM tutor_course ORDER BY tutc_id DESC LIMIT 1 FOR UPDATE')->fetchColumn();
    $number = $lastId ? ((int)substr((string)$lastId, 4) + 1) : 1;
    $tutorCourseId = 'TUTC' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare('INSERT INTO tutor_course (tutc_id, tutc_name, tutc_desc, tutc_status, tutc_price, crs_id, tut_id)
        VALUES (:id, :name, :description, :status, :price, :course_id, :tutor_id)');
    $stmt->execute([
        ':id' => $tutorCourseId, ':name' => $name, ':description' => $description,
        ':status' => $status, ':price' => $price, ':course_id' => $courseId, ':tutor_id' => $memberId,
    ]);
    $scheduleStmt = $pdo->prepare('INSERT INTO tutor_schedule (tutc_id, sch_day, sch_start, sch_end)
        VALUES (:id, :day, :start, :end)');
    foreach ($schedules as $schedule) {
        $scheduleStmt->execute([':id' => $tutorCourseId, ':day' => $schedule['sch_day'], ':start' => $schedule['sch_start'], ':end' => $schedule['sch_end']]);
    }
    $pdo->commit();
    jsonResponse(['status' => 'success', 'message' => 'สร้างคอร์สสำเร็จ', 'tutc_id' => $tutorCourseId], 201);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    safeLog('course_create_failed', ['member_id' => $memberId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการสร้างคอร์ส'], 500);
}
