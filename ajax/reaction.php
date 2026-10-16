<?php
/**
 * Spectator Cheer & Reaction AJAX Endpoint
 * File: ajax/reaction.php
 * 
 * Allows live spectators and bidders to send real-time cheers (🔥, 💎, 🚀, 👏, 🐋)
 * with rate limiting and aggregated item engagement stats.
 */

declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = Database::getPdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$itemId = (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0);

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid auction item ID is required.']);
    exit();
}

$validReactions = ['fire', 'gem', 'rocket', 'clap', 'whale', 'heart'];

if ($method === 'POST') {
    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please sign in to react.']);
        exit();
    }

    $reaction = strtolower(trim((string)($_POST['reaction'] ?? '')));
    if (!in_array($reaction, $validReactions, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid reaction type.']);
        exit();
    }

    // Rate limit: Max 15 reactions per minute per user
    $rl = check_rate_limit('reaction_' . $userId, 15, 60);
    if (!$rl['allowed']) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Reaction rate limit reached.']);
        exit();
    }
    record_rate_limit_attempt('reaction_' . $userId, 60);

    $stmt = $pdo->prepare("
        INSERT INTO item_reactions (item_id, user_id, reaction_type, created_at)
        VALUES (:item_id, :user_id, :type, NOW())
    ");
    $stmt->execute([
        ':item_id' => $itemId,
        ':user_id' => $userId,
        ':type'    => $reaction
    ]);

    http_response_code(201);
    echo json_encode([
        'success'  => true,
        'reaction' => $reaction,
        'item_id'  => $itemId
    ]);
    exit();

} else {
    // GET: Return reaction counts within last 30 minutes
    $stmt = $pdo->prepare("
        SELECT reaction_type, COUNT(*) as count 
        FROM item_reactions 
        WHERE item_id = :id AND created_at >= (NOW() - INTERVAL 30 MINUTE)
        GROUP BY reaction_type
    ");
    $stmt->execute([':id' => $itemId]);
    $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    http_response_code(200);
    echo json_encode([
        'success'   => true,
        'item_id'   => $itemId,
        'reactions' => $counts
    ]);
    exit();
}
