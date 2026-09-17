<?php
if (!defined('DELIVERYDAVE_SMS')) {
    http_response_code(403);
    exit('Forbidden');
}

const SMS_STOP_KEYWORDS = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];
const SMS_HELP_KEYWORDS = ['HELP', 'INFO'];
const SMS_START_KEYWORDS = ['START', 'YES', 'UNSTOP'];

function sms_normalize_keyword_body(string $body): string
{
    $trimmed = strtoupper(trim($body));
    $trimmed = trim($trimmed, " \t\n\r\0\x0B.!?,;:\"'");
    return $trimmed;
}

/**
 * @return 'stop'|'help'|'start'|null
 */
function sms_classify_keyword(string $body, string $optOutType = ''): ?string
{
    $fromTwilio = strtoupper(trim($optOutType));
    if ($fromTwilio !== '') {
        if (in_array($fromTwilio, SMS_STOP_KEYWORDS, true) || $fromTwilio === 'STOP') {
            return 'stop';
        }
        if (in_array($fromTwilio, SMS_HELP_KEYWORDS, true) || $fromTwilio === 'HELP') {
            return 'help';
        }
        if (in_array($fromTwilio, SMS_START_KEYWORDS, true) || $fromTwilio === 'START') {
            return 'start';
        }
    }

    $normalized = sms_normalize_keyword_body($body);
    if ($normalized === '') {
        return null;
    }
    if (in_array($normalized, SMS_STOP_KEYWORDS, true)) {
        return 'stop';
    }
    if (in_array($normalized, SMS_HELP_KEYWORDS, true)) {
        return 'help';
    }
    if (in_array($normalized, SMS_START_KEYWORDS, true)) {
        return 'start';
    }
    return null;
}
