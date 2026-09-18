<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';

$fullName = trim((string)($data['mb_full_name'] ?? ''));
$email = strtolower(trim((string)($data['mb_email'] ?? '')));
$password = (string)($data['mb_pwd'] ?? '');
$confirmPassword = (string)($data['cf_pwd'] ?? '');
$majorId = trim((string)($data['mj_id'] ?? ''));

if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '' || $majorId === '') {
    jsonResponse(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['status' => 'error', 'message' => 'รูปแบบอีเมลไม่ถูกต้อง'], 400);
}
if (strlen($password) < 8) {
    jsonResponse(['status' => 'error', 'message' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'], 400);
}
if ($password !== $confirmPassword) {
    jsonResponse(['status' => 'error', 'message' => 'รหัสผ่านไม่ตรงกัน'], 400);
}

$authSecret = requireAuthSecretConfigured();

try {
    $pdo->beginTransaction();
    $emailStmt = $pdo->prepare('SELECT mb_id FROM member WHERE mb_email = :email');
    $emailStmt->execute([':email' => $email]);
    if ($emailStmt->fetchColumn()) {
        $pdo->rollBack();
        jsonResponse(['status' => 'error', 'message' => 'อีเมลนี้ถูกใช้งานแล้ว'], 409);
    }

    $lastStmt = $pdo->query('SELECT mb_id FROM member ORDER BY mb_id DESC LIMIT 1 FOR UPDATE');
    $lastId = $lastStmt->fetchColumn();
    $nextNumber = $lastId ? ((int)substr((string)$lastId, 2) + 1) : 1;
    $memberId = 'mb' . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);
    $token = createAuthToken($memberId, 28800, $authSecret);

    $insert = $pdo->prepare('INSERT INTO member (mb_id, mb_full_name, mb_email, mb_pwd, mj_id, mb_img)
        VALUES (:id, :name, :email, :password, :major, :image)');
    $insert->execute([
        ':id' => $memberId,
        ':name' => $fullName,
        ':email' => $email,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':major' => $majorId,
        ':image' => '',
    ]);

    $userStmt = $pdo->prepare('SELECT m.mb_id, m.mb_full_name, m.mb_email, m.mj_id, m.mb_img,
        f.fac_name, mj.mj_name, t.tut_status
        FROM member m
        LEFT JOIN major mj ON m.mj_id = mj.mj_id
        LEFT JOIN faculty f ON mj.fac_id = f.fac_id
        LEFT JOIN tutor t ON m.mb_id = t.tut_id
        WHERE m.mb_id = :id');
    $userStmt->execute([':id' => $memberId]);
    $user = $userStmt->fetch();
    $pdo->commit();

    safeLog('member_registered', ['member_id' => $memberId]);
    jsonResponse([
        'status' => 'success',
        'message' => 'ลงทะเบียนสำเร็จ',
        'userData' => $user,
        'token' => $token,
    ], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    safeLog('member_registration_failed', ['error_type' => get_class($e)]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการสมัครสมาชิก'], 500);
}
