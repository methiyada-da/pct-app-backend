<?php

putenv('AUTH_SECRET=' . str_repeat('x', 32));
require_once dirname(__DIR__) . '/helper.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function checkThrows(callable $operation, string $exceptionClass, string $message): void {
    try {
        $operation();
    } catch (Throwable $error) {
        check($error instanceof $exceptionClass, $message . ' (wrong exception type)');
        return;
    }
    throw new RuntimeException($message);
}

if (($argv[1] ?? '') === '--malformed-schedule-probe') {
    register_shutdown_function(static function (): void {
        echo "\nHTTP_STATUS=" . http_response_code() . "\n";
    });
    validateSchedules(['not-an-object'], false);
}

$password = 'portfolio-test-password';
$hash = password_hash($password, PASSWORD_DEFAULT);
check($hash !== $password, 'Registration password must be hashed.');
check(password_verify($password, $hash), 'Login must verify a valid password hash.');
check(!password_verify('wrong-password', $hash), 'Login must reject an invalid password.');

$testSecret = str_repeat('x', 32);
$token = createAuthToken('MB00000000001', 60, $testSecret);
check(substr_count($token, '.') === 1, 'Authentication token must be signed.');
check(verifyAuthToken($token, $testSecret) === 'MB00000000001', 'A valid signed token must verify.');

putenv('AUTH_SECRET');
checkThrows(fn() => authSecret(), RuntimeException::class, 'Missing AUTH_SECRET must fail closed.');
putenv('AUTH_SECRET=short');
checkThrows(fn() => authSecret(), RuntimeException::class, 'Short AUTH_SECRET must fail closed.');
putenv('AUTH_SECRET=' . $testSecret);

[$payloadPart, $signaturePart] = explode('.', $token);
$forgedPayload = base64UrlEncode(json_encode(['sub' => 'MB99999999999', 'exp' => time() + 60]));
checkThrows(
    fn() => verifyAuthToken($forgedPayload . '.' . $signaturePart, $testSecret),
    UnexpectedValueException::class,
    'A forged token payload must be rejected.'
);
$invalidSignature = substr($signaturePart, 0, -1) . ($signaturePart[-1] === 'A' ? 'B' : 'A');
checkThrows(
    fn() => verifyAuthToken($payloadPart . '.' . $invalidSignature, $testSecret),
    UnexpectedValueException::class,
    'An invalid token signature must be rejected.'
);
$expiredToken = createAuthToken('MB00000000001', -1, $testSecret);
checkThrows(
    fn() => verifyAuthToken($expiredToken, $testSecret),
    UnexpectedValueException::class,
    'An expired token must be rejected.'
);

checkThrows(
    fn() => normalizeSchedules(['not-an-object'], false),
    InvalidArgumentException::class,
    'A malformed schedule member must be rejected without a TypeError.'
);
$probeOutput = [];
$probeExit = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --malformed-schedule-probe', $probeOutput, $probeExit);
check($probeExit === 0 && str_contains(implode("\n", $probeOutput), 'HTTP_STATUS=400'), 'Malformed schedules must return HTTP 400.');

$root = dirname(__DIR__);
$registration = file_get_contents($root . '/create_member.php');
$login = file_get_contents($root . '/login.php');
check(str_contains($registration, 'password_hash('), 'Registration must call password_hash.');
check(str_contains($login, 'password_verify('), 'Login must call password_verify.');
check(str_contains($login, "unset(\$row['mb_pwd'])"), 'Login response must remove mb_pwd.');
$registrationSecret = strpos($registration, 'requireAuthSecretConfigured()');
$registrationTransaction = strpos($registration, 'beginTransaction()');
$loginSecret = strpos($login, 'requireAuthSecretConfigured()');
$loginMigration = strpos($login, 'UPDATE member SET mb_pwd');
check($registrationSecret !== false && $registrationTransaction !== false && $registrationSecret < $registrationTransaction, 'Registration must validate AUTH_SECRET before its transaction.');
check($loginSecret !== false && $loginMigration !== false && $loginSecret < $loginMigration, 'Login must validate AUTH_SECRET before password migration.');

$helperSource = file_get_contents($root . '/helper.php');
check(!str_contains($helperSource, '.auth_secret'), 'The application must not create or read a webroot .auth_secret fallback.');
check(str_contains($helperSource, "jsonResponse(['status' => 'error', 'message' => \$error->getMessage()], 400)"), 'Malformed schedules must map to HTTP 400.');

$redactedContext = redactSensitiveContext([
    'Password' => 'plain-text-password',
    'profile' => [
        'api_key' => 'private-api-key',
        'display_name' => 'Test User',
    ],
]);
check($redactedContext['Password'] === '[REDACTED]', 'Log redaction must be case-insensitive.');
check($redactedContext['profile']['api_key'] === '[REDACTED]', 'Log redaction must cover nested sensitive values.');
check($redactedContext['profile']['display_name'] === 'Test User', 'Log redaction must preserve safe values.');

$protected = [
    'get_user.php', 'update_member.php', 'create_tutor.php',
    'create_tutor_course.php', 'update_tutor_course.php',
    'toggle_course_status.php', 'delete_tutor_course.php',
    'get_conversations.php', 'get_messages.php',
    'get_or_create_conversation.php', 'send_message.php',
];
foreach ($protected as $file) {
    $source = file_get_contents($root . '/' . $file);
    check(str_contains($source, 'authenticatedMemberId($pdo)'), "$file must authenticate the caller.");
}

foreach (['update_tutor_course.php', 'toggle_course_status.php', 'delete_tutor_course.php'] as $file) {
    $source = file_get_contents($root . '/' . $file);
    check(str_contains($source, 'tut_id'), "$file must enforce course ownership.");
}

$courseSource = file_get_contents($root . '/get_tutor_courses.php');
check(str_contains($courseSource, "\$course['schedules']"), 'Course API must preserve day/time schedule relationships.');
check(!str_contains($courseSource, "\$course['schedule_days']"), 'Course API must not split schedule days from time slots.');

echo "Backend security smoke tests passed.\n";
