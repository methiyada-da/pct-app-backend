<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('GET, OPTIONS');
handlePreflight();
requireMethod('GET');
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);

try {
    $stmt = $pdo->prepare('SELECT c.conv_id, c.user_a, c.user_b,
        CASE WHEN c.user_a = :me1 THEN c.user_b ELSE c.user_a END AS other_id,
        m.mb_full_name AS other_name, m.mb_img AS other_img,
        (SELECT msg_text FROM messages WHERE conv_id = c.conv_id ORDER BY created_at DESC, msg_id DESC LIMIT 1) AS last_msg,
        (SELECT created_at FROM messages WHERE conv_id = c.conv_id ORDER BY created_at DESC, msg_id DESC LIMIT 1) AS last_time,
        (SELECT COUNT(*) FROM messages WHERE conv_id = c.conv_id AND sender_id != :me2 AND is_read = 0) AS unread_count
        FROM conversation c
        LEFT JOIN member m ON m.mb_id = CASE WHEN c.user_a = :me3 THEN c.user_b ELSE c.user_a END
        WHERE c.user_a = :me4 OR c.user_b = :me5
        ORDER BY last_time DESC, c.created_at DESC');
    $stmt->execute([':me1' => $memberId, ':me2' => $memberId, ':me3' => $memberId, ':me4' => $memberId, ':me5' => $memberId]);
    jsonResponse(['status' => 'success', 'conversations' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    safeLog('get_conversations_failed', ['member_id' => $memberId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการโหลดการสนทนา'], 500);
}
