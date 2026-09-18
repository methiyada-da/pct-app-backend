<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('GET, OPTIONS');
handlePreflight();
requireMethod('GET');
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);

try {
    $stmt = $pdo->prepare('SELECT m.mb_id, m.mb_full_name, m.mb_email, m.mj_id, m.mb_img,
        f.fac_name, mj.mj_name, t.tut_status
        FROM member m
        LEFT JOIN major mj ON m.mj_id = mj.mj_id
        LEFT JOIN faculty f ON mj.fac_id = f.fac_id
        LEFT JOIN tutor t ON m.mb_id = t.tut_id
        WHERE m.mb_id = :id');
    $stmt->execute([':id' => $memberId]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['status' => 'error', 'message' => 'ไม่พบผู้ใช้'], 404);
    jsonResponse(['status' => 'success', 'userData' => $user]);
} catch (PDOException $e) {
    safeLog('get_user_failed', ['member_id' => $memberId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการโหลดข้อมูลผู้ใช้'], 500);
}
