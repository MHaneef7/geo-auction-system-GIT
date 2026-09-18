<?php
include_once __DIR__ . '/database.php';

function format_price($amount) {
    return '$' . number_format((float)$amount, 2);
}

function get_time_remaining($end_time) {
    $now = new DateTime();
    $end = new DateTime($end_time);
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

function sanitize_input($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
