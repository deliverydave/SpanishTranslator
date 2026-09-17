<?php
if (!defined('DELIVERYDAVE_SMS')) {
    define('DELIVERYDAVE_SMS', true);
}

require_once __DIR__ . '/e164.php';
require_once __DIR__ . '/keywords.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/optout.php';
require_once __DIR__ . '/twilio.php';
require_once __DIR__ . '/translate.php';
require_once __DIR__ . '/bridge.php';

function sms_finish_http_response(): void
{
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
        return;
    }
    if (php_sapi_name() === 'cli') {
        return;
    }
    @ob_end_flush();
    @flush();
}
