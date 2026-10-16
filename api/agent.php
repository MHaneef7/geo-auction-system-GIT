<?php
/**
 * Agent Profile API Endpoint
 * File: api/agent.php
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require_once __DIR__ . '/../includes/Database.php';

try {
    $db = new Database();
    $pdo = $db->connect();
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// -------------------------------------------------------------------------
// POST: Interaction Tracking (Email, Call, WhatsApp, View Properties)
// -------------------------------------------------------------------------
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?? $_POST;

    $agent_id = (int)($payload['agent_id'] ?? 1);
    $action = strtolower(trim((string)($payload['interaction_type'] ?? $payload['action'] ?? '')));
    $allowed = ['email', 'call', 'whatsapp', 'view_properties'];

    if (!in_array($action, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid interaction type']);
        exit();
    }

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("
            INSERT INTO `agent_interactions` (`agent_id`, `interaction_type`, `user_ip`, `created_at`)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$agent_id, $action, $ip]);

        echo json_encode([
            'success' => true,
            'recorded' => true,
            'agent_id' => $agent_id,
            'interaction_type' => $action
        ]);
        exit();
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not record interaction']);
        exit();
    }
}

// -------------------------------------------------------------------------
// GET: Fetch Agent Profile & Featured Community
// -------------------------------------------------------------------------
$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

try {
    $stmt = $pdo->prepare("
        SELECT 
            `id`, `name`, `avatar_url`, `banner_url`, `email`, `phone`, 
            `whatsapp_number`, `is_quality_lister`, `is_responsive_broker`, 
            `agency_name`, `agency_logo`, `agency_properties_url`
        FROM `agents` 
        WHERE `id` = ? 
        LIMIT 1
    ");
    $stmt->execute([$agent_id]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agent) {
        // Fallback to primary broker if requested ID is not found
        $fallback_stmt = $pdo->query("SELECT * FROM `agents` ORDER BY `id` ASC LIMIT 1");
        $agent = $fallback_stmt ? $fallback_stmt->fetch(PDO::FETCH_ASSOC) : null;
    }

    if (!$agent) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Agent profile not found']);
        exit();
    }

    // Cast booleans
    $agent['is_quality_lister'] = (bool)$agent['is_quality_lister'];
    $agent['is_responsive_broker'] = (bool)$agent['is_responsive_broker'];

    // Fetch featured community
    $c_stmt = $pdo->prepare("
        SELECT `id`, `title`, `description`, `image_url`, `target_url`
        FROM `featured_communities`
        WHERE `agent_id` = ?
        ORDER BY `id` DESC
        LIMIT 1
    ");
    $c_stmt->execute([(int)$agent['id']]);
    $community = $c_stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'title' => 'Mudon Al Ranim 8',
        'description' => 'See the community attractions and lifestyle',
        'image_url' => 'uploads/items/mudon_aerial.jpg',
        'target_url' => 'live_auction.php?id=243'
    ];

    $agent['featured_community'] = $community;

    echo json_encode([
        'success' => true,
        'agent' => $agent
    ]);
    exit();

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error querying agent profile: ' . $e->getMessage()]);
    exit();
}
