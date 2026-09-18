<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);

$newPassword = (string)($data['mb_pwd'] ?? '');
$confirmPassword = (string)($data['cf_pwd'] ?? '');
$oldPassword = (string)($data['old_pwd'] ?? '');
$imageData = (string)($data['mb_img'] ?? '');
$imageFilename = null;
$imagePath = null;

if ($newPassword === '' && $imageData === '') {
    jsonResponse(['status' => 'error', 'message' => 'ไม่มีข้อมูลที่ต้องการอัปเดต'], 400);
}

try {
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword || $oldPassword === '') {
            jsonResponse(['status' => 'error', 'message' => 'ข้อมูลรหัสผ่านไม่ถูกต้อง'], 400);
        }
        $passwordStmt = $pdo->prepare('SELECT mb_pwd FROM member WHERE mb_id = :id');
        $passwordStmt->execute([':id' => $memberId]);
        $stored = (string)$passwordStmt->fetchColumn();
        $isHash = password_get_info($stored)['algo'] !== null;
        $valid = $isHash ? password_verify($oldPassword, $stored) : hash_equals($stored, $oldPassword);
        if (!$valid) jsonResponse(['status' => 'error', 'message' => 'รหัสผ่านเดิมไม่ถูกต้อง'], 403);
    }

    if ($imageData !== '') {
        if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $imageData, $matches)) {
            jsonResponse(['status' => 'error', 'message' => 'รองรับเฉพาะรูป JPEG และ PNG'], 400);
        }
        $binary = base64_decode(substr($imageData, strpos($imageData, ',') + 1), true);
        if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
            jsonResponse(['status' => 'error', 'message' => 'รูปภาพไม่ถูกต้องหรือมีขนาดเกิน 5 MB'], 400);
        }
        $imageInfo = @getimagesizefromstring($binary);
        $mime = $imageInfo['mime'] ?? '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($extensions[$mime])) jsonResponse(['status' => 'error', 'message' => 'ไฟล์ที่ส่งมาไม่ใช่รูปภาพที่รองรับ'], 400);
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0750, true)) {
            jsonResponse(['status' => 'error', 'message' => 'ไม่สามารถจัดเก็บรูปภาพได้'], 500);
        }
        $imageFilename = 'mb_' . $memberId . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $imagePath = $uploadDir . '/' . $imageFilename;
        if (file_put_contents($imagePath, $binary, LOCK_EX) === false) {
            jsonResponse(['status' => 'error', 'message' => 'ไม่สามารถจัดเก็บรูปภาพได้'], 500);
        }
    }

    $fields = [];
    $params = [':id' => $memberId];
    if ($newPassword !== '') {
        $fields[] = 'mb_pwd = :password';
        $params[':password'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    if ($imageFilename !== null) {
        $fields[] = 'mb_img = :image';
        $params[':image'] = $imageFilename;
    }
    $stmt = $pdo->prepare('UPDATE member SET ' . implode(', ', $fields) . ' WHERE mb_id = :id');
    $stmt->execute($params);
    if ($stmt->rowCount() < 1) {
        if ($imagePath !== null && is_file($imagePath)) @unlink($imagePath);
        jsonResponse(['status' => 'error', 'message' => 'ไม่พบข้อมูลที่ต้องอัปเดต'], 404);
    }
    safeLog('member_updated', ['member_id' => $memberId, 'image_changed' => $imageFilename !== null, 'password_changed' => $newPassword !== '']);
    jsonResponse(['status' => 'success', 'message' => 'อัปเดตข้อมูลเรียบร้อย', 'mb_img' => $imageFilename]);
} catch (PDOException $e) {
    if ($imagePath !== null && is_file($imagePath)) @unlink($imagePath);
    safeLog('member_update_failed', ['member_id' => $memberId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล'], 500);
}
