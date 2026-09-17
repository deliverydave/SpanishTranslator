<?php
/**
 * Consent-form enrollment for the DeliveryDave bilingual SMS pair.
 * Public POST from https://deliverydave.ai/sms-consent/ (CORS).
 * Writes data/pair.json — Twilio/xAI secrets stay in config.local.php.
 */
define('DELIVERYDAVE_SMS', true);
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Vary: Origin');

$origin = sms_enroll_cors_origin($_SERVER);
if ($origin !== null) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$raw = file_get_contents('php://input');
if (!is_string($raw)) {
    $raw = '';
}
if (strlen($raw) > 16384) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Request too large']);
    exit;
}

$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (str_contains(strtolower($contentType), 'json') && $raw !== '' && json_decode($raw, true) === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$input = sms_enroll_parse_body($raw, $_POST, $contentType);
$config = sms_load_config();
$result = sms_enroll_handle($input, $config, $_SERVER);
http_response_code($result['status']);
echo json_encode($result['body'], JSON_UNESCAPED_SLASHES);
