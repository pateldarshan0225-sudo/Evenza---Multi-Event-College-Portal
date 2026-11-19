<?php
/**
 * Evenza - Central Phone Helper Utility
 * Production-Ready Canonical Phone Normalization, Validation, and Display Formatting
 * 
 * Display Standard: +91 9876543210
 * Database Standard: 9876543210 (10 digits)
 */

/**
 * Normalizes an input phone number to a clean 10-digit string.
 * Returns null if input is empty or invalid. Strictly avoids silent truncation.
 *
 * @param string|null $phone
 * @return string|null
 */
function normalize_phone_number(?string $phone): ?string {
    if ($phone === null) {
        return null;
    }

    $raw = trim($phone);
    if ($raw === '') {
        return null;
    }

    // Reject inputs containing letters or unexpected characters
    if (preg_match('/[a-zA-Z]/', $raw)) {
        return null;
    }

    // Extract all numeric digits
    $digits = preg_replace('/\D/', '', $raw);

    // Handle duplicate +91 or country codes (e.g. 91919876543210 -> invalid)
    if (strlen($digits) > 12) {
        return null;
    }

    // Case 1: 12 digits starting with country code 91 (e.g., 919876543210)
    if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
        $digits = substr($digits, 2);
    }
    // Case 2: 11 digits starting with trunk code 0 (e.g., 09876543210)
    elseif (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
        $digits = substr($digits, 1);
    }

    // Must be exactly 10 digits after normalization
    if (strlen($digits) === 10) {
        return $digits;
    }

    // Reject short or long malformed numbers without silent truncation
    return null;
}

/**
 * Validates whether a phone number can be normalized to a valid 10-digit mobile/landline number.
 *
 * @param string|null $phone
 * @return bool
 */
function is_valid_phone_number(?string $phone): bool {
    $normalized = normalize_phone_number($phone);
    if ($normalized === null) {
        return false;
    }
    // Indian mobile & landline numbers start with digits 2-9
    return preg_match('/^[2-9]\d{9}$/', $normalized) === 1;
}

/**
 * Formats a phone number for canonical display: +91 9876543210
 *
 * @param string|null $phone
 * @return string
 */
function format_phone_number(?string $phone): string {
    if ($phone === null || trim($phone) === '') {
        return '—';
    }

    $normalized = normalize_phone_number($phone);
    if ($normalized !== null) {
        return '+91 ' . $normalized;
    }

    // Fallback for valid international legacy records (e.g. +1 555 888 222)
    $clean = trim($phone);
    if (preg_match('/^\+\d{1,4}[\s\-\d]+$/', $clean) && !preg_match('/\+.*?\+/', $clean)) {
        return htmlspecialchars($clean);
    }

    return '—';
}
