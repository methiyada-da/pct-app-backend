<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);
$conversationId = filter_var($data['conv_id'] ?? null, FILTER_VALIDATE_INT);
$message = trim((string)($data['msg_text'] ?? ''));
if ($conversationId === false || $conversationId < 1 || $message === '' || mb_strlen($message) > 2000) {
    jsonResponse(['status' => 'error', 'message' => 'ข้อความไม่ถูกต้องหรือยาวเกิน 2,000 ตัวอักษร'], 400);
}

try {
    $access = $pdo->prepare('SELECT conv_id FROM conversation WHERE conv_id = :id AND (user_a = :member OR user_b = :member2)');
    $access->execute([':id' => $conversationId, ':member' => $memberId, ':member2' => $memberId]);
    if (!$access->fetchColumn()) jsonResponse(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ส่งข้อความในห้องนี้'], 403);
    $stmt = $pdo->prepare('INSERT INTO messages (conv_id, sender_id, msg_text) VALUES (:conversation, :sender, :message)');
    $stmt->execute([':conversation' => $conversationId, ':sender' => $memberId, ':message' => $message]);
    jsonResponse(['status' => 'success', 'msg_id' => (int)$pdo->lastInsertId()], 201);
} catch (PDOException $e) {
    safeLog('send_message_failed', ['member_id' => $memberId, 'conversation_id' => $conversationId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการส่งข้อความ'], 500);
}
