<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');
$data = getJsonBody();
require_once __DIR__ . '/config.php';
$memberId = authenticatedMemberId($pdo);
$otherId = trim((string)($data['user_b'] ?? ''));
if ($otherId === '' || hash_equals($memberId, $otherId)) jsonResponse(['status' => 'error', 'message' => 'คู่สนทนาไม่ถูกต้อง'], 400);

try {
    $memberStmt = $pdo->prepare('SELECT mb_id FROM member WHERE mb_id = :id');
    $memberStmt->execute([':id' => $otherId]);
    if (!$memberStmt->fetchColumn()) jsonResponse(['status' => 'error', 'message' => 'ไม่พบคู่สนทนา'], 404);

    $find = $pdo->prepare('SELECT conv_id FROM conversation WHERE (user_a = :a1 AND user_b = :b1) OR (user_a = :b2 AND user_b = :a2) LIMIT 1');
    $find->execute([':a1' => $memberId, ':b1' => $otherId, ':a2' => $memberId, ':b2' => $otherId]);
    $conversationId = $find->fetchColumn();
    if (!$conversationId) {
        [$userA, $userB] = strcmp($memberId, $otherId) < 0 ? [$memberId, $otherId] : [$otherId, $memberId];
        $insert = $pdo->prepare('INSERT INTO conversation (user_a, user_b) VALUES (:a, :b)');
        $insert->execute([':a' => $userA, ':b' => $userB]);
        $conversationId = $pdo->lastInsertId();
    }
    jsonResponse(['status' => 'success', 'conv_id' => (int)$conversationId]);
} catch (PDOException $e) {
    safeLog('conversation_create_failed', ['member_id' => $memberId, 'error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเปิดการสนทนา'], 500);
}
