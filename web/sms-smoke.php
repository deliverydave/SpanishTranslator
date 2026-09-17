#!/usr/bin/env php
<?php
/**
 * CLI smoke tests for the DeliveryDave bilingual SMS bridge.
 * Run: php web/sms-smoke.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

define('DELIVERYDAVE_SMS', true);
require_once __DIR__ . '/public/sms/includes/bootstrap.php';

$failed = 0;
$passed = 0;

function expect($cond, string $label): void
{
    global $failed, $passed;
    if ($cond) {
        $passed++;
        echo "  ok  {$label}\n";
        return;
    }
    $failed++;
    echo "  FAIL  {$label}\n";
}

function test_config(): array
{
    $dir = sys_get_temp_dir() . '/dd-sms-' . bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);
    return [
        'accountSid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'authToken' => 'test-auth-token-not-real',
        'messagingServiceSid' => '',
        'twilioNumber' => '+14704704880',
        'owner' => ['name' => 'Dave', 'phone' => '+15555550100', 'lang' => 'en'],
        'contact' => ['name' => 'Luis', 'phone' => '+15555550101', 'lang' => 'es'],
        'apiKey' => 'xai-test-not-used',
        'xaiBase' => 'https://api.x.ai/v1',
        'xaiModel' => 'grok-4.6',
        'webhookUrl' => 'https://translate.deliverydave.ai/sms/twilio-webhook.php',
        'helpEmail' => 'contact@deliverydave.ai',
        'privacyUrl' => 'https://translate.deliverydave.ai/sms/privacy.html',
        'termsUrl' => 'https://translate.deliverydave.ai/sms/terms.html',
        'optoutFile' => $dir . '/optouts.json',
        'pairFile' => $dir . '/pair.json',
        'enrollRateFile' => $dir . '/enroll-rate.json',
        'enrollRateLimit' => 8,
        'enrollRateWindow' => 900,
        'dryRun' => true,
        'outboxFile' => $dir . '/outbox.json',
        'validateSignature' => true,
    ];
}

function collect_send(): array
{
    $box = ['sends' => []];
    $send = static function (string $to, string $body) use (&$box): array {
        $box['sends'][] = ['to' => $to, 'body' => $body];
        return ['ok' => true, 'status' => 200, 'error' => '', 'sid' => 'SM-test'];
    };
    $translate = static function (string $text, string $sourceLang, string $targetLang): string {
        $prefix = sms_lang($targetLang) === 'es' ? 'ES' : 'EN';
        return $prefix . '(' . $text . ')';
    };
    return [&$box, $send, $translate];
}

echo "DeliveryDave SMS smoke tests\n";

echo "\nE.164\n";
expect(sms_e164('+14704704880') === '+14704704880', 'already E.164');
expect(sms_e164('4704704880') === '+14704704880', '10-digit US');
expect(sms_e164('1 (470) 470-4880') === '+14704704880', '11-digit US with punctuation');
expect(sms_e164('+52 55 1234 5678') === '+525512345678', 'MX stays country-coded');
expect(sms_e164_valid('+14704704880'), 'Twilio number is valid E.164');
expect(sms_e164_valid('5555550100'), '10-digit US is valid after normalize');
expect(!sms_e164_valid(''), 'empty is invalid');
expect(!sms_e164_valid('123'), 'too-short is invalid');
expect(!sms_e164_valid('abc'), 'letters are invalid');
expect(sms_same_number('(555) 555-0100', '+15555550100'), 'same number after normalize');
expect(!sms_same_number('+15555550100', '+15555550101'), 'different numbers');

echo "\nKeywords\n";
expect(sms_classify_keyword('stop') === 'stop', 'STOP');
expect(sms_classify_keyword('STOP.') === 'stop', 'STOP with period');
expect(sms_classify_keyword('unsubscribe') === 'stop', 'UNSUBSCRIBE');
expect(sms_classify_keyword('help') === 'help', 'HELP');
expect(sms_classify_keyword('INFO') === 'help', 'INFO');
expect(sms_classify_keyword('START') === 'start', 'START');
expect(sms_classify_keyword('YES') === 'start', 'YES');
expect(sms_classify_keyword('UNSTOP') === 'start', 'UNSTOP');
expect(sms_classify_keyword('see you at the jobsite') === null, 'jobsite sentence is not a keyword');
expect(sms_classify_keyword('', 'STOP') === 'stop', 'Twilio OptOutType STOP');
expect(sms_classify_keyword('please STOP by the site') === null, 'STOP in a sentence is not opt-out');

echo "\nCopy is jobsite-only (no market language); STOP/HELP stay English\n";
foreach (sms_all_static_copy() as $i => $line) {
    expect(
        !preg_match(sms_forbidden_market_regex(), $line),
        'static copy #' . $i . ' has no market language',
    );
}
$enFwd = sms_attribution('Luis', 'I will be on site at 7.', 'es', 'en');
$esFwd = sms_attribution('Dave', 'Estaré en la obra a las 7.', 'en', 'es');
expect(str_contains($enFwd, 'Original Spanish received and translated to English by DeliveryDave.'), 'EN attribution format');
expect(str_contains($esFwd, 'Inglés original recibido y traducido al español por DeliveryDave.'), 'ES attribution format');
expect(str_contains($enFwd, 'STOP') && str_contains($enFwd, 'HELP'), 'EN disclosure has STOP/HELP');
expect(str_contains($esFwd, 'STOP') && str_contains($esFwd, 'HELP'), 'ES disclosure has English STOP/HELP');
expect(!str_contains($esFwd, 'ALTO') && !str_contains($esFwd, 'AYUDA PARA CANCELAR'), 'ES does not replace STOP/HELP keywords');
$helpEn = sms_help_message(test_config(), 'en');
expect(str_contains($helpEn, 'contact@deliverydave.ai'), 'HELP has support email');
expect(str_contains($helpEn, 'privacy.html') && str_contains($helpEn, 'terms.html'), 'HELP has privacy/terms URLs');

echo "\nTwilio signature\n";
$token = 'test-auth-token-not-real';
$url = 'https://translate.deliverydave.ai/sms/twilio-webhook.php';
$params = [
    'AccountSid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    'Body' => 'Hola',
    'From' => '+15555550101',
    'To' => '+14704704880',
];
$sig = sms_twilio_compute_signature($token, $url, $params);
expect(sms_validate_twilio_signature($token, $sig, $url, $params), 'valid signature accepted');
expect(!sms_validate_twilio_signature($token, $sig, $url, array_merge($params, ['Body' => 'tamper'])), 'tampered body rejected');
expect(!sms_validate_twilio_signature('wrong-token', $sig, $url, $params), 'wrong token rejected');
expect(!sms_validate_twilio_signature($token, '', $url, $params), 'empty signature rejected');
$server = [
    'HTTPS' => 'on',
    'HTTP_HOST' => 'translate.deliverydave.ai',
    'REQUEST_URI' => '/sms/twilio-webhook.php',
    'HTTP_X_TWILIO_SIGNATURE' => $sig,
];
$cfg = test_config();
expect(sms_request_is_from_twilio($cfg, $server, $params), 'request validator accepts reconstructed HTTPS URL');
$badServer = $server;
$badServer['HTTP_X_TWILIO_SIGNATURE'] = 'Np1nax6uFoY6qpfT5l9jWwJeit0=';
expect(!sms_request_is_from_twilio($cfg, $badServer, $params), 'request validator rejects bad header');

echo "\nBridge routing\n";
$cfg = test_config();

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550101', 'Body' => 'Llego a las 7 a la obra.', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['forwarded'] === true, 'contact → owner is forwarded');
expect(($result['sends'][0]['to'] ?? '') === '+15555550100', 'contact message goes to owner');
expect(str_contains($result['sends'][0]['body'] ?? '', 'Luis:'), 'owner sees contact name');
expect(str_contains($result['sends'][0]['body'] ?? '', 'EN('), 'translated into owner English');
expect(str_contains($result['sends'][0]['body'] ?? '', 'Original Spanish received and translated to English by DeliveryDave.'), 'EN receive-language attribution');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550100', 'Body' => 'See you on site at 7.', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['forwarded'] === true, 'owner → contact is forwarded');
expect(($result['sends'][0]['to'] ?? '') === '+15555550101', 'owner message goes to contact');
expect(str_contains($result['sends'][0]['body'] ?? '', 'Dave:'), 'contact sees owner name');
expect(str_contains($result['sends'][0]['body'] ?? '', 'ES('), 'translated into contact Spanish');
expect(str_contains($result['sends'][0]['body'] ?? '', 'STOP'), 'contact message keeps English STOP');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550999', 'Body' => 'hello', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['status'] === 'unknown_from' && $result['forwarded'] === false, 'unknown From is not forwarded');
expect(count($result['sends']) === 1, 'unknown From gets one polite reply');
expect(($result['sends'][0]['to'] ?? '') === '+15555550999', 'unknown reply goes to From only');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550999', 'Body' => 'hello again', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['forwarded'] === false && $result['sends'] === [], 'unknown From is rate-limited after first polite reply');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550101', 'Body' => 'STOP', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['status'] === 'opt_out' && $result['forwarded'] === false, 'STOP is not forwarded');
expect(sms_is_opted_out($cfg, '+15555550101'), 'STOP persists opt-out');
expect(str_contains($result['sends'][0]['body'] ?? '', 'START'), 'STOP confirm mentions START');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550101', 'Body' => 'still on the way', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['forwarded'] === false, 'opted-out contact is not forwarded');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550100', 'Body' => 'Where are you?', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['status'] === 'dest_opted_out' && $result['forwarded'] === false, 'owner is not sent to opted-out contact');
expect(($result['sends'][0]['to'] ?? '') === '+15555550100', 'owner is told the contact opted out');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550101', 'Body' => 'START', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['status'] === 'opt_in', 'START re-opts in a configured contact');
expect(!sms_is_opted_out($cfg, '+15555550101'), 'START clears opt-out');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550998', 'Body' => 'START', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['status'] === 'start_unknown' && $result['forwarded'] === false, 'START from unknown number does not opt in');
expect(!sms_is_opted_out($cfg, '+15555550998') === true, 'unknown START does not create an opted-in pair member');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550100', 'Body' => 'HELP', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['status'] === 'help' && $result['forwarded'] === false, 'HELP is not forwarded');
expect(str_contains($result['sends'][0]['body'] ?? '', 'contact@deliverydave.ai'), 'HELP body has email');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550101', 'Body' => 'STOP', 'To' => '+14704704880', 'OptOutType' => 'STOP'],
    $cfg,
    $send,
    $translate,
);
expect($result['status'] === 'opt_out' && $result['sends'] === [], 'Twilio OptOutType STOP is persisted without a second confirm');
expect(sms_is_opted_out($cfg, '+15555550101'), 'OptOutType STOP still persists');

[$box, $send, $translate] = collect_send();
sms_set_opted_out($cfg, '+15555550101', false);
$result = sms_handle_inbound(
    ['From' => '+15555550101', 'Body' => '', 'NumMedia' => '1', 'To' => '+14704704880'],
    $cfg,
    $send,
    $translate,
);
expect($result['status'] === 'media_only' && $result['forwarded'] === false, 'MMS-only is not forwarded');

echo "\nConsent enroll validation\n";
$cfg = test_config();
$validEnroll = [
    'owner_name' => 'Bellero',
    'owner_phone' => '555-555-0200',
    'owner_language' => 'en',
    'contact_name' => 'Luis',
    'contact_phone' => '+1 (555) 555-0201',
    'contact_language' => 'es',
    'sms_consent' => true,
];
$missingConsent = sms_enroll_validate(array_merge($validEnroll, ['sms_consent' => false]), $cfg);
expect(empty($missingConsent['ok']), 'checkbox is required');
expect(str_contains((string)($missingConsent['error'] ?? ''), 'consent'), 'missing consent mentions consent');

$missingPhones = sms_enroll_validate(array_merge($validEnroll, ['owner_phone' => '', 'contact_phone' => '']), $cfg);
expect(empty($missingPhones['ok']), 'both phones required');

$badOwner = sms_enroll_validate(array_merge($validEnroll, ['owner_phone' => '123']), $cfg);
expect(empty($badOwner['ok']), 'invalid owner phone rejected');

$samePhones = sms_enroll_validate(array_merge($validEnroll, ['contact_phone' => '555-555-0200']), $cfg);
expect(empty($samePhones['ok']), 'identical owner/contact phones rejected');

$twilioAsOwner = sms_enroll_validate(array_merge($validEnroll, ['owner_phone' => '+14704704880']), $cfg);
expect(empty($twilioAsOwner['ok']), 'Twilio number cannot be the owner');

$okEnroll = sms_enroll_validate($validEnroll, $cfg);
expect(!empty($okEnroll['ok']), 'valid enroll accepted');
expect(($okEnroll['owner']['phone'] ?? '') === '+15555550200', 'owner normalized to E.164');
expect(($okEnroll['contact']['phone'] ?? '') === '+15555550201', 'contact normalized to E.164');
expect(($okEnroll['owner']['name'] ?? '') === 'Bellero', 'owner name kept');
expect(($okEnroll['contact']['lang'] ?? '') === 'es', 'contact lang es');

$secretPayload = array_merge($validEnroll, [
    'authToken' => 'real-auth-token-must-not-persist',
    'accountSid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    'apiKey' => 'xai-should-not-be-saved',
    'messagingServiceSid' => 'MGshould-not-be-saved',
]);
$saved = sms_enroll_handle($secretPayload, $cfg, ['REMOTE_ADDR' => '203.0.113.10']);
expect(($saved['status'] ?? 0) === 200 && !empty($saved['body']['ok']), 'enroll handle writes pair.json');
$pairRaw = (string)file_get_contents($cfg['pairFile']);
expect(str_contains($pairRaw, '+15555550200') && str_contains($pairRaw, '+15555550201'), 'pair.json has enrolled phones');
expect(
    !str_contains($pairRaw, 'real-auth-token-must-not-persist')
    && !str_contains($pairRaw, 'xai-should-not-be-saved')
    && !str_contains($pairRaw, 'MGshould-not-be-saved'),
    'pair.json does not store Twilio/xAI secrets from the form',
);
expect(!isset($saved['body']['authToken'], $saved['body']['apiKey']), 'enroll JSON omits secrets');

$noThanks = sms_enroll_handle(
    array_merge($validEnroll, ['sms_consent' => false, 'owner_phone' => '+15555550900', 'contact_phone' => '+15555550901']),
    $cfg,
    ['REMOTE_ADDR' => '203.0.113.10'],
);
expect(($noThanks['status'] ?? 0) === 400, 'no consent is not enrolled');
$pairAfterDecline = json_decode((string)file_get_contents($cfg['pairFile']), true);
expect(($pairAfterDecline['owner']['phone'] ?? '') === '+15555550200', 'decline does not overwrite pair.json');

$cfgRate = test_config();
$cfgRate['enrollRateLimit'] = 2;
$cfgRate['enrollRateWindow'] = 900;
$rateInput = $validEnroll;
$first = sms_enroll_handle($rateInput, $cfgRate, ['REMOTE_ADDR' => '198.51.100.20']);
$second = sms_enroll_handle($rateInput, $cfgRate, ['REMOTE_ADDR' => '198.51.100.20']);
$third = sms_enroll_handle($rateInput, $cfgRate, ['REMOTE_ADDR' => '198.51.100.20']);
expect(($first['status'] ?? 0) === 200 && ($second['status'] ?? 0) === 200, 'rate limit allows first hits');
expect(($third['status'] ?? 0) === 429, 'rate limit blocks extra enroll POSTs');

echo "\npair.json overrides webhook routing\n";
$cfgPair = test_config();
$cfgPair['owner'] = ['name' => 'Dave', 'phone' => '+15555550100', 'lang' => 'en'];
$cfgPair['contact'] = ['name' => 'Luis', 'phone' => '+15555550101', 'lang' => 'es'];
file_put_contents($cfgPair['pairFile'], json_encode([
    'owner' => ['name' => 'Bellero', 'phone' => '+15555550800', 'lang' => 'en'],
    'contact' => ['name' => 'Crew', 'phone' => '+15555550801', 'lang' => 'es'],
    'consented' => true,
    'consentedAt' => '2026-09-17T00:00:00+00:00',
], JSON_UNESCAPED_SLASHES));
$overridden = sms_apply_pair_override($cfgPair);
expect($overridden['owner']['phone'] === '+15555550800', 'pair.json owner phone wins');
expect($overridden['contact']['phone'] === '+15555550801', 'pair.json contact phone wins');
expect($overridden['owner']['name'] === 'Bellero' && $overridden['contact']['name'] === 'Crew', 'pair.json names win');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550801', 'Body' => 'Llego a la obra.', 'To' => '+14704704880'],
    $overridden,
    $send,
    $translate,
);
expect($result['forwarded'] === true, 'enrolled contact is forwarded');
expect(($result['sends'][0]['to'] ?? '') === '+15555550800', 'forward goes to enrolled owner');
expect(str_contains($result['sends'][0]['body'] ?? '', 'Crew:'), 'owner sees enrolled contact name');

[$box, $send, $translate] = collect_send();
$result = sms_handle_inbound(
    ['From' => '+15555550101', 'Body' => 'old contact still texting', 'To' => '+14704704880'],
    $overridden,
    $send,
    $translate,
);
expect($result['status'] === 'unknown_from' && $result['forwarded'] === false, 'config.local.php contact is unknown after pair.json override');

$oldEnv = getenv('DELIVERYDAVE_SMS_CONFIG');
$loadDir = sys_get_temp_dir() . '/dd-sms-load-' . bin2hex(random_bytes(4));
mkdir($loadDir, 0700, true);
$loadCfg = test_config();
$loadCfg['pairFile'] = $loadDir . '/pair.json';
$loadCfg['optoutFile'] = $loadDir . '/optouts.json';
$loadFile = $loadDir . '/config.local.php';
file_put_contents($loadFile, "<?php\nreturn " . var_export($loadCfg, true) . ";\n");
file_put_contents($loadCfg['pairFile'], json_encode([
    'owner' => ['name' => 'Pat', 'phone' => '+15555550700', 'lang' => 'en'],
    'contact' => ['name' => 'Sam', 'phone' => '+15555550701', 'lang' => 'es'],
]));
putenv('DELIVERYDAVE_SMS_CONFIG=' . $loadFile);
$loaded = sms_load_config();
if ($oldEnv === false || $oldEnv === '') {
    putenv('DELIVERYDAVE_SMS_CONFIG');
} else {
    putenv('DELIVERYDAVE_SMS_CONFIG=' . $oldEnv);
}
expect($loaded['owner']['phone'] === '+15555550700', 'sms_load_config applies pair.json');
expect($loaded['contact']['name'] === 'Sam', 'sms_load_config pair name');
expect($loaded['authToken'] === 'test-auth-token-not-real', 'Twilio token still comes from config.local.php');

echo "\nConsent page copy\n";
$consentPath = __DIR__ . '/public/sms/consent/index.html';
$hostingerConsent = __DIR__ . '/../hostinger-pages/sms-consent/index.html';
$consentHtml = (string)file_get_contents($consentPath);
expect(is_readable($consentPath), 'in-repo consent page exists');
expect(is_readable($hostingerConsent), 'hostinger-pages consent copy exists');
expect(str_contains($consentHtml, 'translate.deliverydave.ai/sms/enroll.php'), 'consent form posts to enroll.php');
expect(str_contains($consentHtml, 'No thanks — continue without SMS'), 'No thanks stays on the page');
expect(str_contains($consentHtml, 'sms_consent'), 'consent checkbox is present');
expect(!str_contains($consentHtml, 'authToken') && !str_contains($consentHtml, 'accountSid'), 'consent page does not collect Twilio secrets');
expect(
    str_contains((string)file_get_contents($hostingerConsent), 'translate.deliverydave.ai/sms/enroll.php'),
    'Hostinger main-site copy posts to enroll.php',
);

echo "\nConfig example\n";
$examplePath = __DIR__ . '/public/sms/config.local.php.example';
$example = include $examplePath;
expect(is_array($example), 'example config loads');
expect(($example['twilioNumber'] ?? '') === '+14704704880', 'default Twilio number');
expect(isset($example['owner']['name'], $example['contact']['phone'], $example['apiKey']), 'owner/contact/apiKey present');
expect(($example['messagingServiceSid'] ?? 'missing') === '', 'messagingServiceSid optional empty');
$rawExample = file_get_contents($examplePath);
expect(
    !preg_match('/sk-|xai-[a-zA-Z0-9]{10,}|[0-9a-f]{32}/', (string)$rawExample)
    || str_contains((string)$rawExample, 'your-auth-token-here'),
    'example file has placeholders, not live secrets',
);

$errors = sms_config_errors(array_merge($cfg, ['apiKey' => '', 'authToken' => 'your-auth-token-here']));
expect(in_array('apiKey', $errors, true) && in_array('authToken', $errors, true), 'incomplete config is rejected');

echo "\nHTTP webhook (php built-in server)\n";
$httpDir = sys_get_temp_dir() . '/dd-sms-http-' . bin2hex(random_bytes(4));
mkdir($httpDir, 0700, true);
$httpCfg = test_config();
$httpCfg['optoutFile'] = $httpDir . '/optouts.json';
$httpCfg['outboxFile'] = $httpDir . '/outbox.json';
$httpCfg['pairFile'] = $httpDir . '/pair.json';
$httpCfg['enrollRateFile'] = $httpDir . '/enroll-rate.json';
$httpCfg['dryRun'] = true;
$port = 8099;
$webhookPath = '/sms/twilio-webhook.php';
$httpCfg['webhookUrl'] = 'http://127.0.0.1:' . $port . $webhookPath;
$configFile = $httpDir . '/config.local.php';
file_put_contents($configFile, "<?php\nreturn " . var_export($httpCfg, true) . ";\n");
$cmd = 'DELIVERYDAVE_SMS_CONFIG=' . escapeshellarg($configFile)
    . ' php -S 127.0.0.1:' . $port
    . ' -t ' . escapeshellarg(__DIR__ . '/public')
    . ' >/dev/null 2>' . escapeshellarg($httpDir . '/server.err')
    . ' & echo $!';
$pid = (int)trim((string)shell_exec($cmd));
$ready = false;
for ($i = 0; $i < 25; $i++) {
    usleep(100000);
    $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
    if (is_resource($conn)) {
        fclose($conn);
        $ready = true;
        break;
    }
}
expect($pid > 0 && $ready, 'php built-in server started');
if ($pid > 0 && $ready) {
    $post = [
        'AccountSid' => $httpCfg['accountSid'],
        'Body' => 'Llego a la obra a las 7.',
        'From' => '+15555550101',
        'To' => '+14704704880',
    ];
    $url = $httpCfg['webhookUrl'];
    $sig = sms_twilio_compute_signature($httpCfg['authToken'], $url, $post);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Twilio-Signature: ' . $sig,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HEADER => true,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    expect($status === 200 && is_string($raw) && str_contains($raw, '<Response></Response>'), 'signed POST returns empty TwiML');
    $outbox = is_readable($httpCfg['outboxFile'])
        ? json_decode((string)file_get_contents($httpCfg['outboxFile']), true)
        : [];
    expect(is_array($outbox) && count($outbox) === 1, 'dry-run outbox has one forwarded SMS');
    expect(($outbox[0]['to'] ?? '') === '+15555550100', 'HTTP forward went to the owner');
    expect(str_contains((string)($outbox[0]['body'] ?? ''), 'Luis:'), 'HTTP forward uses contact name');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Twilio-Signature: Np1nax6uFoY6qpfT5l9jWwJeit0=',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $badStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    expect($badStatus === 403, 'bad signature is 403');

    $enrollUrl = 'http://127.0.0.1:' . $port . '/sms/enroll.php';
    $ch = curl_init($enrollUrl);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'OPTIONS',
        CURLOPT_HTTPHEADER => [
            'Origin: https://deliverydave.ai',
            'Access-Control-Request-Method: POST',
            'Access-Control-Request-Headers: content-type',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_NOBODY => false,
    ]);
    $optRaw = curl_exec($ch);
    $optStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    expect($optStatus === 204, 'enroll OPTIONS is 204');
    expect(is_string($optRaw) && str_contains($optRaw, 'Access-Control-Allow-Origin: https://deliverydave.ai'), 'CORS allows deliverydave.ai');

    $ch = curl_init($enrollUrl);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'OPTIONS',
        CURLOPT_HTTPHEADER => [
            'Origin: https://evil.example',
            'Access-Control-Request-Method: POST',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $badCors = curl_exec($ch);
    curl_close($ch);
    expect(is_string($badCors) && !str_contains($badCors, 'https://evil.example'), 'CORS does not echo unknown origins');

    $ch = curl_init($enrollUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'owner_name' => 'Bellero',
            'owner_phone' => '+15555550300',
            'owner_language' => 'en',
            'contact_name' => 'Crew',
            'contact_phone' => '+15555550301',
            'contact_language' => 'es',
            'sms_consent' => false,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Origin: https://deliverydave.ai',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $noConsentStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    expect($noConsentStatus === 400, 'HTTP enroll without consent is 400');

    $ch = curl_init($enrollUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'owner_name' => 'Bellero',
            'owner_phone' => '+15555550300',
            'owner_language' => 'en',
            'contact_name' => 'Crew',
            'contact_phone' => '+15555550301',
            'contact_language' => 'es',
            'sms_consent' => true,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Origin: https://deliverydave.ai',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $enrollRaw = curl_exec($ch);
    $enrollStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    expect($enrollStatus === 200 && is_string($enrollRaw) && str_contains($enrollRaw, '+15555550300'), 'HTTP enroll returns 200 and phones');
    expect(is_string($enrollRaw) && str_contains($enrollRaw, 'Access-Control-Allow-Origin: https://deliverydave.ai'), 'enroll POST includes CORS');
    $pairHttp = is_readable($httpCfg['pairFile'])
        ? json_decode((string)file_get_contents($httpCfg['pairFile']), true)
        : [];
    expect(($pairHttp['contact']['phone'] ?? '') === '+15555550301', 'HTTP enroll wrote pair.json');

    if (is_file($httpCfg['outboxFile'])) {
        unlink($httpCfg['outboxFile']);
    }
    $postNew = [
        'AccountSid' => $httpCfg['accountSid'],
        'Body' => 'Materiales en camino.',
        'From' => '+15555550301',
        'To' => '+14704704880',
    ];
    $sigNew = sms_twilio_compute_signature($httpCfg['authToken'], $url, $postNew);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postNew),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Twilio-Signature: ' . $sigNew,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $newStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $outboxNew = is_readable($httpCfg['outboxFile'])
        ? json_decode((string)file_get_contents($httpCfg['outboxFile']), true)
        : [];
    expect($newStatus === 200 && is_array($outboxNew) && count($outboxNew) === 1, 'webhook after enroll forwarded once');
    expect(($outboxNew[0]['to'] ?? '') === '+15555550300', 'enrolled pair routes to new owner');
    expect(str_contains((string)($outboxNew[0]['body'] ?? ''), 'Crew:'), 'enrolled pair uses new contact name');

    if (is_file($httpCfg['outboxFile'])) {
        unlink($httpCfg['outboxFile']);
    }
    $postOld = [
        'AccountSid' => $httpCfg['accountSid'],
        'Body' => 'old contact after enroll',
        'From' => '+15555550101',
        'To' => '+14704704880',
    ];
    $sigOld = sms_twilio_compute_signature($httpCfg['authToken'], $url, $postOld);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postOld),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Twilio-Signature: ' . $sigOld,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
    $outboxOld = is_readable($httpCfg['outboxFile'])
        ? json_decode((string)file_get_contents($httpCfg['outboxFile']), true)
        : [];
    expect(
        is_array($outboxOld)
        && count($outboxOld) === 1
        && ($outboxOld[0]['to'] ?? '') === '+15555550101',
        'old config.local.php contact is unknown after pair.json enroll',
    );
}
if ($pid > 0) {
    posix_kill($pid, 15);
    usleep(50000);
    posix_kill($pid, 9);
}

echo "\n";
if ($failed > 0) {
    echo "FAILED {$failed} of " . ($failed + $passed) . " checks\n";
    exit(1);
}
echo "Passed {$passed} checks\n";
exit(0);
