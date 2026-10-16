<?php
/**
 * Official Auction Invoice & Receipt Viewer / PDF Dispatcher
 * File: invoice.php
 * 
 * Provides:
 * 1. High-fidelity printable Web Invoice & Receipt View
 * 2. Real binary PDF download / streaming via FPDF engine
 * 3. Security & authorization verification for Buyer, Seller, and Admin
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/InvoiceService.php';

// Authentication Check: User must be logged in
if (!isset($_SESSION['user_id'])) {
    $redirectUrl = urlencode($_SERVER['REQUEST_URI'] ?? 'invoice.php');
    header("Location: login.php?redirect=" . $redirectUrl);
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$currentUserRole = strtolower($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'buyer');
$isAdmin = ($currentUserRole === 'admin');

// Request Parameters
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;
$itemId  = isset($_GET['item_id'])  ? (int)$_GET['item_id']  : null;
$format  = strtolower(trim($_GET['format'] ?? ($_GET['action'] ?? 'view')));

if (!$orderId && !$itemId) {
    header("Location: user/won_auctions.php");
    exit();
}

// Fetch invoice data with permission check
$invoiceService = new InvoiceService($conn);
$data = $invoiceService->getInvoiceData($orderId, $itemId, $currentUserId, $isAdmin);

if (!$data) {
    // Access Denied or Invoice Not Found
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - Geo Auction Invoice</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-slate-200 p-8 text-center">
            <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h2 class="text-xl font-black text-slate-900 mb-2">Invoice Access Restricted</h2>
            <p class="text-slate-500 text-sm mb-6">
                You do not have permission to view this invoice or the auction record does not exist. Only the winning buyer, lot seller, or platform administrators may access this settlement document.
            </p>
            <div class="flex gap-3 justify-center">
                <a href="user/won_auctions.php" class="px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-sm font-bold transition shadow-sm">
                    My Won Auctions
                </a>
                <a href="index.php" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-bold transition">
                    Homepage
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// -------------------------------------------------------------
// PDF Download / Inline Stream Handler
// -------------------------------------------------------------
if ($format === 'download' || $format === 'pdf') {
    $dest = ($format === 'download') ? 'D' : 'I';
    $filename = 'GeoAuction_Invoice_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$data['invoice_number']) . '.pdf';
    $invoiceService->generatePdf($data, $dest, $filename);
    exit();
}

// -------------------------------------------------------------
// High-Definition Responsive HTML & Printable Invoice View
// -------------------------------------------------------------
$isPaid = in_array(strtoupper($data['payment_status']), ['PAID', 'COMPLETED', 'AUTO_SETTLED']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Invoice #<?= htmlspecialchars($data['invoice_number']); ?> - Geo Auction</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .print-shadow-none {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }
            @page {
                margin: 1.2cm;
                size: A4 portrait;
            }
        }
    </style>
</head>
<body class="py-8 px-4 sm:px-6 antialiased text-slate-800">

    <!-- Top Action Navigation Bar (Hidden during physical print) -->
    <div class="max-w-4xl mx-auto mb-6 no-print">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-4 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="user/won_auctions.php" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition" title="Back to Won Auctions">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-sm font-black text-slate-900 leading-tight">Auction Settlement Invoice</h1>
                    <span class="text-xs text-slate-400 font-mono">Lot #<?= htmlspecialchars($data['item']['auction_code'] ?? 'LOT'); ?></span>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <!-- Browser Print Button -->
                <button type="button" onclick="window.print()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2">
                    <i class="fa-solid fa-print"></i>
                    <span>Print Receipt</span>
                </button>

                <!-- PDF Direct Download Button -->
                <a href="invoice.php?order_id=<?= (int)$orderId; ?>&item_id=<?= (int)$itemId; ?>&format=download" 
                   class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-file-pdf"></i>
                    <span>Download PDF</span>
                </a>

                <!-- Open in PDF Viewer Tab -->
                <a href="invoice.php?order_id=<?= (int)$orderId; ?>&item_id=<?= (int)$itemId; ?>&format=pdf" 
                   target="_blank"
                   class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5"
                   title="Open Raw PDF">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Printable Invoice Card -->
    <div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-xl shadow-slate-200/70 border border-slate-200/90 overflow-hidden print-shadow-none">
        
        <!-- Header Banner & Branding -->
        <div class="p-8 sm:p-10 border-b border-slate-200/80 bg-gradient-to-b from-slate-50/70 to-white">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                <!-- Company Logo & Address -->
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center shrink-0">
                        <img src="assets/image/Geo-auction-logo-clean.png" alt="Geo Auction" class="w-10 h-10 object-contain" onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'fa-solid fa-gavel text-2xl text-emerald-700\'></i>';">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xl font-black tracking-tight text-slate-900">GEO AUCTION</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Verified Settlement
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Premier Live & Geolocation Auction Marketplace<br>
                            Karachi, Pakistan &bull; support@geoauction.com &bull; +92 (021) 3456-7890
                        </p>
                    </div>
                </div>

                <!-- Invoice Meta & Badges -->
                <div class="sm:text-right">
                    <div class="text-2xl font-black text-slate-900 tracking-tight mb-1">INVOICE</div>
                    <div class="font-mono text-sm font-bold text-emerald-700 mb-2">#<?= htmlspecialchars($data['invoice_number']); ?></div>
                    <div class="flex flex-wrap sm:justify-end gap-2 text-xs text-slate-500">
                        <div><strong class="text-slate-700">Issue Date:</strong> <?= htmlspecialchars($data['invoice_date']); ?></div>
                    </div>
                    <div class="mt-2.5">
                        <?php if ($isPaid): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                <i class="fa-solid fa-circle-check text-emerald-600"></i> PAID &amp; SETTLED
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-rose-100 text-rose-800 border border-rose-200">
                                <i class="fa-solid fa-clock text-rose-600"></i> AWAITING PAYMENT
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Two Column Buyer & Seller Details -->
        <div class="p-8 sm:p-10 border-b border-slate-200/80 grid grid-cols-1 sm:grid-cols-2 gap-8 bg-slate-50/40">
            <!-- Billed To (Buyer) -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-2 text-xs font-black text-emerald-700 uppercase tracking-wider mb-3">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Billed To (Winning Buyer)</span>
                </div>
                <div class="text-base font-extrabold text-slate-900 mb-1">
                    <?= htmlspecialchars($data['buyer']['name']); ?>
                </div>
                <div class="text-xs text-slate-500 space-y-1">
                    <div><span class="font-semibold text-slate-700">Email:</span> <?= htmlspecialchars($data['buyer']['email']); ?></div>
                    <div><span class="font-semibold text-slate-700">Phone:</span> <?= htmlspecialchars($data['buyer']['phone']); ?></div>
                    <div><span class="font-semibold text-slate-700">Shipping Address:</span> <?= htmlspecialchars($data['shipping_address']); ?></div>
                </div>
            </div>

            <!-- Auction Seller Particulars -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-2 text-xs font-black text-slate-700 uppercase tracking-wider mb-3">
                    <i class="fa-solid fa-store"></i>
                    <span>Auction Consignor / Seller</span>
                </div>
                <div class="text-base font-extrabold text-slate-900 mb-1">
                    <?= htmlspecialchars($data['seller']['name']); ?>
                </div>
                <div class="text-xs text-slate-500 space-y-1">
                    <div><span class="font-semibold text-slate-700">Email:</span> <?= htmlspecialchars($data['seller']['email']); ?></div>
                    <div><span class="font-semibold text-slate-700">Phone:</span> <?= htmlspecialchars($data['seller']['phone']); ?></div>
                    <div><span class="font-semibold text-slate-700">Lot Location:</span> <?= htmlspecialchars($data['item']['location_city'] ?? 'Karachi, Pakistan'); ?> [Verified Consignment]</div>
                </div>
            </div>
        </div>

        <!-- Item & Lot Particulars Table -->
        <div class="p-8 sm:p-10 border-b border-slate-200/80">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b-2 border-slate-200 text-[11px] font-black text-slate-400 uppercase tracking-wider">
                            <th class="py-3 px-4">Lot #</th>
                            <th class="py-3 px-4">Auction Item Description</th>
                            <th class="py-3 px-4">Specifications</th>
                            <th class="py-3 px-4 text-center">Qty</th>
                            <th class="py-3 px-4 text-right">Hammer Price (PKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <tr>
                            <!-- Lot Code -->
                            <td class="py-4 px-4 font-mono font-bold text-slate-700">
                                #<?= htmlspecialchars($data['item']['auction_code'] ?? 'LOT-1'); ?>
                            </td>

                            <!-- Title & Image -->
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($data['item']['featured_image'])): ?>
                                        <img src="<?= htmlspecialchars($data['item']['featured_image']); ?>" alt="Lot" class="w-12 h-12 rounded-xl object-cover border border-slate-200 shrink-0">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200">
                                            <i class="fa-solid fa-box-open"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="item.php?id=<?= (int)$data['item']['id']; ?>" class="font-bold text-slate-900 hover:text-emerald-700 transition">
                                            <?= htmlspecialchars($data['item']['title']); ?>
                                        </a>
                                        <div class="text-[11px] text-slate-400 mt-0.5">
                                            Category: <?= htmlspecialchars($data['item']['category_name'] ?? 'General'); ?> &bull; Lot Authenticated
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Specs -->
                            <td class="py-4 px-4 text-slate-600">
                                <?php
                                $specsArr = [];
                                if (!empty($data['item']['condition_type'])) $specsArr[] = ucfirst($data['item']['condition_type']);
                                if (!empty($data['item']['brand'])) $specsArr[] = $data['item']['brand'];
                                if (!empty($data['item']['model'])) $specsArr[] = $data['item']['model'];
                                if (!empty($data['item']['year'])) $specsArr[] = $data['item']['year'];
                                echo !empty($specsArr) ? htmlspecialchars(implode(' &bull; ', $specsArr)) : 'Standard Lot';
                                ?>
                            </td>

                            <!-- Qty -->
                            <td class="py-4 px-4 text-center font-bold text-slate-700">
                                1 Lot
                            </td>

                            <!-- Price -->
                            <td class="py-4 px-4 text-right font-black text-slate-900 text-sm">
                                Rs. <?= number_format($data['hammer_price'], 2); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Totals & Payment Breakdown -->
        <div class="p-8 sm:p-10 border-b border-slate-200/80 bg-slate-50/30">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 items-start">
                
                <!-- Payment & Fulfillment Status Details -->
                <div class="space-y-3 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-credit-card text-emerald-700"></i>
                        <span>Payment &amp; Fulfillment Record</span>
                    </h3>
                    <div class="text-xs space-y-2 text-slate-600">
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-400">Payment Channel:</span>
                            <span class="font-bold text-slate-800"><?= htmlspecialchars($data['payment_method']); ?></span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-400">Transaction ID:</span>
                            <span class="font-mono font-bold text-emerald-700"><?= htmlspecialchars($data['transaction_id']); ?></span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-400">Settlement Timestamp:</span>
                            <span class="font-semibold text-slate-700"><?= htmlspecialchars($data['paid_at']); ?></span>
                        </div>
                        <div class="flex justify-between py-1">
                            <span class="text-slate-400">Fulfillment Status:</span>
                            <span class="font-bold text-indigo-700"><?= htmlspecialchars($data['shipping_status']); ?></span>
                        </div>
                        <?php if (!empty($data['tracking_number'])): ?>
                        <div class="flex justify-between py-1 bg-indigo-50/50 px-2.5 rounded-lg">
                            <span class="text-indigo-900 font-semibold">Courier Tracking:</span>
                            <span class="font-mono font-bold text-indigo-700"><?= htmlspecialchars($data['tracking_company'] . ' #' . $data['tracking_number']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Grand Totals Box -->
                <div class="space-y-2.5">
                    <div class="flex justify-between text-xs text-slate-600 py-1">
                        <span>Hammer Price (Final Bid):</span>
                        <span class="font-bold text-slate-900">Rs. <?= number_format($data['hammer_price'], 2); ?></span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-600 py-1">
                        <span>Platform Buyer's Premium:</span>
                        <span class="font-bold text-slate-900">Rs. <?= number_format($data['platform_fee'], 2); ?></span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-600 py-1">
                        <span>Shipping &amp; Logistics:</span>
                        <span class="font-bold text-slate-900">
                            <?= $data['shipping_fee'] > 0 ? ('Rs. ' . number_format($data['shipping_fee'], 2)) : 'Free / Included'; ?>
                        </span>
                    </div>

                    <div class="pt-3 border-t-2 border-slate-200 flex justify-between items-center">
                        <span class="text-sm font-black text-slate-900 uppercase">Total Settled:</span>
                        <span class="text-xl font-black text-emerald-700">
                            Rs. <?= number_format($data['total_amount'], 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Digital Verification & Authenticity Bar -->
        <div class="p-8 sm:p-10 bg-slate-50/80 border-b border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-100/80 border border-emerald-300 text-emerald-800 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h4 class="text-xs font-black text-slate-900 uppercase tracking-wider">Cryptographic Settlement Seal</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        SHA-256 Hash: <span class="font-mono font-bold text-emerald-700 select-all">GEO-<?= htmlspecialchars($data['verification_hash']); ?></span>
                    </p>
                </div>
            </div>

            <div class="text-center sm:text-right">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-white border border-slate-300 text-slate-700 shadow-sm">
                    <i class="fa-solid fa-stamp text-emerald-600"></i> Platform Authorized
                </span>
            </div>
        </div>

        <!-- Official Terms Footer -->
        <div class="p-6 sm:p-8 bg-white text-[11px] text-slate-400 space-y-1">
            <p><strong>Terms:</strong> All winning lots are governed by the Geo Auction Buyer Protection Policy and Terms of Service. Invoices are automatically generated upon confirmed settlement.</p>
            <p>Questions? Contact platform support at <strong>support@geoauction.com</strong> or call <strong>+92 (021) 3456-7890</strong>.</p>
        </div>

    </div>

    <!-- Print Footer helper -->
    <div class="text-center text-xs text-slate-400 mt-8 mb-4 no-print">
        &copy; <?= date('Y'); ?> Geo Auction System. All rights reserved.
    </div>

</body>
</html>
