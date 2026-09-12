<?php
/**
 * Same-origin Chat Completions proxy for static Hostinger hosting.
 * Forwards the browser Authorization header. Does not store the API key.
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
if (!preg_match('/^Bearer\s+(\S+)/i', $auth, $matches)) {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Missing API key. Add it in Settings.']]);
    exit;
}
$apiKey = $matches[1];

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

$base = trim((string)($payload['baseURL'] ?? 'https://api.openai.com/v1'));
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
    ($host === 'api.openai.com' || str_ends_with($host, '.openai.com'))
    && !str_contains($parts['path'] ?? '', 'v1')
) {
    $base .= '/v1';
}

$endpoint = $base . '/chat/completions';
$model = trim((string)($payload['model'] ?? 'gpt-4o-mini'));
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
