<?php
/**
 * Global Search Results - Geo Auction System
 * File: search.php
 */

declare(strict_types=1);

$require_auth = false;
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/database.php';
include_once __DIR__ . '/includes/functions.php';

$q = trim((string)($_GET['q'] ?? ''));

$pdo = Database::getPdo();
$params = [];
$where = "WHERE status = 'active' AND (is_deleted = 0 OR is_deleted IS NULL)";
if ($q !== '') {
    $where .= " AND (title LIKE :q1 OR description LIKE :q2)";
    $params[':q1'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
}

$stmt = $pdo->prepare("SELECT * FROM items $where ORDER BY id DESC");
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-muted">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none" style="color: rgb(4 120 87);">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Search</li>
                </ol>
            </nav>
            <h2 class="h4 fw-bold text-dark mb-0">
                <i class="fa-solid fa-magnifying-glass me-2" style="color: rgb(4 120 87);"></i>
                Search Results <?php if ($q !== ''): ?>for <span style="color: rgb(4 120 87);">"<?php echo htmlspecialchars($q); ?>"</span><?php endif; ?>
                <span class="badge bg-light text-muted fw-normal fs-6 border ms-2"><?php echo count($items); ?> found</span>
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
                    <i class="fa-solid fa-magnifying-glass fa-3x text-muted mb-3 opacity-50"></i>
                    <h3 class="h5 fw-bold text-dark mb-2">No Matching Auctions Found</h3>
                    <p class="text-muted mb-4">We couldn't find any active lots matching your search query. Try different keywords or browse all live categories.</p>
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

<?php include_once __DIR__ . '/includes/footer.php'; ?>
