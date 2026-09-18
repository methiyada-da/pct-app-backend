<?php
require_once __DIR__ . '/helper.php';

$dbHost = envValue('DB_HOST');
$dbPort = envValue('DB_PORT');
$dbName = envValue('DB_NAME');
$dbUser = envValue('DB_USER');
$dbPassword = envValue('DB_PASSWORD', '');

if ($dbHost === null || $dbHost === '' || $dbPort === null || !ctype_digit($dbPort)
    || $dbName === null || $dbName === '' || $dbUser === null || $dbUser === '') {
    safeLog('database_configuration_missing');
    jsonResponse(['status' => 'error', 'message' => 'การตั้งค่าฐานข้อมูลไม่ครบถ้วน'], 500);
}

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
} catch (PDOException $e) {
    safeLog('database_connection_failed');
    jsonResponse(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้'], 500);
}
