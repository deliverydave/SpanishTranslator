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

    return [
        'accountSid' => $accountSid,
        'authToken' => $authToken,
        'messagingServiceSid' => trim((string)($merged['messagingServiceSid'] ?? '')),
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
        'dryRun' => !empty($merged['dryRun']),
        'outboxFile' => (string)($merged['outboxFile'] ?? ''),
        'validateSignature' => array_key_exists('validateSignature', $merged)
            ? (bool)$merged['validateSignature']
            : true,
    ];
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
