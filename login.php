<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';

$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    jsonResponse(['status' => 'error', 'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'], 400);
}

$authSecret = requireAuthSecretConfigured();

try {
    $stmt = $pdo->prepare('SELECT m.mb_id, m.mb_full_name, m.mb_email, m.mb_pwd, m.mj_id, m.mb_img,
        f.fac_name, mj.mj_name, t.tut_status
        FROM member m
        LEFT JOIN major mj ON m.mj_id = mj.mj_id
        LEFT JOIN faculty f ON mj.fac_id = f.fac_id
        LEFT JOIN tutor t ON t.tut_id = m.mb_id
        WHERE m.mb_email = :email');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['status' => 'error', 'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'], 401);

    $storedPassword = (string)$row['mb_pwd'];
    $isHash = password_get_info($storedPassword)['algo'] !== null;
    $valid = $isHash ? password_verify($password, $storedPassword) : hash_equals($storedPassword, $password);
    if (!$valid) jsonResponse(['status' => 'error', 'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'], 401);
    $token = createAuthToken((string)$row['mb_id'], 28800, $authSecret);

    if (!$isHash || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
        $upgrade = $pdo->prepare('UPDATE member SET mb_pwd = :password WHERE mb_id = :id');
        $upgrade->execute([':password' => password_hash($password, PASSWORD_DEFAULT), ':id' => $row['mb_id']]);
        safeLog('password_hash_upgraded', ['member_id' => $row['mb_id']]);
    }

    unset($row['mb_pwd']);
    jsonResponse([
        'status' => 'success',
        'userData' => $row,
        'token' => $token,
    ]);
} catch (Throwable $e) {
    safeLog('login_failed_internal', ['error_type' => get_class($e)]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ'], 500);
}
