<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);
$conversationId = filter_var($data['conv_id'] ?? null, FILTER_VALIDATE_INT);
if ($conversationId === false || $conversationId < 1) jsonResponse(['status' => 'error', 'message' => 'รหัสการสนทนาไม่ถูกต้อง'], 400);

try {
    $access = $pdo->prepare('SELECT conv_id FROM conversation WHERE conv_id = :id AND (user_a = :member OR user_b = :member2)');
    $access->execute([':id' => $conversationId, ':member' => $memberId, ':member2' => $memberId]);
    if (!$access->fetchColumn()) jsonResponse(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึงการสนทนานี้'], 403);
    $pdo->prepare('UPDATE messages SET is_read = 1 WHERE conv_id = :id AND sender_id != :member AND is_read = 0')
        ->execute([':id' => $conversationId, ':member' => $memberId]);
    $stmt = $pdo->prepare('SELECT msg_id, conv_id, sender_id, msg_text, is_read, created_at FROM messages WHERE conv_id = :id ORDER BY created_at, msg_id');
    $stmt->execute([':id' => $conversationId]);
    jsonResponse(['status' => 'success', 'messages' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    safeLog('get_messages_failed', ['member_id' => $memberId, 'conversation_id' => $conversationId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการโหลดข้อความ'], 500);
}
