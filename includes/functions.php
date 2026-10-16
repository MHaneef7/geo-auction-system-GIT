<?php

include_once __DIR__ . '/database.php';

date_default_timezone_set('Asia/Karachi');

/**
 * Format numeric amount into formatted currency string
 *
 * @param float|int|string|null $amount
 * @return string
 */
function format_price($amount): string
{
    return 'Rs. ' . number_format((float) ($amount ?? 0), 2);
}

/**
 * Calculate and return human-readable remaining time string
 *
 * @param string|DateTimeInterface $end_time
 * @return string
 */
function get_time_remaining($end_time): string
{
    $now = new DateTime();
    $end = ($end_time instanceof DateTimeInterface) ? $end_time : new DateTime((string) $end_time);

    if ($now >= $end) {
        return 'Ended';
    }

    $diff = $now->diff($end);

    if ($diff->days > 0) {
        return $diff->days . 'd ' . $diff->h . 'h left';
    } elseif ($diff->h > 0) {
        return $diff->h . 'h ' . $diff->i . 'm left';
    } elseif ($diff->i > 0) {
        return $diff->i . 'm ' . $diff->s . 's left';
    } else {
        return $diff->s . 's left';
    }
}

/**
 * Sanitize user input for safe output
 *
 * @param mixed $data
 * @return string
 */
function sanitize_input($data): string
{
    return htmlspecialchars(trim((string) ($data ?? '')), ENT_QUOTES, 'UTF-8');
}
