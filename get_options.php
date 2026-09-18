<?php
// --- 1. HELPERS ---
require_once 'helper.php';

setCorsHeaders('GET, OPTIONS');
handlePreflight();
requireMethod('GET');

// --- 4. DATABASE LOGIC ---
require_once 'config.php';

try {
    // ดึงข้อมูลคณะ (fac_id, fac_name)
    $stmtFac = $pdo->prepare("SELECT fac_id, fac_name FROM faculty ORDER BY fac_id ASC");
    $stmtFac->execute();
    $faculties = $stmtFac->fetchAll();

    // ดึงข้อมูลสาขา (mj_id, mj_name, fac_id)
    $stmtMj = $pdo->prepare("SELECT mj_id, mj_name, fac_id FROM major ORDER BY mj_id ASC");
    $stmtMj->execute();
    $majors = $stmtMj->fetchAll();

    // บันทึก Log เมื่อมีการดึงข้อมูลสำเร็จ
    // 5. ตอบกลับเป็น JSON
    echo json_encode([
        "status" => "success",
        "faculties" => $faculties,
        "majors" => $majors
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    safeLog('get_options_failed', ['error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการโหลดตัวเลือก'], 500);
}
?>
