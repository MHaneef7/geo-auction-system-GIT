<?php
/**
 * Category Management - Geo Auction System Admin Panel
 * File: admin/categories.php
 * 
 * Features:
 * - OOP PHP Architecture using PDO and Database Service
 * - Full CRUD: Add, Edit, Delete, and Status Toggle (Active / Inactive)
 * - Visual FontAwesome Icon Selector with categorized auction options & live preview
 * - Live Category Search & Status Filtering
 * - Real-time Item/Auction count per category
 * - Strong Security: Prepared statements, CSRF protection, input validation, XSS escaping
 * - Safe Delete: Prevents deletion of categories with linked items, displaying a clear warning
 * - Matches existing Geo-Auction Admin Panel styling and design system
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../includes/database.php';
include_once __DIR__ . '/../includes/auth.php';

// Enforce admin privileges using existing auth guard
require_admin('../index.php');

// CSRF Security Token Setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool {
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Format Font Awesome class safely for rendering
 */
if (!function_exists('format_fa_icon')) {
    function format_fa_icon(?string $icon): string {
        $icon = trim((string)$icon);
        if ($icon === '') {
            return 'fa-solid fa-tag';
        }
        if (preg_match('/^(fas|far|fab|fa-solid|fa-regular|fa-brands)\s+/i', $icon)) {
            return $icon;
        }
        if (strpos($icon, 'fa-') === 0) {
            return 'fa-solid ' . $icon;
        }
        return 'fa-solid fa-' . $icon;
    }
}

/**
 * Curated list of Font Awesome icons tailored for an Auction System
 */
$curated_icons = [
    // Vehicles & Transport
    ['class' => 'fa-car', 'name' => 'Cars & Vehicles', 'group' => 'Vehicles'],
    ['class' => 'fa-motorcycle', 'name' => 'Motorcycles & Bikes', 'group' => 'Vehicles'],
    ['class' => 'fa-bicycle', 'name' => 'Bicycles', 'group' => 'Vehicles'],
    ['class' => 'fa-truck', 'name' => 'Trucks & Haulers', 'group' => 'Vehicles'],
    ['class' => 'fa-tractor', 'name' => 'Agriculture & Farming', 'group' => 'Vehicles'],
    ['class' => 'fa-ship', 'name' => 'Boats & Marine', 'group' => 'Vehicles'],
    ['class' => 'fa-plane', 'name' => 'Aviation & Aircraft', 'group' => 'Vehicles'],

    // Real Estate & Property
    ['class' => 'fa-house', 'name' => 'Property & Real Estate', 'group' => 'Real Estate'],
    ['class' => 'fa-building', 'name' => 'Commercial Buildings', 'group' => 'Real Estate'],
    ['class' => 'fa-warehouse', 'name' => 'Warehouses & Industrial', 'group' => 'Real Estate'],
    ['class' => 'fa-mountain', 'name' => 'Land & Plots', 'group' => 'Real Estate'],

    // Electronics & Computers
    ['class' => 'fa-mobile-screen', 'name' => 'Phones & Tablets', 'group' => 'Electronics'],
    ['class' => 'fa-laptop', 'name' => 'Computers & Laptops', 'group' => 'Electronics'],
    ['class' => 'fa-tv', 'name' => 'Appliances & TVs', 'group' => 'Electronics'],
    ['class' => 'fa-camera', 'name' => 'Cameras & Photography', 'group' => 'Electronics'],
    ['class' => 'fa-headphones', 'name' => 'Audio & Headphones', 'group' => 'Electronics'],
    ['class' => 'fa-gamepad', 'name' => 'Gaming & Consoles', 'group' => 'Electronics'],

    // Luxury & Collectibles
    ['class' => 'fa-gem', 'name' => 'Jewelry & Diamonds', 'group' => 'Luxury'],
    ['class' => 'fa-clock', 'name' => 'Watches & Timepieces', 'group' => 'Luxury'],
    ['class' => 'fa-palette', 'name' => 'Art & Collectibles', 'group' => 'Luxury'],
    ['class' => 'fa-coins', 'name' => 'Coins & Currency', 'group' => 'Luxury'],
    ['class' => 'fa-hashtag', 'name' => 'Prestige VIP Plates', 'group' => 'Luxury'],

    // Home, Living & Fashion
    ['class' => 'fa-couch', 'name' => 'Furniture & Decor', 'group' => 'Home & Fashion'],
    ['class' => 'fa-shirt', 'name' => 'Fashion & Apparel', 'group' => 'Home & Fashion'],
    ['class' => 'fa-glasses', 'name' => 'Eyewear & Accessories', 'group' => 'Home & Fashion'],
    ['class' => 'fa-guitar', 'name' => 'Musical Instruments', 'group' => 'Home & Fashion'],
    ['class' => 'fa-futbol', 'name' => 'Sports & Outdoors', 'group' => 'Home & Fashion'],
    ['class' => 'fa-book', 'name' => 'Books & Antiques', 'group' => 'Home & Fashion'],

    // Industrial, Tools & General
    ['class' => 'fa-screwdriver-wrench', 'name' => 'Tools & Equipment', 'group' => 'Industrial & General'],
    ['class' => 'fa-hammer', 'name' => 'Machinery & Hardware', 'group' => 'Industrial & General'],
    ['class' => 'fa-box', 'name' => 'General Merchandise', 'group' => 'Industrial & General'],
    ['class' => 'fa-boxes-stacked', 'name' => 'Wholesale & Surplus', 'group' => 'Industrial & General'],
    ['class' => 'fa-tags', 'name' => 'Other Auctions', 'group' => 'Industrial & General'],
];

/**
 * Clean OOP Service for Category Operations
 */
if (!class_exists('CategoryService')) {
class CategoryService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    /**
     * Get all categories with auction/item counts, search, and status filter
     */
    public function getAll(string $search = '', string $status = ''): array
    {
        $sql = "SELECT c.*, 
                       (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id) AS item_count
                FROM categories c
                WHERE 1=1";
        $params = [];

        if ($status === 'active' || $status === 'inactive') {
            $sql .= " AND c.status = :status";
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $sql .= " AND (c.name LIKE :search_name OR c.description LIKE :search_desc OR c.slug LIKE :search_slug)";
            $term = '%' . $search . '%';
            $params[':search_name'] = $term;
            $params[':search_desc'] = $term;
            $params[':search_slug'] = $term;
        }

        $sql .= " ORDER BY c.sort_order ASC, c.name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get aggregate category & auction stats
     */
    public function getStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) AS total_categories,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_categories,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_categories
            FROM categories
        ");
        $cat_stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $item_stmt = $this->pdo->query("SELECT COUNT(*) AS total_items FROM items WHERE category_id IS NOT NULL");
        $item_stats = $item_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'       => (int)($cat_stats['total_categories'] ?? 0),
            'active'      => (int)($cat_stats['active_categories'] ?? 0),
            'inactive'    => (int)($cat_stats['inactive_categories'] ?? 0),
            'total_items' => (int)($item_stats['total_items'] ?? 0),
        ];
    }

    /**
     * Get category by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id) AS item_count
            FROM categories c
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get count of auctions/items linked to a category
     */
    public function getAuctionCount(int $id): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS c FROM items WHERE category_id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['c'] ?? 0);
    }

    /**
     * Check if a category name already exists (case-insensitive)
     */
    public function isNameDuplicate(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM categories WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))";
        $params = [':name' => $name];
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Generate a unique, URL-safe slug
     */
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('~[^\pL\d]+~u', '-', $slug);
        if (function_exists('iconv')) {
            $slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
        }
        $slug = preg_replace('~[^-\w]+~', '', $slug);
        $slug = trim($slug, '-');
        $slug = preg_replace('~-+~', '-', $slug);
        if (empty($slug)) {
            $slug = 'category-' . time();
        }

        $base_slug = $slug;
        $counter = 1;

        while (true) {
            $sql = "SELECT id FROM categories WHERE slug = :slug";
            $params = [':slug' => $slug];
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = $excludeId;
            }
            $sql .= " LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch() === false) {
                break;
            }
            $counter++;
            $slug = $base_slug . '-' . $counter;
        }

        return $slug;
    }

    /**
     * Create a new category
     */
    public function create(array $data): array
    {
        $name        = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $icon        = trim($data['icon'] ?? 'fa-tag');
        $status      = strtolower(trim($data['status'] ?? 'active'));

        if ($name === '') {
            return ['success' => false, 'message' => 'Category name is required.'];
        }
        if (mb_strlen($name) > 100) {
            return ['success' => false, 'message' => 'Category name cannot exceed 100 characters.'];
        }
        if ($this->isNameDuplicate($name)) {
            return ['success' => false, 'message' => "A category with the name \"{$name}\" already exists."];
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }
        if (empty($icon)) {
            $icon = 'fa-tag';
        }

        $slug = $this->generateUniqueSlug(!empty($data['slug']) ? $data['slug'] : $name);

        $stmt = $this->pdo->prepare("
            INSERT INTO categories (name, slug, description, icon, status, sort_order, created_at, updated_at)
            VALUES (:name, :slug, :description, :icon, :status, 0, NOW(), NOW())
        ");
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':description' => !empty($description) ? $description : null,
            ':icon'        => $icon,
            ':status'      => $status,
        ]);

        return [
            'success' => true,
            'message' => "Category \"{$name}\" has been added successfully.",
            'id'      => (int)$this->pdo->lastInsertId(),
        ];
    }

    /**
     * Update an existing category
     */
    public function update(int $id, array $data): array
    {
        $cat = $this->getById($id);
        if (!$cat) {
            return ['success' => false, 'message' => 'Category not found.'];
        }

        $name        = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $icon        = trim($data['icon'] ?? 'fa-tag');
        $status      = strtolower(trim($data['status'] ?? 'active'));

        if ($name === '') {
            return ['success' => false, 'message' => 'Category name is required.'];
        }
        if (mb_strlen($name) > 100) {
            return ['success' => false, 'message' => 'Category name cannot exceed 100 characters.'];
        }
        if ($this->isNameDuplicate($name, $id)) {
            return ['success' => false, 'message' => "Another category with the name \"{$name}\" already exists."];
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }
        if (empty($icon)) {
            $icon = 'fa-tag';
        }

        // Slug management
        $custom_slug = trim($data['slug'] ?? '');
        if ($custom_slug !== '') {
            $slug = $this->generateUniqueSlug($custom_slug, $id);
        } elseif (strtolower($name) !== strtolower($cat['name'])) {
            $slug = $this->generateUniqueSlug($name, $id);
        } else {
            $slug = $cat['slug'];
        }

        $stmt = $this->pdo->prepare("
            UPDATE categories 
            SET name = :name, slug = :slug, description = :description, icon = :icon, status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':description' => !empty($description) ? $description : null,
            ':icon'        => $icon,
            ':status'      => $status,
            ':id'          => $id,
        ]);

        return [
            'success' => true,
            'message' => "Category \"{$name}\" was updated successfully.",
        ];
    }

    /**
     * Toggle Category status (Active <-> Inactive)
     */
    public function toggleStatus(int $id): array
    {
        $cat = $this->getById($id);
        if (!$cat) {
            return ['success' => false, 'message' => 'Category not found.'];
        }

        $new_status = ($cat['status'] === 'active') ? 'inactive' : 'active';
        $stmt = $this->pdo->prepare("UPDATE categories SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':status' => $new_status, ':id' => $id]);

        return [
            'success'    => true,
            'message'    => "Category \"{$cat['name']}\" status set to " . ucfirst($new_status) . ".",
            'new_status' => $new_status,
        ];
    }

    /**
     * Delete Category with relational integrity safety check
     */
    public function delete(int $id): array
    {
        $cat = $this->getById($id);
        if (!$cat) {
            return ['success' => false, 'message' => 'Category not found.'];
        }

        // Check if any auction/item is using this category
        $item_count = $this->getAuctionCount($id);
        if ($item_count > 0) {
            return [
                'success'    => false,
                'message'    => "This category contains {$item_count} auction item(s) and cannot be deleted. Please move or remove the associated items first.",
                'is_blocked' => true,
            ];
        }

        // Check if any child categories exist
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS c FROM categories WHERE parent_id = :id");
        $stmt->execute([':id' => $id]);
        $child_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        if ($child_count > 0) {
            return [
                'success'    => false,
                'message'    => "This category contains {$child_count} subcategory/subcategories and cannot be deleted. Please reassign or remove them first.",
                'is_blocked' => true,
            ];
        }

        $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return [
            'success' => true,
            'message' => "Category \"{$cat['name']}\" was deleted successfully.",
        ];
    }
}
}

// Initialize Category Service
$categoryService = new CategoryService();

$msg = '';
$msg_type = 'success'; // 'success', 'warning', or 'danger'

// -------------------------------------------------------------
// POST Request Handling (Add, Edit, Toggle Status, Delete)
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    $token  = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $msg = 'Security token invalid or expired. Please refresh the page and try again.';
        $msg_type = 'danger';
    } else {
        if ($action === 'add') {
            $result = $categoryService->create($_POST);
            $msg = $result['message'];
            $msg_type = $result['success'] ? 'success' : 'danger';
        } elseif ($action === 'edit') {
            $cid = (int)($_POST['cat_id'] ?? 0);
            $result = $categoryService->update($cid, $_POST);
            $msg = $result['message'];
            $msg_type = $result['success'] ? 'success' : 'danger';
        } elseif ($action === 'toggle_status') {
            $cid = (int)($_POST['cat_id'] ?? 0);
            $result = $categoryService->toggleStatus($cid);
            $msg = $result['message'];
            $msg_type = $result['success'] ? 'success' : 'danger';
        } elseif ($action === 'delete') {
            $cid = (int)($_POST['cat_id'] ?? 0);
            $result = $categoryService->delete($cid);
            $msg = $result['message'];
            $msg_type = $result['success'] ? 'success' : (!empty($result['is_blocked']) ? 'warning' : 'danger');
        }
    }
}

// -------------------------------------------------------------
// GET Request Data (Filters & Categories List)
// -------------------------------------------------------------
$search_query  = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$categories = $categoryService->getAll($search_query, $status_filter);
$stats      = $categoryService->getStats();

// Check if a direct edit modal trigger is requested via GET (?edit=ID)
$direct_edit_cat = null;
if (isset($_GET['edit'])) {
    $direct_edit_cat = $categoryService->getById((int)$_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Panel</title>
    <!-- Bootstrap 5 & FontAwesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: rgb(4 120 87);
            --primary-green-hover: rgb(3 98 71);
            --primary-light: rgba(4, 120, 87, 0.08);
        }
        body { 
            background-color: #f8f9fa; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1f2937;
        }
        .sidebar { 
            height: 100vh; 
            background-color: #1a1d21; 
            color: #fff; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 250px; 
            padding-top: 20px; 
            overflow-y: auto; 
            z-index: 100;
        }
        .sidebar a { 
            color: #8fa8b8; 
            text-decoration: none; 
            padding: 12px 20px; 
            display: block; 
            transition: 0.3s; 
            font-size: 14px;
        }
        .sidebar a:hover, .sidebar a.active { 
            color: #fff; 
            background-color: var(--primary-green); 
        }
        .sidebar .nav-category { 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            color: #4a6a7a; 
            padding: 10px 20px; 
            margin-top: 10px; 
        }
        .main-content { 
            margin-left: 250px; 
            padding: 30px; 
        }
        @media(max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
        .card-stat { 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.04); 
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.06);
        }
        /* Icon Selector Card Styling */
        .icon-choice-card {
            border: 1px solid #e5e7eb;
            background: #ffffff;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
            user-select: none;
        }
        .icon-choice-card:hover {
            border-color: var(--primary-green) !important;
            background-color: #f0fdf4 !important;
            transform: translateY(-1px);
        }
        .icon-choice-card.active-icon {
            border: 2px solid var(--primary-green) !important;
            background-color: rgba(4, 120, 87, 0.08) !important;
            box-shadow: 0 0 0 2px rgba(4, 120, 87, 0.2);
        }
        .icon-choice-card.active-icon .icon-name-text {
            color: var(--primary-green) !important;
            font-weight: 700;
        }
        .icon-group-pill {
            cursor: pointer;
            font-size: 0.72rem;
            transition: all 0.15s ease;
        }
        .icon-group-pill.active {
            background-color: var(--primary-green) !important;
            color: #ffffff !important;
            border-color: var(--primary-green) !important;
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h3 class="text-center text-white mb-4"><i class="fas fa-gavel me-2"></i>AuctionAdmin</h3>
        <div class="nav-category">Core</div>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        <a href="../user/dashboard.php"><i class="fas fa-house me-2"></i> User Portal</a>
        
        <div class="nav-category">Management</div>
        <a href="users.php"><i class="fas fa-users me-2"></i> Users</a>
        <a href="categories.php" class="active"><i class="fas fa-tags me-2"></i> Categories</a>
        <a href="items.php"><i class="fas fa-box-open me-2"></i> Items</a>
        <a href="bids.php"><i class="fas fa-hand-holding-usd me-2"></i> Bids</a>
        
        <div class="nav-category">System</div>
        <a href="setting.php"><i class="fas fa-database me-2"></i> Database & Backup</a>
        <a href="reports.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        <a href="logout.php" class="text-danger mt-4"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Top Title Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="color: #111827;">
                    <i class="fas fa-tags me-2 text-success"></i>Manage Categories
                </h2>
                <p class="text-muted small mb-0">Organize auction catalog taxonomy, visual icons, and status controls.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn text-white fw-semibold px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2" style="background-color: var(--primary-green); border-color: var(--primary-green);" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus-circle"></i> + Add Category
                </button>
                <span class="text-muted bg-white px-3 py-2 border rounded shadow-sm d-none d-sm-inline-block">Logged in as: <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong></span>
            </div>
        </div>

        <!-- Alert Notification -->
        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas <?php echo $msg_type === 'success' ? 'fa-circle-check text-success' : ($msg_type === 'warning' ? 'fa-triangle-exclamation text-warning' : 'fa-circle-exclamation text-danger'); ?> me-2"></i>
                <?php echo htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Stats Tabs -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <a href="categories.php" class="text-decoration-none">
                    <div class="card card-stat p-3 rounded-3 bg-white h-100">
                        <span class="text-muted small fw-semibold">Total Categories</span>
                        <h3 class="fw-bold text-dark mb-0"><?php echo number_format($stats['total']); ?></h3>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="categories.php?status=active" class="text-decoration-none">
                    <div class="card card-stat p-3 rounded-3 bg-white h-100 border-start border-success border-4">
                        <span class="text-muted small fw-semibold">Active Categories</span>
                        <h3 class="fw-bold text-success mb-0"><?php echo number_format($stats['active']); ?></h3>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="categories.php?status=inactive" class="text-decoration-none">
                    <div class="card card-stat p-3 rounded-3 bg-white h-100 border-start border-secondary border-4">
                        <span class="text-muted small fw-semibold">Inactive Categories</span>
                        <h3 class="fw-bold text-secondary mb-0"><?php echo number_format($stats['inactive']); ?></h3>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="items.php" class="text-decoration-none">
                    <div class="card card-stat p-3 rounded-3 bg-white h-100 border-start border-primary border-4">
                        <span class="text-muted small fw-semibold">Total Auction Items</span>
                        <h3 class="fw-bold text-primary mb-0"><?php echo number_format($stats['total_items']); ?></h3>
                    </div>
                </a>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" placeholder="Search categories..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select shadow-none">
                            <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn text-white w-100 fw-semibold" style="background-color: var(--primary-green);">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <a href="categories.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Categories Table Card -->
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 ps-3">#</th>
                                <th class="py-3">Icon</th>
                                <th class="py-3">Category Name</th>
                                <th class="py-3">Description</th>
                                <th class="py-3">Auctions / Items</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Created At</th>
                                <th class="py-3 text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <!-- ID -->
                                        <td class="ps-3 text-muted fw-semibold">#<?php echo $cat['id']; ?></td>

                                        <!-- Icon Column -->
                                        <td>
                                            <div class="d-inline-flex align-items-center justify-content-center rounded-3 bg-success bg-opacity-10 text-success" style="width: 44px; height: 44px; font-size: 1.25rem;">
                                                <i class="<?php echo htmlspecialchars(format_fa_icon($cat['icon'] ?? 'fa-tag')); ?>"></i>
                                            </div>
                                        </td>

                                        <!-- Category Name & Slug -->
                                        <td>
                                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($cat['name']); ?></div>
                                            <code class="text-muted small">/<?php echo htmlspecialchars($cat['slug']); ?></code>
                                        </td>

                                        <!-- Description -->
                                        <td>
                                            <div class="text-muted small text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($cat['description'] ?? ''); ?>">
                                                <?php echo htmlspecialchars(!empty($cat['description']) ? $cat['description'] : '—'); ?>
                                            </div>
                                        </td>

                                        <!-- Auctions / Items Count -->
                                        <td>
                                            <?php if ((int)$cat['item_count'] > 0): ?>
                                                <a href="items.php?search=<?php echo urlencode($cat['name']); ?>" class="badge bg-success bg-opacity-10 text-success text-decoration-none px-2.5 py-1.5 rounded-pill fw-semibold">
                                                    <i class="fas fa-gavel me-1"></i><?php echo number_format($cat['item_count']); ?> Items
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border px-2.5 py-1.5 rounded-pill">
                                                    <i class="fas fa-box me-1"></i>0 Items
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status Badge -->
                                        <td>
                                            <?php if ($cat['status'] === 'active'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1.5 rounded-pill">
                                                    <i class="fas fa-circle-check me-1"></i>Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2.5 py-1.5 rounded-pill">
                                                    <i class="fas fa-circle-xmark me-1"></i>Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Created At -->
                                        <td>
                                            <span class="text-muted small">
                                                <i class="far fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($cat['created_at'])); ?>
                                            </span>
                                        </td>

                                        <!-- Action Buttons -->
                                        <td class="text-end pe-3">
                                            <!-- Quick Toggle Status Button -->
                                            <form method="POST" class="d-inline" title="Toggle Status (Active/Inactive)">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $cat['status'] === 'active' ? 'btn-outline-secondary' : 'btn-outline-success'; ?> me-1" title="<?php echo $cat['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas <?php echo $cat['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                </button>
                                            </form>

                                            <!-- Edit Modal Button -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-warning me-1 edit-cat-trigger"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editCategoryModal"
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                                    data-slug="<?php echo htmlspecialchars($cat['slug']); ?>"
                                                    data-description="<?php echo htmlspecialchars($cat['description'] ?? ''); ?>"
                                                    data-icon="<?php echo htmlspecialchars($cat['icon'] ?? 'fa-tag'); ?>"
                                                    data-status="<?php echo htmlspecialchars($cat['status']); ?>"
                                                    data-items="<?php echo (int)$cat['item_count']; ?>"
                                                    title="Edit Category">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <!-- Delete Modal Button -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger delete-cat-trigger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteCategoryModal"
                                                    data-id="<?php echo $cat['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                                    data-icon="<?php echo htmlspecialchars($cat['icon'] ?? 'fa-tag'); ?>"
                                                    data-items="<?php echo (int)$cat['item_count']; ?>"
                                                    title="Delete Category">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="text-muted mb-3"><i class="fas fa-tags fa-3x text-secondary"></i></div>
                                        <h5 class="fw-bold text-dark">No categories found</h5>
                                        <p class="text-muted small mb-3">No categories match your search or filter criteria.</p>
                                        <a href="categories.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">Showing <strong><?php echo count($categories); ?></strong> of <strong><?php echo $stats['total']; ?></strong> categories</span>
                <span class="text-muted small">Active: <strong class="text-success"><?php echo $stats['active']; ?></strong> &bull; Inactive: <strong class="text-secondary"><?php echo $stats['inactive']; ?></strong></span>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 1. ADD CATEGORY MODAL                                         -->
    <!-- ============================================================== -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div class="modal-header border-bottom py-3 px-4">
                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="addCategoryModalLabel">
                            <i class="fas fa-plus-circle text-success me-1"></i> Add New Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Category Name -->
                            <div class="col-md-7">
                                <label class="form-label fw-semibold text-dark">Category Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="add_cat_name" class="form-control" required placeholder="e.g. Sports Motorcycles" maxlength="100">
                                <div class="form-text">Clean unique category title for auction listings.</div>
                            </div>

                            <!-- Status -->
                            <div class="col-md-5">
                                <label class="form-label fw-semibold text-dark">Initial Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select" required>
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <div class="form-text">Active categories are visible to bidders immediately.</div>
                            </div>

                            <!-- Generated Slug Preview -->
                            <div class="col-12">
                                <div class="bg-light p-2.5 rounded-3 border d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <span class="small text-muted">
                                        <i class="fas fa-link me-1"></i> URL Slug: <code id="add_slug_preview" class="text-success fw-bold">sports-motorcycles</code>
                                    </span>
                                    <button class="btn btn-link btn-sm p-0 text-decoration-none small text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#addCustomSlugCollapse">
                                        <i class="fas fa-pen me-1"></i> Custom Slug
                                    </button>
                                </div>
                                <div class="collapse mt-2" id="addCustomSlugCollapse">
                                    <input type="text" name="slug" id="add_custom_slug" class="form-control form-control-sm font-monospace" placeholder="Leave empty for auto-generated slug">
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Brief summary of auctions, brands, or products in this category..."></textarea>
                            </div>

                            <!-- Visual Icon Selector Component -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark d-flex justify-content-between align-items-center">
                                    <span>Category Icon <span class="text-danger">*</span></span>
                                    <small class="text-muted fw-normal">Select an icon for visual branding</small>
                                </label>

                                <!-- Live Selected Icon Display -->
                                <div class="p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between mb-3 shadow-sm icon-preview-container" id="add_icon_preview_container">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-3 bg-white border d-flex align-items-center justify-content-center shadow-sm preview-icon-badge" style="width: 48px; height: 48px; font-size: 1.5rem; color: var(--primary-green);">
                                            <i class="fa-solid fa-tags"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Selected Icon</div>
                                            <div class="fw-bold text-dark preview-icon-label fs-6">Other Auctions</div>
                                            <div class="small text-muted font-monospace preview-icon-class">fa-tags</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill">
                                        <i class="fas fa-check-circle me-1"></i>Selected
                                    </span>
                                </div>

                                <!-- Hidden Input to Store Selected Class -->
                                <input type="hidden" name="icon" id="add_icon_input" value="fa-tags">

                                <!-- Icon Search & Group Tabs -->
                                <div class="mb-2">
                                    <div class="input-group input-group-sm mb-2">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control border-start-0 shadow-none icon-search-box" data-target-modal="add" placeholder="Search icons (e.g. car, motorcycle, laptop, gem, tool)...">
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 icon-group-tabs" data-target-modal="add">
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill active" data-group="all">All</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Vehicles">Vehicles</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Real Estate">Real Estate</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Electronics">Electronics</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Luxury">Luxury</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Home & Fashion">Home & Fashion</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Industrial & General">Tools & General</button>
                                    </div>
                                </div>

                                <!-- Scrollable Visual Icon Grid -->
                                <div class="icon-selector-grid p-2 border rounded-3 bg-white" data-target-modal="add" style="max-height: 210px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px;">
                                    <?php foreach ($curated_icons as $ico): ?>
                                        <div class="icon-choice-card rounded-3 p-2 text-center <?php echo $ico['class'] === 'fa-tags' ? 'active-icon' : ''; ?>"
                                             data-icon-class="<?php echo htmlspecialchars($ico['class']); ?>"
                                             data-icon-name="<?php echo htmlspecialchars($ico['name']); ?>"
                                             data-icon-group="<?php echo htmlspecialchars($ico['group']); ?>">
                                            <div class="fs-4 text-success mb-1">
                                                <i class="<?php echo htmlspecialchars(format_fa_icon($ico['class'])); ?>"></i>
                                            </div>
                                            <div class="small text-truncate icon-name-text" style="font-size: 0.76rem;">
                                                <?php echo htmlspecialchars($ico['name']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white px-4 fw-bold" style="background-color: var(--primary-green);">
                            <i class="fas fa-plus-circle me-1"></i> Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 2. EDIT CATEGORY MODAL                                        -->
    <!-- ============================================================== -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow rounded-4">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="cat_id" id="edit_cat_id" value="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="modal-header border-bottom py-3 px-4">
                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="editCategoryModalLabel">
                            <i class="fas fa-edit text-warning me-1"></i> Edit Category
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Category Name -->
                            <div class="col-md-7">
                                <label class="form-label fw-semibold text-dark">Category Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_cat_name" class="form-control" required maxlength="100">
                                <div class="form-text">Category title displayed on listings and filters.</div>
                            </div>

                            <!-- Status -->
                            <div class="col-md-5">
                                <label class="form-label fw-semibold text-dark">Status <span class="text-danger">*</span></label>
                                <select name="status" id="edit_cat_status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <div class="form-text">Inactive categories hide from public filters without breaking existing lots.</div>
                            </div>

                            <!-- Slug Management -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark small mb-1">URL Slug</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted">/category/</span>
                                    <input type="text" name="slug" id="edit_cat_slug" class="form-control font-monospace" placeholder="category-slug">
                                </div>
                                <div class="form-text">Used for routing (e.g. <code>category.php?cat=cars</code>). Unique URL identifier.</div>
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Description</label>
                                <textarea name="description" id="edit_cat_description" class="form-control" rows="3" placeholder="Category description..."></textarea>
                            </div>

                            <!-- Visual Icon Selector Component for Edit Modal -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark d-flex justify-content-between align-items-center">
                                    <span>Category Icon <span class="text-danger">*</span></span>
                                    <small class="text-muted fw-normal">Choose new icon or keep current</small>
                                </label>

                                <!-- Live Selected Icon Display -->
                                <div class="p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between mb-3 shadow-sm icon-preview-container" id="edit_icon_preview_container">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-3 bg-white border d-flex align-items-center justify-content-center shadow-sm preview-icon-badge" style="width: 48px; height: 48px; font-size: 1.5rem; color: var(--primary-green);">
                                            <i class="fa-solid fa-tag"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Current Selected Icon</div>
                                            <div class="fw-bold text-dark preview-icon-label fs-6">Loading...</div>
                                            <div class="small text-muted font-monospace preview-icon-class">fa-tag</div>
                                        </div>
                                    </div>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill">
                                        <i class="fas fa-check-circle me-1"></i>Selected
                                    </span>
                                </div>

                                <!-- Hidden Input to Store Selected Class -->
                                <input type="hidden" name="icon" id="edit_icon_input" value="fa-tag">

                                <!-- Icon Search & Group Tabs -->
                                <div class="mb-2">
                                    <div class="input-group input-group-sm mb-2">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control border-start-0 shadow-none icon-search-box" data-target-modal="edit" placeholder="Search icons (e.g. car, watch, real estate, electronics)...">
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 icon-group-tabs" data-target-modal="edit">
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill active" data-group="all">All</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Vehicles">Vehicles</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Real Estate">Real Estate</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Electronics">Electronics</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Luxury">Luxury</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Home & Fashion">Home & Fashion</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0.5 px-2.5 rounded-pill icon-group-pill" data-group="Industrial & General">Tools & General</button>
                                    </div>
                                </div>

                                <!-- Scrollable Visual Icon Grid -->
                                <div class="icon-selector-grid p-2 border rounded-3 bg-white" data-target-modal="edit" style="max-height: 210px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px;">
                                    <?php foreach ($curated_icons as $ico): ?>
                                        <div class="icon-choice-card rounded-3 p-2 text-center"
                                             data-icon-class="<?php echo htmlspecialchars($ico['class']); ?>"
                                             data-icon-name="<?php echo htmlspecialchars($ico['name']); ?>"
                                             data-icon-group="<?php echo htmlspecialchars($ico['group']); ?>">
                                            <div class="fs-4 text-success mb-1">
                                                <i class="<?php echo htmlspecialchars(format_fa_icon($ico['class'])); ?>"></i>
                                            </div>
                                            <div class="small text-truncate icon-name-text" style="font-size: 0.76rem;">
                                                <?php echo htmlspecialchars($ico['name']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white px-4 fw-bold" style="background-color: var(--primary-green);">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- 3. DELETE CATEGORY MODAL (WITH SAFE INTEGRITY CHECK)          -->
    <!-- ============================================================== -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom py-3 px-4">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="deleteCategoryModalLabel">
                        <i class="fas fa-trash-alt text-danger me-1"></i> Delete Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4 text-center">
                    <!-- Case A: In Use (Blocked with Warning) -->
                    <div id="delete_blocked_view" class="d-none">
                        <div class="mb-3">
                            <div class="d-inline-flex p-3 rounded-circle bg-warning bg-opacity-10 text-warning fs-1">
                                <i class="fas fa-triangle-exclamation"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Category Cannot Be Deleted</h5>
                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-dark text-start small mb-3">
                            <i class="fas fa-info-circle me-1 text-warning"></i>
                            <span id="delete_blocked_text">This category contains auction items and cannot be deleted. Please move or remove the associated items first.</span>
                        </div>
                        <p class="text-muted small mb-0">
                            To preserve marketplace audit integrity and prevent broken bidder links, categories with active or historical auctions cannot be removed.
                        </p>
                    </div>

                    <!-- Case B: Zero Items (Allowed Deletion) -->
                    <div id="delete_allowed_view">
                        <div class="mb-3">
                            <div class="d-inline-flex p-3 rounded-circle bg-danger bg-opacity-10 text-danger fs-1">
                                <i class="fas fa-trash-can"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Confirm Category Deletion</h5>
                        <p class="text-muted mb-2">
                            Are you sure you want to delete category <strong id="delete_category_name" class="text-dark"></strong>?
                        </p>
                        <p class="text-muted small mb-0">
                            This category currently has <strong class="text-success">0</strong> auction items. This action will permanently remove it.
                        </p>
                    </div>
                </div>

                <div class="modal-footer border-top py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>

                    <!-- Blocked View Action Button -->
                    <div id="delete_blocked_actions" class="d-none">
                        <a id="view_category_items_btn" href="items.php" class="btn text-white fw-semibold px-4" style="background-color: var(--primary-green);">
                            <i class="fas fa-box-open me-1"></i> View Associated Items
                        </a>
                    </div>

                    <!-- Allowed View Submit Form -->
                    <form id="delete_category_form" method="POST" class="d-inline m-0">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="cat_id" id="delete_cat_id" value="0">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <button type="submit" id="delete_confirm_btn" class="btn btn-danger px-4 fw-semibold">
                            <i class="fas fa-trash-alt me-1"></i> Delete Category
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Page JavaScript Logic -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Icon Master Map from PHP
        const iconList = <?php echo json_encode($curated_icons); ?>;

        /**
         * Safe Font Awesome class resolver
         */
        function resolveFaClass(iconClass) {
            iconClass = (iconClass || '').trim();
            if (!iconClass) return 'fa-solid fa-tag';
            if (/^(fas|far|fab|fa-solid|fa-regular|fa-brands)\s+/i.test(iconClass)) {
                return iconClass;
            }
            if (iconClass.startsWith('fa-')) {
                return 'fa-solid ' + iconClass;
            }
            return 'fa-solid fa-' + iconClass;
        }

        /**
         * Find icon name from class
         */
        function getIconName(iconClass) {
            const found = iconList.find(item => item.class === iconClass);
            if (found) return found.name;
            return iconClass.replace(/^fa-/, '').replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        /**
         * Initialize Interactive Icon Picker for a target modal ('add' or 'edit')
         */
        function setupIconPicker(modalType) {
            const grid = document.querySelector(`.icon-selector-grid[data-target-modal="${modalType}"]`);
            const searchBox = document.querySelector(`.icon-search-box[data-target-modal="${modalType}"]`);
            const groupTabs = document.querySelectorAll(`.icon-group-tabs[data-target-modal="${modalType}"] .icon-group-pill`);
            const inputField = document.getElementById(`${modalType}_icon_input`);
            const previewContainer = document.getElementById(`${modalType}_icon_preview_container`);

            if (!grid || !inputField || !previewContainer) return;

            const previewBadge = previewContainer.querySelector('.preview-icon-badge');
            const previewLabel = previewContainer.querySelector('.preview-icon-label');
            const previewClass = previewContainer.querySelector('.preview-icon-class');

            // Select an icon
            function selectIcon(iconClass, iconName) {
                inputField.value = iconClass;
                if (previewBadge) previewBadge.innerHTML = `<i class="${resolveFaClass(iconClass)}"></i>`;
                if (previewLabel) previewLabel.textContent = iconName || getIconName(iconClass);
                if (previewClass) previewClass.textContent = iconClass;

                // Highlight card
                grid.querySelectorAll('.icon-choice-card').forEach(card => {
                    if (card.dataset.iconClass === iconClass) {
                        card.classList.add('active-icon');
                    } else {
                        card.classList.remove('active-icon');
                    }
                });
            }

            // Click on an icon card
            grid.addEventListener('click', function (e) {
                const card = e.target.closest('.icon-choice-card');
                if (!card) return;
                const iconClass = card.dataset.iconClass;
                const iconName = card.dataset.iconName;
                selectIcon(iconClass, iconName);
            });

            // Search filter
            if (searchBox) {
                searchBox.addEventListener('input', function () {
                    const query = this.value.toLowerCase().trim();
                    const activeGroup = document.querySelector(`.icon-group-tabs[data-target-modal="${modalType}"] .icon-group-pill.active`)?.dataset.group || 'all';

                    grid.querySelectorAll('.icon-choice-card').forEach(card => {
                        const name = (card.dataset.iconName || '').toLowerCase();
                        const cls = (card.dataset.iconClass || '').toLowerCase();
                        const grp = card.dataset.iconGroup || '';

                        const matchesQuery = !query || name.includes(query) || cls.includes(query);
                        const matchesGroup = activeGroup === 'all' || grp === activeGroup;

                        if (matchesQuery && matchesGroup) {
                            card.classList.remove('d-none');
                        } else {
                            card.classList.add('d-none');
                        }
                    });
                });
            }

            // Category group pills
            groupTabs.forEach(pill => {
                pill.addEventListener('click', function () {
                    groupTabs.forEach(p => p.classList.remove('active'));
                    this.classList.add('active');

                    const selectedGroup = this.dataset.group;
                    const query = searchBox ? searchBox.value.toLowerCase().trim() : '';

                    grid.querySelectorAll('.icon-choice-card').forEach(card => {
                        const name = (card.dataset.iconName || '').toLowerCase();
                        const cls = (card.dataset.iconClass || '').toLowerCase();
                        const grp = card.dataset.iconGroup || '';

                        const matchesQuery = !query || name.includes(query) || cls.includes(query);
                        const matchesGroup = selectedGroup === 'all' || grp === selectedGroup;

                        if (matchesQuery && matchesGroup) {
                            card.classList.remove('d-none');
                        } else {
                            card.classList.add('d-none');
                        }
                    });
                });
            });

            return { selectIcon };
        }

        const addPicker = setupIconPicker('add');
        const editPicker = setupIconPicker('edit');

        // Automatic slug generator for Add Category Modal
        const addNameInput = document.getElementById('add_cat_name');
        const addSlugPreview = document.getElementById('add_slug_preview');
        const addCustomSlug = document.getElementById('add_custom_slug');

        function generateSlug(text) {
            return (text || '').toString().toLowerCase().trim()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-')
                .replace(/^-+/, '')
                .replace(/-+$/, '');
        }

        if (addNameInput && addSlugPreview) {
            addNameInput.addEventListener('input', function () {
                const auto = generateSlug(this.value) || 'category-slug';
                addSlugPreview.textContent = auto;
                if (addCustomSlug && !addCustomSlug.value) {
                    addCustomSlug.placeholder = auto;
                }
            });
        }

        // Setup Edit Category Modal Population
        const editModalEl = document.getElementById('editCategoryModal');
        function populateEditModal(data) {
            if (!data) return;
            document.getElementById('edit_cat_id').value = data.id || '';
            document.getElementById('edit_cat_name').value = data.name || '';
            document.getElementById('edit_cat_slug').value = data.slug || '';
            document.getElementById('edit_cat_description').value = data.description || '';
            
            const statusSelect = document.getElementById('edit_cat_status');
            if (statusSelect) {
                statusSelect.value = (data.status || 'active').toLowerCase();
            }

            const iconVal = data.icon || 'fa-tag';
            if (editPicker) {
                editPicker.selectIcon(iconVal, getIconName(iconVal));
            }
        }

        document.querySelectorAll('.edit-cat-trigger').forEach(btn => {
            btn.addEventListener('click', function () {
                populateEditModal({
                    id: this.dataset.id,
                    name: this.dataset.name,
                    slug: this.dataset.slug,
                    description: this.dataset.description,
                    icon: this.dataset.icon,
                    status: this.dataset.status,
                    items: this.dataset.items
                });
            });
        });

        // Setup Delete Category Modal Population & Safety Verification
        const deleteModalEl = document.getElementById('deleteCategoryModal');
        document.querySelectorAll('.delete-cat-trigger').forEach(btn => {
            btn.addEventListener('click', function () {
                const catId = this.dataset.id;
                const catName = this.dataset.name;
                const itemCount = parseInt(this.dataset.items || '0', 10);

                const deleteCatIdInput = document.getElementById('delete_cat_id');
                const deleteCatName = document.getElementById('delete_category_name');
                const deleteBlockedView = document.getElementById('delete_blocked_view');
                const deleteAllowedView = document.getElementById('delete_allowed_view');
                const deleteBlockedActions = document.getElementById('delete_blocked_actions');
                const deleteCategoryForm = document.getElementById('delete_category_form');
                const deleteBlockedText = document.getElementById('delete_blocked_text');
                const viewItemsBtn = document.getElementById('view_category_items_btn');

                if (deleteCatIdInput) deleteCatIdInput.value = catId;
                if (deleteCatName) deleteCatName.textContent = `"${catName}"`;

                if (itemCount > 0) {
                    // Category has active or historical auction items: Block deletion
                    deleteBlockedView.classList.remove('d-none');
                    deleteAllowedView.classList.add('d-none');
                    deleteBlockedActions.classList.remove('d-none');
                    deleteCategoryForm.classList.add('d-none');

                    if (deleteBlockedText) {
                        deleteBlockedText.textContent = `This category contains ${itemCount} auction item(s) and cannot be deleted. Please move or remove the associated items first.`;
                    }
                    if (viewItemsBtn) {
                        viewItemsBtn.href = `items.php?search=${encodeURIComponent(catName)}`;
                    }
                } else {
                    // Category has 0 items: Allow deletion with confirmation
                    deleteBlockedView.classList.add('d-none');
                    deleteAllowedView.classList.remove('d-none');
                    deleteBlockedActions.classList.add('d-none');
                    deleteCategoryForm.classList.remove('d-none');
                }
            });
        });

        // Open edit modal directly if ?edit=ID is passed in the URL
        <?php if (!empty($direct_edit_cat)): ?>
            if (editModalEl) {
                const editModal = new bootstrap.Modal(editModalEl);
                populateEditModal(<?php echo json_encode($direct_edit_cat); ?>);
                editModal.show();
            }
        <?php endif; ?>
    });

    // Prevent browser back cache artifacts
    window.addEventListener('pageshow', function (event) {
        if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
            window.location.reload();
        }
    });
    </script>
</body>
</html>