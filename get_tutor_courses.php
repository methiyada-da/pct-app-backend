<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';

$requestedTutorId = trim((string)($data['tut_id'] ?? ''));
$requestedCourseId = trim((string)($data['tutc_id'] ?? ''));
$isPublicDetail = $requestedCourseId !== '';
if ($isPublicDetail) {
    if ($requestedTutorId === '') jsonResponse(['status' => 'error', 'message' => 'ไม่พบรหัสติวเตอร์'], 400);
    $tutorId = $requestedTutorId;
} else {
    $tutorId = authenticatedMemberId($pdo);
}

try {
    $sql = 'SELECT tc.tutc_id, tc.tutc_name, tc.tutc_desc, tc.tutc_status, tc.tutc_price,
        c.crs_id, c.crs_name, cg.cg_id, cg.cg_name
        FROM tutor_course tc
        LEFT JOIN course c ON tc.crs_id = c.crs_id
        LEFT JOIN course_group cg ON c.cg_id = cg.cg_id
        WHERE tc.tut_id = :tutor_id';
    $params = [':tutor_id' => $tutorId];
    if ($isPublicDetail) {
        $sql .= ' AND tc.tutc_id = :course_id AND tc.tutc_status = 1';
        $params[':course_id'] = $requestedCourseId;
    }
    $sql .= ' ORDER BY tc.tutc_id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
    $scheduleStmt = $pdo->prepare('SELECT sch_id, sch_day, sch_start, sch_end FROM tutor_schedule WHERE tutc_id = :id ORDER BY sch_day, sch_start');
    $dayNames = [1 => 'จ.', 2 => 'อ.', 3 => 'พ.', 4 => 'พฤ.', 5 => 'ศ.', 6 => 'ส.', 7 => 'อา.'];
    foreach ($courses as &$course) {
        $scheduleStmt->execute([':id' => $course['tutc_id']]);
        $course['schedules'] = array_map(static function (array $row) use ($dayNames): array {
            return [
                'id' => (int)$row['sch_id'],
                'day' => (int)$row['sch_day'],
                'day_label' => $dayNames[(int)$row['sch_day']] ?? '',
                'start' => substr((string)$row['sch_start'], 0, 5),
                'end' => substr((string)$row['sch_end'], 0, 5),
            ];
        }, $scheduleStmt->fetchAll());
    }
    unset($course);
    if ($isPublicDetail && count($courses) === 0) jsonResponse(['status' => 'error', 'message' => 'ไม่พบคอร์สที่เปิดรับ'], 404);
    jsonResponse(['status' => 'success', 'courses' => $courses]);
} catch (PDOException $e) {
    safeLog('get_courses_failed', ['tutor_id' => $tutorId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการโหลดคอร์ส'], 500);
}
