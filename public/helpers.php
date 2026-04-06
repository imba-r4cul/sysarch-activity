<?php
/**
 * Shared Helper Functions
 * Common utilities used across multiple pages
 */

/**
 * Escapes a value for safe HTML output
 * 
 * @param mixed $value The value to escape
 * @return string The escaped string
 */
function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generates student initials from first and last names
 * 
 * @param mixed $firstName The student's first name
 * @param mixed $lastName The student's last name
 * @return string Two-letter initials or 'NA' if both names are empty
 */
function studentInitials($firstName, $lastName)
{
    $first = trim((string) $firstName);
    $last = trim((string) $lastName);

    $a = $first !== '' ? strtoupper(substr($first, 0, 1)) : '';
    $b = $last !== '' ? strtoupper(substr($last, 0, 1)) : '';
    $initials = $a . $b;

    return $initials !== '' ? $initials : 'NA';
}

/**
 * Formats a datetime value for display
 * 
 * @param mixed $value The datetime value
 * @return string Formatted date string (d M Y, h:i A) or '--' if invalid
 */
function formatDateTime($value)
{
    if ($value === null || $value === '') {
        return '--';
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '--';
    }

    return date('d M Y, h:i A', $timestamp);
}
