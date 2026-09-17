<?php
if (!defined('DELIVERYDAVE_SMS')) {
    http_response_code(403);
    exit('Forbidden');
}

function sms_optout_path(array $config): string
{
    if (!empty($config['optoutFile']) && is_string($config['optoutFile'])) {
        return $config['optoutFile'];
    }
    return dirname(__DIR__) . '/data/optouts.json';
}

/**
 * @return array<string, array<string, mixed>>
 */
function sms_optout_read(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    if (isset($decoded['numbers']) && is_array($decoded['numbers'])) {
        return $decoded['numbers'];
    }
    return $decoded;
}

/**
 * @param array<string, array<string, mixed>> $numbers
 */
function sms_optout_write(string $path, array $numbers): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $payload = json_encode(
        ['numbers' => $numbers, 'updatedAt' => gmdate('c')],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    );
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        error_log('DeliveryDave SMS: cannot open opt-out file');
        return;
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            error_log('DeliveryDave SMS: cannot lock opt-out file');
            return;
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $payload === false ? '{"numbers":{}}' : $payload);
        fflush($fh);
        flock($fh, LOCK_UN);
    } finally {
        fclose($fh);
    }
}

function sms_is_opted_out(array $config, string $phone): bool
{
    $e164 = sms_e164($phone);
    if ($e164 === '') {
        return false;
    }
    $numbers = sms_optout_read(sms_optout_path($config));
    return !empty($numbers[$e164]['optedOut']);
}

function sms_set_opted_out(array $config, string $phone, bool $optedOut): void
{
    $e164 = sms_e164($phone);
    if ($e164 === '') {
        return;
    }
    $path = sms_optout_path($config);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        error_log('DeliveryDave SMS: cannot open opt-out file');
        return;
    }
    try {
        flock($fh, LOCK_EX);
        rewind($fh);
        $raw = stream_get_contents($fh);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        $numbers = [];
        if (is_array($decoded)) {
            $numbers = isset($decoded['numbers']) && is_array($decoded['numbers'])
                ? $decoded['numbers']
                : $decoded;
        }
        $row = is_array($numbers[$e164] ?? null) ? $numbers[$e164] : [];
        $row['optedOut'] = $optedOut;
        $row['updatedAt'] = gmdate('c');
        $numbers[$e164] = $row;
        $payload = json_encode(
            ['numbers' => $numbers, 'updatedAt' => gmdate('c')],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $payload === false ? '{"numbers":{}}' : $payload);
        fflush($fh);
        flock($fh, LOCK_UN);
    } finally {
        fclose($fh);
    }
}

function sms_unknown_reply_recent(array $config, string $phone, int $windowSeconds = 86400): bool
{
    $e164 = sms_e164($phone);
    if ($e164 === '') {
        return false;
    }
    $numbers = sms_optout_read(sms_optout_path($config));
    $stamp = $numbers[$e164]['lastUnknownReplyAt'] ?? '';
    if (!is_string($stamp) || $stamp === '') {
        return false;
    }
    $then = strtotime($stamp);
    if ($then === false) {
        return false;
    }
    return (time() - $then) < $windowSeconds;
}

function sms_mark_unknown_reply(array $config, string $phone): void
{
    $e164 = sms_e164($phone);
    if ($e164 === '') {
        return;
    }
    $path = sms_optout_path($config);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        return;
    }
    try {
        flock($fh, LOCK_EX);
        rewind($fh);
        $raw = stream_get_contents($fh);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        $numbers = [];
        if (is_array($decoded)) {
            $numbers = isset($decoded['numbers']) && is_array($decoded['numbers'])
                ? $decoded['numbers']
                : $decoded;
        }
        $row = is_array($numbers[$e164] ?? null) ? $numbers[$e164] : [];
        $row['lastUnknownReplyAt'] = gmdate('c');
        $row['updatedAt'] = gmdate('c');
        $numbers[$e164] = $row;
        $payload = json_encode(
            ['numbers' => $numbers, 'updatedAt' => gmdate('c')],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $payload === false ? '{"numbers":{}}' : $payload);
        fflush($fh);
        flock($fh, LOCK_UN);
    } finally {
        fclose($fh);
    }
}
