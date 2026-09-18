<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('GET, POST, OPTIONS');
handlePreflight();
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT tut_id, tut_desc, tut_skill, tut_gpax, tut_has_exp, tut_exp_desc, tut_status FROM tutor WHERE tut_id = :id');
    $stmt->execute([':id' => $memberId]);
    $row = $stmt->fetch();
    jsonResponse($row ? ['status' => 'exists', 'data' => $row] : ['status' => 'not_found']);
}
requireMethod('POST');
$data = getJsonBody();
$description = trim((string)($data['tut_desc'] ?? ''));
$skill = trim((string)($data['tut_skill'] ?? ''));
$gpax = filter_var($data['tut_gpax'] ?? null, FILTER_VALIDATE_FLOAT);
$hasExperience = (int)($data['tut_has_exp'] ?? 0) === 1 ? 1 : 0;
$experience = trim((string)($data['tut_exp_desc'] ?? ''));
if ($description === '' || $skill === '' || $gpax === false || $gpax < 0 || $gpax > 4 || ($hasExperience === 1 && $experience === '')) {
    jsonResponse(['status' => 'error', 'message' => 'ข้อมูลสมัครติวเตอร์ไม่ถูกต้องหรือไม่ครบถ้วน'], 400);
}

try {
    $check = $pdo->prepare('SELECT tut_id FROM tutor WHERE tut_id = :id');
    $check->execute([':id' => $memberId]);
    if ($check->fetchColumn()) {
        $stmt = $pdo->prepare('UPDATE tutor SET tut_desc = :description, tut_skill = :skill, tut_gpax = :gpax,
            tut_has_exp = :has_exp, tut_exp_desc = :experience, tut_status = 0 WHERE tut_id = :id');
    } else {
        $stmt = $pdo->prepare('INSERT INTO tutor (tut_id, tut_desc, tut_skill, tut_gpax, tut_has_exp, tut_exp_desc, tut_rating, tut_status)
            VALUES (:id, :description, :skill, :gpax, :has_exp, :experience, 0.00, 0)');
    }
    $stmt->execute([
        ':id' => $memberId,
        ':description' => $description,
        ':skill' => $skill,
        ':gpax' => $gpax,
        ':has_exp' => $hasExperience,
        ':experience' => $hasExperience ? $experience : '',
    ]);
    safeLog('tutor_application_submitted', ['member_id' => $memberId]);
    jsonResponse(['status' => 'success', 'message' => 'ส่งคำขอสำเร็จ']);
} catch (PDOException $e) {
    safeLog('tutor_application_failed', ['member_id' => $memberId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการส่งคำขอ'], 500);
}
