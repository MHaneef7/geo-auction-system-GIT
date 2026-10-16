<?php
/**
 * Category Auctions View - Geo Auction System
 * File: category.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$require_auth = false;
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/database.php';
include_once __DIR__ . '/includes/functions.php';

$pdo = Database::getPdo();
$cat_id = (int)($_GET['id'] ?? 0);
$cat_slug = trim($_GET['cat'] ?? '');
$category_name = 'All Categories';

$where_clauses = ["i.status = 'active'", "(i.is_deleted = 0 OR i.is_deleted IS NULL)"];
$params = [];

if ($cat_id > 0) {
    $where_clauses[] = "i.category_id = :cid";
    $params[':cid'] = $cat_id;
    
    try {
        $c_stmt = $pdo->prepare("SELECT name FROM categories WHERE id = :id LIMIT 1");
        $c_stmt->execute([':id' => $cat_id]);
        $c_row = $c_stmt->fetch(PDO::FETCH_ASSOC);
        if ($c_row) {
            $category_name = $c_row['name'];
        }
    } catch (\Throwable $e) {
        error_log("Category lookup error: " . $e->getMessage());
    }
} elseif (!empty($cat_slug)) {
    $where_clauses[] = "(c.slug LIKE :slug OR c.name LIKE :slug2 OR i.title LIKE :slug3)";
    $term = "%" . $cat_slug . "%";
    $params[':slug'] = $term;
    $params[':slug2'] = $term;
    $params[':slug3'] = $term;
    $category_name = ucfirst($cat_slug);
}

$where_sql = implode(" AND ", $where_clauses);
$sql = "
    SELECT i.*, c.name AS category_name 
    FROM items i 
    LEFT JOIN categories c ON c.id = i.category_id 
    WHERE $where_sql 
    ORDER BY i.id DESC
";

$items = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
    error_log("Category items fetch error: " . $e->getMessage());
    $items = [];
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-muted">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none" style="color: rgb(4 120 87);">Home</a></li>
                    <li class="breadcrumb-item"><a href="items.php" class="text-decoration-none text-muted">Categories</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars((string)$category_name); ?></li>
                </ol>
            </nav>
            <h2 class="h4 fw-bold text-dark mb-0">
                <i class="fa-solid fa-tags me-2" style="color: rgb(4 120 87);"></i>
                <?php echo htmlspecialchars((string)$category_name); ?>
            </h2>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Home
        </a>
    </div>

    <div class="row g-4">
        <?php if (!empty($items)): ?>
            <?php foreach ($items as $item): 
                $time_left = get_time_remaining($item['end_time'] ?? date('Y-m-d H:i:s'));
                $img = !empty($item['featured_image']) ? $item['featured_image'] : 'assets/image/Geo-auction.png';
            ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden transition-hover">
                        <div class="position-relative bg-light" style="height: 190px;">
                            <img src="<?php echo htmlspecialchars((string)$img); ?>" 
                                 alt="<?php echo htmlspecialchars((string)$item['title']); ?>" 
                                 class="w-100 h-100 object-fit-cover"
                                 onerror="this.onerror=null; this.src='assets/image/Geo-auction.png';">
                            <span class="badge position-absolute top-0 end-0 m-2 px-2.5 py-1.5 rounded-pill bg-dark bg-opacity-75 small">
                                <i class="fa-regular fa-clock me-1 text-warning"></i> <?php echo htmlspecialchars((string)$time_left); ?>
                            </span>
                        </div>
                        <div class="card-body p-3 d-flex flex-column">
                            <h5 class="card-title fs-6 fw-bold text-dark mb-2 text-truncate" title="<?php echo htmlspecialchars((string)$item['title']); ?>">
                                <?php echo htmlspecialchars((string)$item['title']); ?>
                            </h5>
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-baseline mb-3">
                                    <span class="small text-muted">Current Bid:</span>
                                    <span class="fs-5 fw-bold" style="color: rgb(4 120 87);">
                                        Rs. <?php echo number_format((float)($item['current_price'] ?? $item['starting_price']), 2); ?>
                                    </span>
                                </div>
                                <a href="live_auction.php?id=<?php echo (int)$item['id']; ?>" 
                                   class="btn text-white w-100 fw-semibold rounded-2 py-2"
                                   style="background: rgb(4 120 87);">
                                    <i class="fa-solid fa-gavel me-1"></i> View Lot & Bid
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <i class="fa-solid fa-box-open fa-3x text-muted mb-3 opacity-50"></i>
                    <h3 class="h5 fw-bold text-dark mb-2">No Active Auctions in this Category</h3>
                    <p class="text-muted mb-4">There are currently no live bidding lots matching this category.</p>
                    <div>
                        <a href="items.php" class="btn text-white px-4 py-2 rounded-pill fw-semibold" style="background: rgb(4 120 87);">
                            Browse All Live Auctions
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
include_once __DIR__ . '/includes/footer.php'; 
?>