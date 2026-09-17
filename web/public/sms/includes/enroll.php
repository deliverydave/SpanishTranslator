<?php
if (!defined('DELIVERYDAVE_SMS')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * @return list<string>
 */
function sms_enroll_allowed_origins(): array
{
    return [
        'https://deliverydave.ai',
        'https://www.deliverydave.ai',
        'https://translate.deliverydave.ai',
    ];
}

function sms_enroll_cors_origin(array $server): ?string
{
    $origin = trim((string)($server['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') {
        return null;
    }
    return in_array($origin, sms_enroll_allowed_origins(), true) ? $origin : null;
}

function sms_enroll_client_ip(array $server): string
{
    $forwarded = (string)($server['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwarded !== '') {
        $first = trim(explode(',', $forwarded)[0]);
        if ($first !== '') {
            return $first;
        }
    }
    $addr = trim((string)($server['REMOTE_ADDR'] ?? ''));
    return $addr !== '' ? $addr : '0.0.0.0';
}

function sms_enroll_consent_given(mixed $value): bool
{
    if ($value === true || $value === 1) {
        return true;
    }
    if (is_string($value) || is_int($value) || is_float($value)) {
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on', 'checked'], true);
    }
    return false;
}

function sms_enroll_lang(string $code): string
{
    return sms_lang($code) === 'es' ? 'es' : 'en';
}

function sms_enroll_sanitize_name(string $name, string $fallback): string
{
    $name = trim(strip_tags($name));
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 80);
    } else {
        $name = substr($name, 0, 80);
    }
    $name = trim($name);
    return $name !== '' ? $name : $fallback;
}

/**
 * @param array<string, mixed> $input
 * @return array{owner_name: string, owner_phone: string, owner_lang: string, contact_name: string, contact_phone: string, contact_lang: string, consent: mixed}
 */
function sms_enroll_normalize_input(array $input): array
{
    $owner = is_array($input['owner'] ?? null) ? $input['owner'] : [];
    $contact = is_array($input['contact'] ?? null) ? $input['contact'] : [];
    return [
        'owner_name' => (string)($input['owner_name'] ?? $input['ownerName'] ?? $owner['name'] ?? ''),
        'owner_phone' => (string)($input['owner_phone'] ?? $input['ownerPhone'] ?? $owner['phone'] ?? ''),
        'owner_lang' => (string)($input['owner_language'] ?? $input['owner_lang'] ?? $input['ownerLang'] ?? $owner['lang'] ?? $owner['language'] ?? 'en'),
        'contact_name' => (string)($input['contact_name'] ?? $input['contactName'] ?? $contact['name'] ?? ''),
        'contact_phone' => (string)($input['contact_phone'] ?? $input['contactPhone'] ?? $contact['phone'] ?? ''),
        'contact_lang' => (string)($input['contact_language'] ?? $input['contact_lang'] ?? $input['contactLang'] ?? $contact['lang'] ?? $contact['language'] ?? 'es'),
        'consent' => $input['sms_consent'] ?? $input['consent'] ?? $input['agree'] ?? false,
    ];
}

/**
 * @param array<string, mixed> $input
 * @param array<string, mixed> $config
 * @return array{ok: true, owner: array{name: string, phone: string, lang: string}, contact: array{name: string, phone: string, lang: string}}|array{ok: false, error: string}
 */
function sms_enroll_validate(array $input, array $config = []): array
{
    $norm = sms_enroll_normalize_input($input);
    if (!sms_enroll_consent_given($norm['consent'])) {
        return [
            'ok' => false,
            'error' => 'Check the SMS consent box to enroll, or choose “No thanks” to continue without SMS.',
        ];
    }
    $ownerRaw = trim($norm['owner_phone']);
    $contactRaw = trim($norm['contact_phone']);
    if ($ownerRaw === '' || $contactRaw === '') {
        return [
            'ok' => false,
            'error' => 'To opt in to SMS, enter both mobile numbers (or choose “No thanks” to skip SMS).',
        ];
    }
    if (!sms_e164_valid($ownerRaw)) {
        return [
            'ok' => false,
            'error' => 'Owner mobile number looks invalid. Use a full number (for example +1 555 555 0100).',
        ];
    }
    if (!sms_e164_valid($contactRaw)) {
        return [
            'ok' => false,
            'error' => 'Contact mobile number looks invalid. Use a full number (for example +1 555 555 0199).',
        ];
    }
    $ownerPhone = sms_e164($ownerRaw);
    $contactPhone = sms_e164($contactRaw);
    if (sms_same_number($ownerPhone, $contactPhone)) {
        return [
            'ok' => false,
            'error' => 'Owner and contact need two different mobile numbers.',
        ];
    }
    $twilio = sms_e164((string)($config['twilioNumber'] ?? ''));
    if ($twilio !== '' && (sms_same_number($ownerPhone, $twilio) || sms_same_number($contactPhone, $twilio))) {
        return [
            'ok' => false,
            'error' => 'Use personal mobiles for owner and contact — not the DeliveryDave Twilio number.',
        ];
    }
    return [
        'ok' => true,
        'owner' => [
            'name' => sms_enroll_sanitize_name($norm['owner_name'], 'Dave'),
            'phone' => $ownerPhone,
            'lang' => sms_enroll_lang($norm['owner_lang']),
        ],
        'contact' => [
            'name' => sms_enroll_sanitize_name($norm['contact_name'], 'Luis'),
            'phone' => $contactPhone,
            'lang' => sms_enroll_lang($norm['contact_lang']),
        ],
    ];
}

function sms_enroll_rate_path(array $config): string
{
    if (!empty($config['enrollRateFile']) && is_string($config['enrollRateFile'])) {
        return $config['enrollRateFile'];
    }
    return dirname(__DIR__) . '/data/enroll-rate.json';
}

function sms_enroll_is_rate_limited(array $config, string $ip): bool
{
    $limit = (int)($config['enrollRateLimit'] ?? 8);
    $window = (int)($config['enrollRateWindow'] ?? 900);
    if ($limit <= 0) {
        return false;
    }
    $path = sms_enroll_rate_path($config);
    if (!is_readable($path)) {
        return false;
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    $hits = is_array($decoded) && isset($decoded['hits'][$ip]) && is_array($decoded['hits'][$ip])
        ? $decoded['hits'][$ip]
        : [];
    $cutoff = time() - max(1, $window);
    $recent = 0;
    foreach ($hits as $stamp) {
        if ((int)$stamp >= $cutoff) {
            $recent++;
        }
    }
    return $recent >= $limit;
}

function sms_enroll_record_attempt(array $config, string $ip): void
{
    $limit = (int)($config['enrollRateLimit'] ?? 8);
    if ($limit <= 0) {
        return;
    }
    $path = sms_enroll_rate_path($config);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
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
        $hits = [];
        if (is_array($decoded) && isset($decoded['hits']) && is_array($decoded['hits'])) {
            $hits = $decoded['hits'];
        }
        $window = (int)($config['enrollRateWindow'] ?? 900);
        $cutoff = time() - max(1, $window);
        $existing = isset($hits[$ip]) && is_array($hits[$ip]) ? $hits[$ip] : [];
        $kept = [];
        foreach ($existing as $stamp) {
            if ((int)$stamp >= $cutoff) {
                $kept[] = (int)$stamp;
            }
        }
        $kept[] = time();
        $hits[$ip] = $kept;
        $payload = json_encode(
            ['hits' => $hits, 'updatedAt' => gmdate('c')],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $payload === false ? '{"hits":{}}' : $payload);
        fflush($fh);
        flock($fh, LOCK_UN);
    } finally {
        fclose($fh);
    }
}

/**
 * @param array<string, mixed> $input
 * @param array<string, mixed> $config
 * @param array<string, mixed> $server
 * @return array{status: int, body: array<string, mixed>}
 */
function sms_enroll_handle(array $input, array $config, array $server): array
{
    $ip = sms_enroll_client_ip($server);
    if (sms_enroll_is_rate_limited($config, $ip)) {
        return [
            'status' => 429,
            'body' => [
                'ok' => false,
                'error' => 'Too many enrollment attempts. Try again in a few minutes.',
            ],
        ];
    }
    sms_enroll_record_attempt($config, $ip);

    $validated = sms_enroll_validate($input, $config);
    if (empty($validated['ok'])) {
        return [
            'status' => 400,
            'body' => [
                'ok' => false,
                'error' => (string)($validated['error'] ?? 'Invalid enrollment'),
            ],
        ];
    }

    $path = sms_pair_path($config);
    if (!sms_write_pair($path, $validated['owner'], $validated['contact'])) {
        return [
            'status' => 500,
            'body' => [
                'ok' => false,
                'error' => 'Could not save enrollment on the server.',
            ],
        ];
    }

    return [
        'status' => 200,
        'body' => [
            'ok' => true,
            'owner' => $validated['owner'],
            'contact' => $validated['contact'],
        ],
    ];
}

/**
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function sms_enroll_parse_body(string $raw, array $post, string $contentType): array
{
    $type = strtolower($contentType);
    $looksJson = str_contains($type, 'json')
        || ($post === [] && str_starts_with(ltrim($raw), '{'));
    if ($looksJson && $raw !== '') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return $post;
}
