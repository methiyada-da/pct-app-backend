<?php

function loadEnvFile(string $path): void {
    if (!is_file($path)) return;
    $values = parse_ini_file($path, false, INI_SCANNER_RAW);
    if ($values === false) return;
    foreach ($values as $key => $value) {
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}

function configuredEnvFilePath(): ?string {
    $externalPath = getenv('MINI_BACKEND_ENV_FILE');
    if ($externalPath !== false && trim($externalPath) !== '') {
        return trim($externalPath);
    }

    $localPath = __DIR__ . '/.env';
    return is_file($localPath) ? $localPath : null;
}

$envFilePath = configuredEnvFilePath();
if ($envFilePath !== null) loadEnvFile($envFilePath);

function envValue(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function jsonResponse(array $body, int $status = 200): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function safeLog(string $event, array $context = []): void {
    $blocked = ['password', 'mb_pwd', 'cf_pwd', 'old_pwd', 'token', 'authorization', 'mb_img'];
    foreach ($blocked as $key) {
        if (array_key_exists($key, $context)) $context[$key] = '[REDACTED]';
    }
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0750, true);
    $entry = ['time' => date(DATE_ATOM), 'event' => $event, 'context' => $context];
    @file_put_contents($logDir . '/' . date('Y-m-d') . '.log', json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function _log($message, string $subdir = ''): void {
    safeLog($subdir !== '' ? $subdir : 'application', ['message' => is_string($message) ? $message : '[structured data]']);
}

function allowedOrigins(): array {
    $raw = envValue('ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5000,http://localhost:8080,http://127.0.0.1:3000,http://127.0.0.1:5000,http://127.0.0.1:8080');
    return array_values(array_filter(array_map('trim', explode(',', (string)$raw))));
}

function setCorsHeaders(string $methods = 'POST, GET, OPTIONS'): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, allowedOrigins(), true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Vary: Origin');
    }
    header("Access-Control-Allow-Methods: $methods");
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Accept, Authorization');
    header('Content-Type: application/json; charset=utf-8');
}

function handlePreflight(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' || $_SERVER['REQUEST_METHOD'] === 'HEAD') {
        http_response_code(204);
        exit;
    }
}

function requireMethod(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        jsonResponse(['status' => 'error', 'message' => 'Method Not Allowed'], 405);
    }
}

function getJsonBody(): array {
    $content = file_get_contents('php://input');
    if ($content === false || trim($content) === '') return [];
    $decoded = json_decode($content, true);
    if (!is_array($decoded)) jsonResponse(['status' => 'error', 'message' => 'รูปแบบ JSON ไม่ถูกต้อง'], 400);
    return $decoded;
}

function base64UrlEncode(string $value): string {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64UrlDecode(string $value): string|false {
    $padding = strlen($value) % 4;
    if ($padding > 0) $value .= str_repeat('=', 4 - $padding);
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function authSecret(): string {
    $configured = envValue('AUTH_SECRET');
    if ($configured === null || strlen($configured) < 32) {
        throw new RuntimeException('Authentication configuration is unavailable');
    }
    return $configured;
}

function requireAuthSecretConfigured(): string {
    try {
        return authSecret();
    } catch (Throwable $error) {
        safeLog('auth_configuration_invalid');
        jsonResponse(['status' => 'error', 'message' => 'ระบบยืนยันตัวตนยังไม่พร้อมใช้งาน'], 500);
    }
}

function createAuthToken(string $memberId, int $ttlSeconds = 28800, ?string $secret = null): string {
    $secret ??= authSecret();
    $payload = base64UrlEncode(json_encode([
        'sub' => $memberId,
        'exp' => time() + $ttlSeconds,
        'nonce' => bin2hex(random_bytes(8)),
    ], JSON_UNESCAPED_SLASHES));
    $signature = base64UrlEncode(hash_hmac('sha256', $payload, $secret, true));
    return $payload . '.' . $signature;
}

function verifyAuthToken(string $token, string $secret, ?int $now = null): string {
    $parts = explode('.', $token);
    if (count($parts) !== 2) throw new UnexpectedValueException('Invalid token format');

    [$payloadPart, $signature] = $parts;
    $expected = base64UrlEncode(hash_hmac('sha256', $payloadPart, $secret, true));
    if (!hash_equals($expected, $signature)) throw new UnexpectedValueException('Invalid token signature');

    $payloadJson = base64UrlDecode($payloadPart);
    $payload = $payloadJson === false ? null : json_decode($payloadJson, true);
    if (!is_array($payload) || !is_string($payload['sub'] ?? null) || $payload['sub'] === '') {
        throw new UnexpectedValueException('Invalid token payload');
    }
    $expiresAt = filter_var($payload['exp'] ?? null, FILTER_VALIDATE_INT);
    if ($expiresAt === false || $expiresAt <= ($now ?? time())) {
        throw new UnexpectedValueException('Expired token');
    }
    return $payload['sub'];
}

function bearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    return preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) ? trim($matches[1]) : null;
}

function authenticatedMemberId(PDO $pdo): string {
    $token = bearerToken();
    if ($token === null) jsonResponse(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
    $secret = requireAuthSecretConfigured();
    try {
        $memberId = verifyAuthToken($token, $secret);
    } catch (Throwable $error) {
        jsonResponse(['status' => 'error', 'message' => 'Token หมดอายุหรือไม่ถูกต้อง'], 401);
    }
    $stmt = $pdo->prepare('SELECT mb_id FROM member WHERE mb_id = :id');
    $stmt->execute([':id' => $memberId]);
    if (!$stmt->fetchColumn()) jsonResponse(['status' => 'error', 'message' => 'ไม่พบผู้ใช้'], 401);
    return $memberId;
}

function normalizeSchedules($schedules, bool $required): array {
    if (!is_array($schedules) || ($required && count($schedules) === 0)) {
        throw new InvalidArgumentException('กรุณาระบุวันและเวลาสอนอย่างน้อย 1 รายการ');
    }
    $validated = [];
    foreach ($schedules as $schedule) {
        if (!is_array($schedule)) {
            throw new InvalidArgumentException('รูปแบบตารางสอนไม่ถูกต้อง');
        }
        $day = filter_var($schedule['sch_day'] ?? null, FILTER_VALIDATE_INT);
        $start = (string)($schedule['sch_start'] ?? '');
        $end = (string)($schedule['sch_end'] ?? '');
        if ($day === false || $day < 1 || $day > 7 || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end) || $end <= $start) {
            throw new InvalidArgumentException('วันหรือช่วงเวลาสอนไม่ถูกต้อง');
        }
        $validated[] = ['sch_day' => $day, 'sch_start' => $start, 'sch_end' => $end];
    }
    return $validated;
}

function validateSchedules($schedules, bool $required): array {
    try {
        return normalizeSchedules($schedules, $required);
    } catch (InvalidArgumentException $error) {
        jsonResponse(['status' => 'error', 'message' => $error->getMessage()], 400);
    }
}
