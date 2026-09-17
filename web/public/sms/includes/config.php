<?php
if (!defined('DELIVERYDAVE_SMS')) {
    http_response_code(403);
    exit('Forbidden');
}

function sms_config_candidates(): array
{
    $fromEnv = getenv('DELIVERYDAVE_SMS_CONFIG');
    if (is_string($fromEnv) && $fromEnv !== '') {
        return [$fromEnv];
    }
    return [
        dirname(__DIR__) . '/config.local.php',
        dirname(__DIR__, 2) . '/api/config.local.php',
        dirname(__DIR__, 2) . '/config.local.php',
        dirname(__DIR__, 4) . '/config.local.php',
    ];
}

function sms_include_config_file(string $file): array
{
    if (!is_readable($file)) {
        return [];
    }
    $config = include $file;
    return is_array($config) ? $config : [];
}

function sms_merge_person(array $raw, array $defaults): array
{
    $person = $raw;
    if (!is_array($person)) {
        $person = [];
    }
    return [
        'name' => trim((string)($person['name'] ?? $defaults['name'])),
        'phone' => sms_e164((string)($person['phone'] ?? $defaults['phone'])),
        'lang' => sms_lang((string)($person['lang'] ?? $defaults['lang'])),
    ];
}

function sms_usable_secret(mixed $value, array $placeholders): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    foreach ($placeholders as $needle) {
        if (str_contains($value, $needle)) {
            return '';
        }
    }
    return $value;
}

/**
 * @return array<string, mixed>
 */
function sms_load_config(): array
{
    $merged = [];
    $apiKey = '';
    $authToken = '';
    foreach (sms_config_candidates() as $file) {
        $chunk = sms_include_config_file($file);
        if ($chunk === []) {
            continue;
        }
        $merged = array_merge($merged, $chunk);
        if (isset($chunk['owner']) && is_array($chunk['owner'])) {
            $merged['owner'] = array_merge($merged['owner'] ?? [], $chunk['owner']);
        }
        if (isset($chunk['contact']) && is_array($chunk['contact'])) {
            $merged['contact'] = array_merge($merged['contact'] ?? [], $chunk['contact']);
        }
        if ($apiKey === '') {
            $apiKey = sms_usable_secret($chunk['apiKey'] ?? '', ['your-key-here']);
        }
        if ($authToken === '') {
            $authToken = sms_usable_secret($chunk['authToken'] ?? '', ['your-auth-token']);
        }
    }

    $owner = sms_merge_person($merged['owner'] ?? [], [
        'name' => 'Dave',
        'phone' => '',
        'lang' => 'en',
    ]);
    $contact = sms_merge_person($merged['contact'] ?? [], [
        'name' => 'Luis',
        'phone' => '',
        'lang' => 'es',
    ]);

    $twilioNumber = sms_e164((string)($merged['twilioNumber'] ?? '+14704704880'));
    if ($twilioNumber === '') {
        $twilioNumber = '+14704704880';
    }
    $accountSid = sms_usable_secret($merged['accountSid'] ?? '', ['ACxxxxxxxx']);

    $config = [
        'accountSid' => $accountSid,
        'authToken' => $authToken,
        'messagingServiceSid' => sms_messaging_service_sid($merged['messagingServiceSid'] ?? ''),
        'twilioNumber' => $twilioNumber,
        'owner' => $owner,
        'contact' => $contact,
        'apiKey' => $apiKey,
        'xaiBase' => rtrim((string)($merged['xaiBase'] ?? 'https://api.x.ai/v1'), '/'),
        'xaiModel' => trim((string)($merged['xaiModel'] ?? 'grok-4.6')) ?: 'grok-4.6',
        'webhookUrl' => trim((string)($merged['webhookUrl'] ?? '')),
        'helpEmail' => trim((string)($merged['helpEmail'] ?? 'contact@deliverydave.ai')) ?: 'contact@deliverydave.ai',
        'privacyUrl' => trim((string)($merged['privacyUrl'] ?? 'https://translate.deliverydave.ai/sms/privacy.html')),
        'termsUrl' => trim((string)($merged['termsUrl'] ?? 'https://translate.deliverydave.ai/sms/terms.html')),
        'optoutFile' => (string)($merged['optoutFile'] ?? ''),
        'pairFile' => (string)($merged['pairFile'] ?? ''),
        'enrollRateFile' => (string)($merged['enrollRateFile'] ?? ''),
        'enrollRateLimit' => isset($merged['enrollRateLimit']) ? (int)$merged['enrollRateLimit'] : 8,
        'enrollRateWindow' => isset($merged['enrollRateWindow']) ? (int)$merged['enrollRateWindow'] : 900,
        'dryRun' => !empty($merged['dryRun']),
        'outboxFile' => (string)($merged['outboxFile'] ?? ''),
        'validateSignature' => array_key_exists('validateSignature', $merged)
            ? (bool)$merged['validateSignature']
            : true,
    ];
    return sms_apply_pair_override($config);
}

/**
 * Messaging Service SIDs start with MG. Campaign SIDs (CM…) are ignored.
 */
function sms_messaging_service_sid(mixed $value): string
{
    $sid = trim((string)$value);
    if ($sid === '' || str_starts_with($sid, 'MG')) {
        return $sid;
    }
    return '';
}

function sms_pair_path(array $config): string
{
    if (!empty($config['pairFile']) && is_string($config['pairFile'])) {
        return $config['pairFile'];
    }
    return dirname(__DIR__) . '/data/pair.json';
}

/**
 * @return array{owner: array{name: string, phone: string, lang: string}, contact: array{name: string, phone: string, lang: string}, consentedAt?: string}|null
 */
function sms_read_pair(string $path): ?array
{
    if ($path === '' || !is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    $owner = is_array($decoded['owner'] ?? null) ? $decoded['owner'] : [];
    $contact = is_array($decoded['contact'] ?? null) ? $decoded['contact'] : [];
    $ownerPhone = sms_e164((string)($owner['phone'] ?? ''));
    $contactPhone = sms_e164((string)($contact['phone'] ?? ''));
    if (!sms_e164_valid($ownerPhone) || !sms_e164_valid($contactPhone)) {
        return null;
    }
    if (sms_same_number($ownerPhone, $contactPhone)) {
        return null;
    }
    return [
        'owner' => [
            'name' => trim((string)($owner['name'] ?? '')),
            'phone' => $ownerPhone,
            'lang' => sms_lang((string)($owner['lang'] ?? 'en')),
        ],
        'contact' => [
            'name' => trim((string)($contact['name'] ?? '')),
            'phone' => $contactPhone,
            'lang' => sms_lang((string)($contact['lang'] ?? 'es')),
        ],
        'consentedAt' => (string)($decoded['consentedAt'] ?? ''),
    ];
}

/**
 * pair.json overrides config.local.php owner/contact name, phone, and lang.
 *
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function sms_apply_pair_override(array $config): array
{
    $pair = sms_read_pair(sms_pair_path($config));
    if ($pair === null) {
        return $config;
    }
    $config['owner'] = sms_merge_person($pair['owner'], is_array($config['owner'] ?? null) ? $config['owner'] : [
        'name' => 'Dave',
        'phone' => '',
        'lang' => 'en',
    ]);
    $config['contact'] = sms_merge_person($pair['contact'], is_array($config['contact'] ?? null) ? $config['contact'] : [
        'name' => 'Luis',
        'phone' => '',
        'lang' => 'es',
    ]);
    return $config;
}

/**
 * @param array{name: string, phone: string, lang: string} $owner
 * @param array{name: string, phone: string, lang: string} $contact
 */
function sms_write_pair(string $path, array $owner, array $contact): bool
{
    if ($path === '') {
        return false;
    }
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log('DeliveryDave SMS: cannot create pair directory');
        return false;
    }
    $existing = sms_read_pair($path);
    $consentedAt = is_array($existing) && ($existing['consentedAt'] ?? '') !== ''
        ? $existing['consentedAt']
        : gmdate('c');
    $payload = json_encode(
        [
            'owner' => [
                'name' => $owner['name'],
                'phone' => $owner['phone'],
                'lang' => $owner['lang'],
            ],
            'contact' => [
                'name' => $contact['name'],
                'phone' => $contact['phone'],
                'lang' => $contact['lang'],
            ],
            'consented' => true,
            'consentedAt' => $consentedAt,
            'updatedAt' => gmdate('c'),
            'source' => 'sms-consent',
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    );
    if ($payload === false) {
        return false;
    }
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        error_log('DeliveryDave SMS: cannot open pair file');
        return false;
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            error_log('DeliveryDave SMS: cannot lock pair file');
            return false;
        }
        ftruncate($fh, 0);
        rewind($fh);
        $ok = fwrite($fh, $payload) !== false;
        fflush($fh);
        flock($fh, LOCK_UN);
        return $ok;
    } finally {
        fclose($fh);
    }
}

function sms_config_errors(array $config): array
{
    $errors = [];
    if ($config['accountSid'] === '' || !str_starts_with($config['accountSid'], 'AC')) {
        $errors[] = 'accountSid';
    }
    if ($config['authToken'] === '' || str_contains($config['authToken'], 'your-auth-token')) {
        $errors[] = 'authToken';
    }
    if ($config['twilioNumber'] === '') {
        $errors[] = 'twilioNumber';
    }
    if ($config['owner']['phone'] === '') {
        $errors[] = 'owner.phone';
    }
    if ($config['contact']['phone'] === '') {
        $errors[] = 'contact.phone';
    }
    if ($config['apiKey'] === '') {
        $errors[] = 'apiKey';
    }
    if ($config['owner']['phone'] !== '' && sms_same_number($config['owner']['phone'], $config['contact']['phone'])) {
        $errors[] = 'owner/contact phones must differ';
    }
    return $errors;
}

/**
 * @return array{role: 'owner'|'contact', name: string, phone: string, lang: string, other: array{role: string, name: string, phone: string, lang: string}}|null
 */
function sms_party_for(array $config, string $from): ?array
{
    $from = sms_e164($from);
    $owner = $config['owner'];
    $contact = $config['contact'];
    if ($from !== '' && sms_same_number($from, $owner['phone'])) {
        return [
            'role' => 'owner',
            'name' => $owner['name'] ?: 'Dave',
            'phone' => $owner['phone'],
            'lang' => $owner['lang'] ?: 'en',
            'other' => [
                'role' => 'contact',
                'name' => $contact['name'] ?: 'Luis',
                'phone' => $contact['phone'],
                'lang' => $contact['lang'] ?: 'es',
            ],
        ];
    }
    if ($from !== '' && sms_same_number($from, $contact['phone'])) {
        return [
            'role' => 'contact',
            'name' => $contact['name'] ?: 'Luis',
            'phone' => $contact['phone'],
            'lang' => $contact['lang'] ?: 'es',
            'other' => [
                'role' => 'owner',
                'name' => $owner['name'] ?: 'Dave',
                'phone' => $owner['phone'],
                'lang' => $owner['lang'] ?: 'en',
            ],
        ];
    }
    return null;
}
