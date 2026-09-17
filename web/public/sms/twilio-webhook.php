<?php
/**
 * Twilio inbound webhook for the DeliveryDave bilingual jobsite SMS bridge.
 * Configure this URL on the DeliveryDave Messaging Service (POST).
 */
define('DELIVERYDAVE_SMS', true);
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/xml; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'HEAD') {
    echo sms_empty_twiml();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo sms_empty_twiml();
    exit;
}

$config = sms_load_config();
$errors = sms_config_errors($config);
if ($errors !== []) {
    error_log('DeliveryDave SMS is missing config: ' . implode(', ', $errors));
    http_response_code(500);
    echo sms_empty_twiml();
    exit;
}

if (!sms_request_is_from_twilio($config, $_SERVER, $_POST)) {
    http_response_code(403);
    echo sms_empty_twiml();
    exit;
}

ignore_user_abort(true);
@set_time_limit(120);
http_response_code(200);
echo sms_empty_twiml();
sms_finish_http_response();

$send = static function (string $to, string $body) use ($config): array {
    return sms_send($config, $to, $body);
};
$translate = static function (string $text, string $sourceLang, string $targetLang) use ($config): string {
    return sms_translate($config, $text, $sourceLang, $targetLang);
};

try {
    sms_handle_inbound($_POST, $config, $send, $translate);
} catch (Throwable $e) {
    error_log('DeliveryDave SMS webhook error: ' . $e->getMessage());
}
