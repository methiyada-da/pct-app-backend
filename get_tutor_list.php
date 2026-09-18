<?php
require_once __DIR__ . '/helper.php';
setCorsHeaders('POST, OPTIONS');
handlePreflight();
requireMethod('POST');

$json_data = getJsonBody();
$search = trim((string)($json_data['search_name'] ?? ''));
$cg_id = trim((string)($json_data['cg_id'] ?? ''));
$crs_id = trim((string)($json_data['crs_id'] ?? ''));
$min_price = filter_var($json_data['min_price'] ?? 0, FILTER_VALIDATE_FLOAT);
$max_price = filter_var($json_data['max_price'] ?? 0, FILTER_VALIDATE_FLOAT);
if ($min_price === false || $max_price === false || $min_price < 0 || $max_price < 0 || ($max_price > 0 && $min_price > $max_price)) {
    jsonResponse(['status' => 'error', 'message' => 'ช่วงราคาไม่ถูกต้อง'], 400);
}

require_once __DIR__ . '/config.php';

try {
    $pdo->exec("SET NAMES utf8mb4");

    // ใช้ INNER JOIN tutor_course เสมอ เพื่อดึงเฉพาะติวเตอร์ที่มีคอร์ส active
    // และรองรับ filter ราคา/รายวิชาได้โดยตรงโดยไม่ต้องเปลี่ยน JOIN
    $strSQL = "SELECT 
                t.tut_id,
                m.mb_id,
                t.tut_desc,
                t.tut_skill,
                t.tut_gpax,
                m.mb_full_name,
                m.mb_img,
                tc.tutc_id,
                tc.tutc_name,
                tc.tutc_desc,
                tc.tutc_price,
                c.crs_name
               FROM tutor t
               LEFT JOIN member m ON t.tut_id = m.mb_id
               INNER JOIN tutor_course tc ON tc.tut_id = t.tut_id AND tc.tutc_status = 1
               LEFT JOIN course c ON tc.crs_id = c.crs_id
               LEFT JOIN course_group cg ON c.cg_id = cg.cg_id";

    $strSQL .= " WHERE t.tut_status = 1";

    if ($search !== "") {
        $strSQL .= " AND (
            m.mb_full_name LIKE :search1 OR
            t.tut_skill    LIKE :search2 OR
            tc.tutc_name   LIKE :search3 OR
            c.crs_name     LIKE :search4 OR
            cg.cg_name     LIKE :search5
        )";
    }
    if ($cg_id !== '') {
        $strSQL .= " AND c.cg_id = :cg_id";
    }
    if ($crs_id !== '') {
        $strSQL .= " AND tc.crs_id = :crs_id";
    }
    if ($max_price > 0) {
        $strSQL .= " AND tc.tutc_price BETWEEN :min_price AND :max_price";
    }

    // ไม่ต้อง GROUP BY แล้ว เพราะต้องการแสดงแยกตามคอร์ส
    $stmt = $pdo->prepare($strSQL);

    if ($search !== "") {
        $stmt->bindValue(':search1', '%' . $search . '%');
        $stmt->bindValue(':search2', '%' . $search . '%');
        $stmt->bindValue(':search3', '%' . $search . '%');
        $stmt->bindValue(':search4', '%' . $search . '%');
        $stmt->bindValue(':search5', '%' . $search . '%');
    }
    if ($cg_id !== '') {
        $stmt->bindValue(':cg_id', $cg_id);
    }
    if ($crs_id !== '') {
        $stmt->bindValue(':crs_id', $crs_id);
    }
    if ($max_price > 0) {
        $stmt->bindValue(':min_price', $min_price);
        $stmt->bindValue(':max_price', $max_price);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(['status' => 'success', 'datalist' => $rows]);
} catch (PDOException $e) {
    safeLog('get_tutor_list_failed', ['error_code' => $e->getCode()]);
    jsonResponse(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการค้นหาติวเตอร์'], 500);
}
