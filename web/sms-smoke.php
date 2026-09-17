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
