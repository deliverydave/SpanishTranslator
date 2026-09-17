<?php
if (!defined('DELIVERYDAVE_SMS')) {
    http_response_code(403);
    exit('Forbidden');
}

function sms_twilio_compute_signature(string $authToken, string $url, array $params): string
{
    ksort($params);
    $data = $url;
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            continue;
        }
        $data .= $key . $value;
    }
    return base64_encode(hash_hmac('sha1', $data, $authToken, true));
}

function sms_request_url(array $server, string $configured = ''): string
{
    if ($configured !== '') {
        return $configured;
    }
    $forwarded = strtolower((string)($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off')
        || $forwarded === 'https'
        || (string)($server['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = (string)($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '');
    $uri = (string)($server['REQUEST_URI'] ?? '/');
    return $scheme . '://' . $host . $uri;
}

/**
 * @return list<string>
 */
function sms_signature_url_candidates(array $server, string $configured): array
{
    $candidates = [];
    $add = static function (string $url) use (&$candidates): void {
        $url = trim($url);
        if ($url === '') {
            return;
        }
        $candidates[] = $url;
        $stripped = preg_replace('/:443(?=\/|$)/', '', $url) ?? $url;
        $candidates[] = $stripped;
        if (str_ends_with($url, '/')) {
            $candidates[] = rtrim($url, '/');
        } else {
            $candidates[] = $url . '/';
        }
    };

    $add(sms_request_url($server, $configured));
    $add(sms_request_url($server, ''));
    return array_values(array_unique($candidates));
}

function sms_validate_twilio_signature(string $authToken, string $signature, string $url, array $params): bool
{
    if ($authToken === '' || $signature === '') {
        return false;
    }
    $expected = sms_twilio_compute_signature($authToken, $url, $params);
    return hash_equals($expected, $signature);
}

function sms_request_is_from_twilio(array $config, array $server, array $params): bool
{
    if (empty($config['validateSignature'])) {
        return true;
    }
    $signature = (string)($server['HTTP_X_TWILIO_SIGNATURE'] ?? '');
    foreach (sms_signature_url_candidates($server, (string)$config['webhookUrl']) as $url) {
        if (sms_validate_twilio_signature((string)$config['authToken'], $signature, $url, $params)) {
            return true;
        }
    }
    return false;
}

function sms_empty_twiml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
}

/**
 * @return array{ok: bool, status: int, error: string, sid: string}
 */
function sms_send(array $config, string $to, string $body): array
{
    $to = sms_e164($to);
    $body = trim($body);
    if ($to === '' || $body === '') {
        return ['ok' => false, 'status' => 0, 'error' => 'missing to/body', 'sid' => ''];
    }

    if (!empty($config['dryRun'])) {
        $outbox = (string)($config['outboxFile'] ?? '');
        if ($outbox !== '') {
            $rows = [];
            if (is_readable($outbox)) {
                $decoded = json_decode((string)file_get_contents($outbox), true);
                if (is_array($decoded)) {
                    $rows = $decoded;
                }
            }
            $rows[] = [
                'to' => $to,
                'body' => $body,
                'at' => gmdate('c'),
            ];
            file_put_contents($outbox, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return ['ok' => true, 'status' => 200, 'error' => '', 'sid' => 'SM-dry-run'];
    }

    $sid = (string)$config['accountSid'];
    $token = (string)$config['authToken'];
    $endpoint = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
    $fields = ['To' => $to, 'Body' => $body];
    $msid = trim((string)($config['messagingServiceSid'] ?? ''));
    if ($msid !== '') {
        $fields['MessagingServiceSid'] = $msid;
    } else {
        $fields['From'] = (string)$config['twilioNumber'];
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('DeliveryDave SMS send failed: ' . $err);
        return ['ok' => false, 'status' => $status, 'error' => $err ?: 'curl failed', 'sid' => ''];
    }
    $parsed = json_decode($response, true);
    $messageSid = is_array($parsed) ? (string)($parsed['sid'] ?? '') : '';
    if ($status < 200 || $status >= 300) {
        $message = is_array($parsed) ? (string)($parsed['message'] ?? $response) : (string)$response;
        error_log('DeliveryDave SMS send HTTP ' . $status . ': ' . $message);
        return ['ok' => false, 'status' => $status, 'error' => $message, 'sid' => $messageSid];
    }
    return ['ok' => true, 'status' => $status, 'error' => '', 'sid' => $messageSid];
}
