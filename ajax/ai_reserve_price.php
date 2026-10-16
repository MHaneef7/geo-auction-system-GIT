<?php
/**
 * AJAX Endpoint: AI Dynamic Reserve Price Recommendation
 * File: ajax/ai_reserve_price.php
 * 
 * Takes starting_bid, category_id, brand, model, year, condition_type, city, item_id
 * and returns calculated recommended reserve price, valuation tiers, and factor breakdown.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/AiReservePriceService.php';

// Accept JSON payload or standard form POST
$input = $_POST;
$rawBody = file_get_contents('php://input');
if (!empty($rawBody)) {
    $jsonData = json_decode($rawBody, true);
    if (is_array($jsonData)) {
        $input = array_merge($input, $jsonData);
    }
}

$startingPrice = (float)($input['starting_price'] ?? $input['starting_bid'] ?? 0);
$categoryId    = (int)($input['category_id'] ?? 0);
$brand         = trim((string)($input['brand'] ?? ''));
$model         = trim((string)($input['model'] ?? ''));
$year          = !empty($input['year']) ? (int)$input['year'] : null;
$conditionType = trim((string)($input['condition_type'] ?? $input['condition'] ?? ''));
$city          = trim((string)($input['city'] ?? ''));
$itemId        = !empty($input['item_id']) ? (int)$input['item_id'] : null;

// Validation
if ($startingPrice <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid Starting Bid (PKR) greater than 0 first.'
    ]);
    exit;
}

try {
    $service = new AiReservePriceService();
    $result = $service->calculate([
        'starting_price' => $startingPrice,
        'category_id'    => $categoryId,
        'brand'          => $brand,
        'model'          => $model,
        'year'           => $year,
        'condition_type' => $conditionType,
        'city'           => $city,
        'item_id'        => $itemId
    ]);

    echo json_encode($result);
} catch (Throwable $e) {
    error_log('AI Reserve Price AJAX Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while calculating AI valuation. Please try again.',
        'error'   => $e->getMessage()
    ]);
}
exit;
