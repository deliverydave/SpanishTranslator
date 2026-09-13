<?php
/**
 * Same-origin Chat Completions proxy for Hostinger.
 * Uses a browser Authorization header if present, otherwise api/config.local.php.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'POST only']]);
    exit;
}

$auth = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';
$apiKey = '';
if (preg_match('/^Bearer\s+(\S+)/i', $auth, $matches)) {
    $apiKey = $matches[1];
}
if ($apiKey === '') {
    $apiKey = load_server_api_key();
}
if ($apiKey === '') {
    http_response_code(401);
    echo json_encode([
        'error' => [
            'message' => 'No API key on the server. Upload api/config.local.php next to chat.php (copy from config.local.php.example).',
        ],
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Invalid JSON body']]);
    exit;
}

$messages = $payload['messages'] ?? null;
if (!is_array($messages) || count($messages) === 0) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'messages are required']]);
    exit;
}

$defaultBase = 'https://api.x.ai/v1';
$defaultModel = 'grok-4.6';
$base = trim((string)($payload['baseURL'] ?? $defaultBase));
$model = trim((string)($payload['model'] ?? $defaultModel));
if ($base === '' || str_contains(strtolower($base), 'openai.com')) {
    $base = $defaultBase;
}
if ($model === '' || $model === 'gpt-4o-mini' || str_starts_with($model, 'gpt-3')) {
    $model = $defaultModel;
}

$base = rtrim($base, '/');
if (str_ends_with($base, '/chat/completions')) {
    $base = substr($base, 0, -strlen('/chat/completions'));
}
$base = rtrim($base, '/');
if (!str_contains($base, '://')) {
    $base = 'https://' . $base;
}

$parts = parse_url($base);
$host = strtolower($parts['host'] ?? '');
$scheme = $parts['scheme'] ?? '';
if ($scheme !== 'https' || $host === '') {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'The API base URL must use https.']]);
    exit;
}

$blocked = (
    $host === 'localhost'
    || str_ends_with($host, '.localhost')
    || $host === '127.0.0.1'
    || $host === '0.0.0.0'
    || $host === '::1'
    || str_ends_with($host, '.local')
    || str_ends_with($host, '.internal')
);
if ($blocked || is_private_ipv4($host)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'That API host is not allowed.']]);
    exit;
}

if (
    ($host === 'api.openai.com' || $host === 'api.x.ai' || str_ends_with($host, '.openai.com') || str_ends_with($host, '.x.ai'))
    && !str_contains($parts['path'] ?? '', 'v1')
) {
    $base .= '/v1';
}

$endpoint = $base . '/chat/completions';
$temperature = $payload['temperature'] ?? 0.4;

$body = json_encode([
    'model' => $model,
    'messages' => $messages,
    'temperature' => $temperature,
]);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
]);
$response = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => ['message' => $err ?: 'Upstream request failed']]);
    exit;
}

http_response_code($status ?: 502);
echo $response;

function load_server_api_key(): string
{
    $candidates = [
        __DIR__ . '/config.local.php',
        dirname(__DIR__) . '/config.local.php',
        dirname(__DIR__, 3) . '/config.local.php',
    ];
    foreach ($candidates as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $config = include $file;
        if (is_array($config) && !empty($config['apiKey']) && is_string($config['apiKey'])) {
            $key = trim($config['apiKey']);
            if ($key !== '' && !str_contains($key, 'your-key-here')) {
                return $key;
            }
        }
    }
    return '';
}

function is_private_ipv4(string $host): bool
{
    if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    $parts = array_map('intval', explode('.', $host));
    $a = $parts[0];
    $b = $parts[1];
    return $a === 10
        || $a === 127
        || ($a === 192 && $b === 168)
        || ($a === 172 && $b >= 16 && $b <= 31)
        || ($a === 169 && $b === 254);
}
