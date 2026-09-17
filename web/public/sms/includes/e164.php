<?php
if (!defined('DELIVERYDAVE_SMS')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Normalize a phone number to E.164. Bare 10-digit numbers are treated as US/Canada (+1).
 */
function sms_e164(string $phone, string $defaultCountry = '1'): string
{
    $trimmed = trim($phone);
    if ($trimmed === '') {
        return '';
    }
    $hasPlus = str_starts_with($trimmed, '+');
    $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
    if ($digits === '') {
        return '';
    }
    if ($hasPlus) {
        return '+' . $digits;
    }
    if (strlen($digits) === 10) {
        return '+' . $defaultCountry . $digits;
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        return '+' . $digits;
    }
    return '+' . $digits;
}

function sms_same_number(string $a, string $b): bool
{
    $left = sms_e164($a);
    $right = sms_e164($b);
    return $left !== '' && $left === $right;
}

/**
 * E.164-ish: + then 8–15 digits, first digit 1–9 (ITU max 15).
 */
function sms_e164_valid(string $phone): bool
{
    $e164 = sms_e164($phone);
    if ($e164 === '') {
        return false;
    }
    return (bool)preg_match('/^\+[1-9]\d{7,14}$/', $e164);
}
