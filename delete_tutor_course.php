<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);
$courseId = trim((string)($data['tutc_id'] ?? ''));
if ($courseId === '') jsonResponse(['status' => 'error', 'message' => 'ไม่พบรหัสคอร์ส'], 400);

try {
    $owner = $pdo->prepare('SELECT tut_id FROM tutor_course WHERE tutc_id = :id');
    $owner->execute([':id' => $courseId]);
    $ownerId = $owner->fetchColumn();
    if (!$ownerId) jsonResponse(['status' => 'error', 'message' => 'ไม่พบคอร์ส'], 404);
    if (!hash_equals((string)$ownerId, $memberId)) jsonResponse(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ลบคอร์สนี้'], 403);
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM tutor_schedule WHERE tutc_id = :id')->execute([':id' => $courseId]);
    $stmt = $pdo->prepare('DELETE FROM tutor_course WHERE tutc_id = :id AND tut_id = :owner');
    $stmt->execute([':id' => $courseId, ':owner' => $memberId]);
    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        jsonResponse(['status' => 'error', 'message' => 'ไม่พบคอร์สที่ต้องการลบ'], 404);
    }
    $pdo->commit();
    jsonResponse(['status' => 'success', 'message' => 'ลบคอร์สสำเร็จ']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    safeLog('course_delete_failed', ['member_id' => $memberId, 'course_id' => $courseId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบคอร์ส'], 500);
}
