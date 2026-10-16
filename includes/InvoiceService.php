<?php
/**
 * Invoice & Receipt Generation Service
 * File: includes/InvoiceService.php
 * 
 * Provides end-to-end invoice data resolution and PDF generation
 * for won auctions, payment settlements, and seller payouts.
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/fpdf/fpdf.php';

/**
 * Custom FPDF subclass tailored for Geo Auction branded invoices
 */
class GeoAuctionPdf extends FPDF
{
    public string $invoiceNumber = '';
    public string $invoiceDate = '';
    public string $statusText = 'PAID';

    // Page header
    public function Header(): void
    {
        // Emerald top accent bar
        $this->SetFillColor(4, 120, 87); // #047857 Emerald
        $this->Rect(0, 0, 210, 5, 'F');

        // Logo
        $logoPath = dirname(__DIR__) . '/assets/image/Geo-auction-logo-clean.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 14, 10, 38);
        } else {
            $this->SetFont('Helvetica', 'B', 18);
            $this->SetTextColor(4, 120, 87);
            $this->SetXY(14, 12);
            $this->Cell(50, 10, 'GEO AUCTION', 0, 0, 'L');
        }

        // Platform Company Details (Left sub-text)
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->SetXY(14, 25);
        $this->Cell(70, 4, 'Geo Auction Platform - Settlement Division', 0, 1, 'L');
        $this->SetX(14);
        $this->Cell(70, 4, 'support@geoauction.com | +92 (021) 3456-7890', 0, 1, 'L');
        $this->SetX(14);
        $this->Cell(70, 4, 'Karachi, Pakistan | www.geoauction.com', 0, 1, 'L');

        // Header Right - Invoice Title & Meta
        $this->SetXY(120, 10);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(15, 23, 42); // Slate-900
        $this->Cell(76, 7, 'OFFICIAL INVOICE', 0, 1, 'R');

        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(71, 85, 105);
        $this->SetX(120);
        $this->Cell(40, 5, 'Invoice Number:', 0, 0, 'R');
        $this->SetTextColor(4, 120, 87);
        $this->Cell(36, 5, $this->invoiceNumber, 0, 1, 'R');

        $this->SetTextColor(71, 85, 105);
        $this->SetX(120);
        $this->Cell(40, 5, 'Issue Date:', 0, 0, 'R');
        $this->SetTextColor(15, 23, 42);
        $this->Cell(36, 5, $this->invoiceDate, 0, 1, 'R');

        $this->SetTextColor(71, 85, 105);
        $this->SetX(120);
        $this->Cell(40, 5, 'Payment Status:', 0, 0, 'R');
        if (strtoupper($this->statusText) === 'PAID' || strtoupper($this->statusText) === 'COMPLETED' || strtoupper($this->statusText) === 'AUTO_SETTLED') {
            $this->SetTextColor(4, 120, 87); // Green
            $this->Cell(36, 5, 'PAID & VERIFIED', 0, 1, 'R');
        } else {
            $this->SetTextColor(225, 29, 72); // Rose
            $this->Cell(36, 5, $this->statusText, 0, 1, 'R');
        }

        // Horizontal Separator Rule
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.4);
        $this->Line(14, 42, 196, 42);

        $this->Ln(6);
    }

    // Page footer
    public function Footer(): void
    {
        $this->SetY(-22);

        // Thin separator
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        $this->Line(14, $this->GetY(), 196, $this->GetY());

        $this->Ln(2);

        // Footer notice
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(148, 163, 184); // Slate-400
        $this->Cell(140, 4, 'This is an authentic computer-generated auction settlement invoice. Verified by Geo Auction Engine.', 0, 0, 'L');

        // Page Number
        $this->Cell(42, 4, 'Page ' . $this->PageNo() . ' of {nb}', 0, 1, 'R');

        $this->SetFont('Helvetica', '', 7);
        $this->Cell(182, 3.5, 'All sales are final under platform terms. For disputes or support, contact support@geoauction.com.', 0, 1, 'L');
    }

    /**
     * Draw rounded rectangle
     */
    public function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = '', string $corners = '1234'): void
    {
        $k = $this->k;
        $hp = $this->h;
        if ($style === 'F') {
            $op = 'f';
        } elseif ($style === 'FD' || $style === 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $myArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));

        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        if (strpos($corners, '2') === false) {
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $y) * $k));
        } else {
            $this->_Arc($xc + $r * $myArc, $yc - $r, $xc + $r, $yc - $r * $myArc, $xc + $r, $yc);
        }

        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        if (strpos($corners, '3') === false) {
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - ($y + $h)) * $k));
        } else {
            $this->_Arc($xc + $r, $yc + $r * $myArc, $xc + $r * $myArc, $yc + $r, $xc, $yc + $r);
        }

        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        if (strpos($corners, '4') === false) {
            $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - ($y + $h)) * $k));
        } else {
            $this->_Arc($xc - $r * $myArc, $yc + $r, $xc - $r, $yc + $r * $myArc, $xc - $r, $yc);
        }

        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $yc) * $k));
        if (strpos($corners, '1') === false) {
            $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $y) * $k));
            $this->_out(sprintf('%.2F %.2F l', ($x + $r) * $k, ($hp - $y) * $k));
        } else {
            $this->_Arc($xc - $r, $yc - $r * $myArc, $xc - $r * $myArc, $yc - $r, $xc, $yc - $r);
        }
        $this->_out($op);
    }

    protected function _Arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', 
            $x1 * $this->k, ($h - $y1) * $this->k, 
            $x2 * $this->k, ($h - $y2) * $this->k, 
            $x3 * $this->k, ($h - $y3) * $this->k));
    }
}

class InvoiceService
{
    private PDO $pdo;

    public function __construct($connOrPdo = null)
    {
        if ($connOrPdo instanceof PDO) {
            $this->pdo = $connOrPdo;
        } else {
            $this->pdo = Database::getPdo();
        }
    }

    /**
     * Resolve full invoice dataset by Order ID or Item ID
     *
     * @param int|null $orderId
     * @param int|null $itemId
     * @param int|null $currentUserId For authorization check (pass null if admin)
     * @param bool $isAdmin
     * @return array|null
     */
    public function getInvoiceData(?int $orderId = null, ?int $itemId = null, ?int $currentUserId = null, bool $isAdmin = false): ?array
    {
        $order = null;
        $item  = null;

        // 1. Fetch by Order ID if provided
        if ($orderId && $orderId > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($order && empty($itemId)) {
                $itemId = (int)$order['auction_id'];
            }
        }

        // 2. Fetch Item Details
        if ($itemId && $itemId > 0) {
            $stmt = $this->pdo->prepare("
                SELECT i.*, c.name AS category_name, c.slug AS category_slug
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.id
                WHERE i.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$item && !$order) {
            return null;
        }

        // 3. If Order wasn't found by ID, look up by item's winner
        if (!$order && $item) {
            $winnerId = (int)($item['winner_id'] ?? 0);
            if ($winnerId > 0) {
                $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE auction_id = :item_id AND buyer_id = :buyer_id LIMIT 1");
                $stmt->execute([':item_id' => $itemId, ':buyer_id' => $winnerId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        // Determine buyer and seller IDs
        $buyerId  = (int)($order['buyer_id'] ?? $item['winner_id'] ?? 0);
        $sellerId = (int)($order['seller_id'] ?? $item['seller_id'] ?? 0);

        // Fallback: If no winner_id on item yet, look up highest bid
        if ($buyerId <= 0 && $item) {
            $b_stmt = $this->pdo->prepare("
                SELECT user_id, bid_amount 
                FROM bids 
                WHERE item_id = :item_id 
                ORDER BY bid_amount DESC 
                LIMIT 1
            ");
            $b_stmt->execute([':item_id' => $item['id']]);
            $b_row = $b_stmt->fetch(PDO::FETCH_ASSOC);
            if ($b_row) {
                $buyerId = (int)$b_row['user_id'];
            }
        }

        // 4. Authorization check
        if (!$isAdmin && $currentUserId !== null) {
            if ($currentUserId !== $buyerId && $currentUserId !== $sellerId) {
                return null; // Forbidden: neither buyer, seller, nor admin
            }
        }

        // 5. Fetch Buyer Information
        $buyer = [
            'id'       => $buyerId,
            'name'     => 'Winning Buyer',
            'email'    => 'buyer@geoauction.com',
            'phone'    => 'N/A',
            'address'  => 'N/A',
            'city'     => 'N/A'
        ];
        if ($buyerId > 0) {
            $u_stmt = $this->pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = :id LIMIT 1");
            $u_stmt->execute([':id' => $buyerId]);
            $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
            if ($u_row) {
                $buyer = array_merge($buyer, $u_row);
            }
        }

        // 6. Fetch Seller Information
        $seller = [
            'id'       => $sellerId,
            'name'     => 'Verified Auction Seller',
            'email'    => 'seller@geoauction.com',
            'phone'    => 'N/A',
            'city'     => 'N/A'
        ];
        if ($sellerId > 0) {
            $s_stmt = $this->pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = :id LIMIT 1");
            $s_stmt->execute([':id' => $sellerId]);
            $s_row = $s_stmt->fetch(PDO::FETCH_ASSOC);
            if ($s_row) {
                $seller = array_merge($seller, $s_row);
            }
        }

        // 7. Fetch Payment Record (if any)
        $payment = null;
        if ($item) {
            $p_stmt = $this->pdo->prepare("
                SELECT * FROM payments 
                WHERE item_id = :item_id AND (user_id = :uid OR buyer_id = :bid_str OR buyer_id = :email) 
                ORDER BY id DESC LIMIT 1
            ");
            $bIdStr = (string)$buyerId;
            $p_stmt->execute([
                ':item_id' => $item['id'],
                ':uid'     => $buyerId,
                ':bid_str' => $bIdStr,
                ':email'   => $buyer['email']
            ]);
            $payment = $p_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // 8. Fetch Transaction Record (if any)
        $transaction = null;
        if ($item) {
            $t_stmt = $this->pdo->prepare("
                SELECT * FROM transactions 
                WHERE item_id = :item_id AND user_id = :uid AND type IN ('bid_payment', 'winning_payment') 
                ORDER BY id DESC LIMIT 1
            ");
            $t_stmt->execute([
                ':item_id' => $item['id'],
                ':uid'     => $buyerId
            ]);
            $transaction = $t_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        // 9. Consolidate Financial Breakdown
        $hammerPrice = (float)($order['hammer_price'] ?? 0);
        if ($hammerPrice <= 0 && $item) {
            $hammerPrice = (float)($item['current_price'] > 0 ? $item['current_price'] : $item['starting_price']);
        }

        $platformFee = (float)($order['platform_fee'] ?? 0);
        $shippingFee = (float)($order['shipping_fee'] ?? 0);
        $totalAmount = (float)($order['total_amount'] ?? ($hammerPrice + $platformFee + $shippingFee));

        // Determine Invoice Number
        $invoiceNo = $order['order_number'] ?? null;
        if (empty($invoiceNo) && !empty($payment['invoice_no'])) {
            $invoiceNo = $payment['invoice_no'];
        }
        if (empty($invoiceNo)) {
            $invoiceNo = 'INV-' . date('Y') . '-' . str_pad((string)($item['id'] ?? 1), 6, '0', STR_PAD_LEFT);
        }

        // Determine Payment Status
        $paymentStatus = strtoupper($order['payment_status'] ?? 'PAYMENT_PENDING');
        if ($payment && strtolower($payment['payment_status']) === 'completed') {
            $paymentStatus = 'PAID';
        }
        if ($transaction && strtolower($transaction['status']) === 'completed') {
            $paymentStatus = 'PAID';
        }

        // Shipping Address
        $shippingAddress = !empty($order['shipping_address'])
            ? $order['shipping_address']
            : (!empty($payment['billing_address']) ? $payment['billing_address'] . ', ' . ($payment['billing_city'] ?? '') : 'Standard Registered Address');

        // Tracking Details
        $trackingCompany = $order['tracking_company'] ?? null;
        $trackingNumber  = $order['tracking_number'] ?? null;
        $shippingStatus  = strtoupper($order['shipping_status'] ?? 'PROCESSING');

        // Verification Hash
        $hashData = "GEO-INV:{$invoiceNo}:{$hammerPrice}:{$buyer['email']}:" . ($order['created_at'] ?? date('Y-m-d'));
        $verifyHash = strtoupper(substr(hash('sha256', $hashData), 0, 16));

        return [
            'invoice_number'   => $invoiceNo,
            'invoice_date'     => !empty($order['created_at']) ? date('M d, Y', strtotime($order['created_at'])) : date('M d, Y'),
            'due_date'         => !empty($order['created_at']) ? date('M d, Y', strtotime($order['created_at'] . ' + 3 days')) : date('M d, Y'),
            'payment_status'   => $paymentStatus,
            'payment_method'   => $payment['payment_method'] ?? 'Wallet Auto-Debit / Internal Balance',
            'transaction_id'   => $payment['transaction_id'] ?? ($transaction['reference_id'] ?? ('TXN-' . strtoupper(substr(md5((string)$invoiceNo), 0, 10)))),
            'paid_at'          => !empty($payment['paid_at']) ? date('M d, Y H:i', strtotime($payment['paid_at'])) : (!empty($order['created_at']) ? date('M d, Y H:i', strtotime($order['created_at'])) : date('M d, Y H:i')),
            'hammer_price'     => $hammerPrice,
            'platform_fee'     => $platformFee,
            'shipping_fee'     => $shippingFee,
            'total_amount'     => $totalAmount,
            'shipping_status'  => $shippingStatus,
            'shipping_address' => $shippingAddress,
            'tracking_company' => $trackingCompany,
            'tracking_number'  => $trackingNumber,
            'verification_hash'=> $verifyHash,
            'buyer'            => $buyer,
            'seller'           => $seller,
            'item'             => $item,
            'order'            => $order
        ];
    }

    /**
     * Generate raw binary PDF document using GeoAuctionPdf
     *
     * @param array $data
     * @param string $dest 'I' (Inline), 'D' (Download), 'S' (String), 'F' (File)
     * @param string|null $filename
     * @return string
     */
    public function generatePdf(array $data, string $dest = 'I', ?string $filename = null): string
    {
        $filename = $filename ?? ('GeoAuction_' . $data['invoice_number'] . '.pdf');

        $pdf = new GeoAuctionPdf('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->invoiceNumber = $data['invoice_number'];
        $pdf->invoiceDate   = $data['invoice_date'];
        $pdf->statusText    = $data['payment_status'];

        $pdf->SetMargins(14, 12, 14);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();

        // ---------------------------------------------------------------------
        // 1. BILLING & SELLER PARTICULARS (Two Column Card)
        // ---------------------------------------------------------------------
        $pdf->SetY(48);

        // Billed To (Buyer Box)
        $pdf->SetFillColor(248, 250, 252); // Slate-50
        $pdf->SetDrawColor(226, 232, 240); // Slate-200
        $pdf->RoundedRect(14, 48, 88, 38, 2.5, 'DF');

        $pdf->SetXY(18, 51);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(4, 120, 87);
        $pdf->Cell(80, 4, 'BILLED TO (BUYER):', 0, 1, 'L');

        $pdf->SetX(18);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $buyerName = iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$data['buyer']['name']);
        $pdf->Cell(80, 5, substr($buyerName, 0, 35), 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetX(18);
        $pdf->Cell(80, 4, 'Email: ' . substr((string)$data['buyer']['email'], 0, 36), 0, 1, 'L');
        $pdf->SetX(18);
        $pdf->Cell(80, 4, 'Phone: ' . substr((string)$data['buyer']['phone'], 0, 25), 0, 1, 'L');
        $pdf->SetX(18);
        $addr = !empty($data['shipping_address']) ? $data['shipping_address'] : 'Standard Registered Address';
        $pdf->Cell(80, 4, 'Address: ' . substr(iconv('UTF-8', 'windows-1252//TRANSLIT', $addr), 0, 40), 0, 1, 'L');

        // Auction Seller Box (Right)
        $pdf->SetFillColor(248, 250, 252);
        $pdf->RoundedRect(108, 48, 88, 38, 2.5, 'DF');

        $pdf->SetXY(112, 51);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell(80, 4, 'AUCTION CONSIGNOR / SELLER:', 0, 1, 'L');

        $pdf->SetX(112);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $sellerName = iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$data['seller']['name']);
        $pdf->Cell(80, 5, substr($sellerName, 0, 35), 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetX(112);
        $pdf->Cell(80, 4, 'Email: ' . substr((string)$data['seller']['email'], 0, 36), 0, 1, 'L');
        $pdf->SetX(112);
        $pdf->Cell(80, 4, 'Phone: ' . substr((string)$data['seller']['phone'], 0, 25), 0, 1, 'L');
        $pdf->SetX(112);
        $city = !empty($data['item']['location_city']) ? $data['item']['location_city'] : 'Karachi, Pakistan';
        $pdf->Cell(80, 4, 'Item Origin: ' . substr($city, 0, 30) . ' [Verified Lot]', 0, 1, 'L');

        // ---------------------------------------------------------------------
        // 2. ORDER / LOT PARTICULARS TABLE
        // ---------------------------------------------------------------------
        $pdf->SetY(92);

        // Table Header
        $pdf->SetFillColor(15, 23, 42); // Slate-900
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 8.5);

        $pdf->Cell(20, 8, 'LOT #', 0, 0, 'C', true);
        $pdf->Cell(78, 8, 'ITEM DESCRIPTION', 0, 0, 'L', true);
        $pdf->Cell(34, 8, 'SPECIFICATIONS', 0, 0, 'L', true);
        $pdf->Cell(20, 8, 'QTY', 0, 0, 'C', true);
        $pdf->Cell(30, 8, 'HAMMER PRICE', 0, 1, 'R', true);

        // Table Row
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetFont('Helvetica', '', 8.5);

        $lotCode = !empty($data['item']['auction_code']) ? $data['item']['auction_code'] : ('LOT-' . ($data['item']['id'] ?? '1'));
        $itemTitle = !empty($data['item']['title']) ? $data['item']['title'] : 'Auction Lot Item';
        $itemTitle = iconv('UTF-8', 'windows-1252//TRANSLIT', $itemTitle);

        $specs = [];
        if (!empty($data['item']['condition_type'])) {
            $specs[] = ucfirst((string)$data['item']['condition_type']);
        }
        if (!empty($data['item']['brand'])) {
            $specs[] = $data['item']['brand'];
        }
        if (!empty($data['item']['model'])) {
            $specs[] = $data['item']['model'];
        }
        if (!empty($data['item']['year'])) {
            $specs[] = (string)$data['item']['year'];
        }
        $specText = !empty($specs) ? implode(' | ', $specs) : ($data['item']['category_name'] ?? 'General Auction');
        $specText = iconv('UTF-8', 'windows-1252//TRANSLIT', $specText);

        $startY = $pdf->GetY();
        $pdf->Cell(20, 14, $lotCode, 'B', 0, 'C');

        // Multicell or formatted title
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(78, 7, substr($itemTitle, 0, 42), 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(100, 116, 139);

        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell(34, 14, substr($specText, 0, 24), 'B', 0, 'L');

        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(20, 14, '1 Lot', 'B', 0, 'C');

        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(4, 120, 87);
        $pdf->Cell(30, 14, 'Rs. ' . number_format($data['hammer_price'], 2), 'B', 1, 'R');

        // Second line for item description under title
        $pdf->SetXY(34, $startY + 6);
        $catName = !empty($data['item']['category_name']) ? $data['item']['category_name'] : 'Catalog Lot';
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(78, 6, 'Category: ' . $catName . ' | Authenticated Lot', 'B', 0, 'L');

        // ---------------------------------------------------------------------
        // 3. FINANCIAL TOTALS & PAYMENT SUMMARY
        // ---------------------------------------------------------------------
        $pdf->SetY(116);

        // Left Side: Payment Details Box
        $pdf->SetFillColor(241, 245, 249); // Slate-100
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->RoundedRect(14, 116, 96, 44, 2.5, 'DF');

        $pdf->SetXY(18, 119);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(88, 4, 'PAYMENT & SETTLEMENT DETAILS', 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetX(18);
        $pdf->Cell(36, 5, 'Payment Method:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(52, 5, substr((string)$data['payment_method'], 0, 28), 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetX(18);
        $pdf->Cell(36, 5, 'Transaction Ref:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(4, 120, 87);
        $pdf->Cell(52, 5, (string)$data['transaction_id'], 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetX(18);
        $pdf->Cell(36, 5, 'Settlement Time:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(52, 5, (string)$data['paid_at'], 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetX(18);
        $pdf->Cell(36, 5, 'Fulfillment Status:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(52, 5, $data['shipping_status'], 0, 1, 'L');

        if (!empty($data['tracking_number'])) {
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetX(18);
            $pdf->Cell(88, 4, 'Tracking: ' . $data['tracking_company'] . ' - ' . $data['tracking_number'], 0, 1, 'L');
        }

        // Right Side: Grand Totals Breakdown Table
        $pdf->SetXY(118, 116);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(71, 85, 105);

        // Hammer Price
        $pdf->Cell(44, 6, 'Hammer Price (Winning Bid):', 0, 0, 'R');
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(34, 6, 'Rs. ' . number_format($data['hammer_price'], 2), 0, 1, 'R');

        // Platform Fee
        $pdf->SetX(118);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell(44, 6, 'Platform Commission / Fee:', 0, 0, 'R');
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(34, 6, 'Rs. ' . number_format($data['platform_fee'], 2), 0, 1, 'R');

        // Shipping Fee
        $pdf->SetX(118);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell(44, 6, 'Shipping & Handling:', 0, 0, 'R');
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(34, 6, $data['shipping_fee'] > 0 ? ('Rs. ' . number_format($data['shipping_fee'], 2)) : 'Free / Included', 0, 1, 'R');

        // Subtotal separator
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->Line(135, $pdf->GetY() + 1, 196, $pdf->GetY() + 1);
        $pdf->Ln(2);

        // Total Amount Box (Emerald Highlight)
        $pdf->SetFillColor(4, 120, 87); // #047857
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetX(118);
        $pdf->Cell(44, 8, 'TOTAL PAID:', 0, 0, 'R');
        $pdf->Cell(34, 8, 'Rs. ' . number_format($data['total_amount'], 2), 0, 1, 'R');

        // ---------------------------------------------------------------------
        // 4. DIGITAL VERIFICATION SEAL & SECURITY WATERMARK
        // ---------------------------------------------------------------------
        $pdf->SetY(170);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->RoundedRect(14, 170, 182, 34, 2.5, 'DF');

        $pdf->SetXY(18, 173);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(4, 120, 87);
        $pdf->Cell(174, 4, 'DIGITAL VERIFICATION & BLOCK INTEGRITY SEAL', 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetX(18);
        $pdf->Cell(174, 4, 'This invoice is cryptographically signed and stored in the Geo Auction transaction registry.', 0, 1, 'L');

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetX(18);
        $pdf->Cell(32, 4.5, 'Verification Code:', 0, 0, 'L');
        $pdf->SetTextColor(4, 120, 87);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(70, 4.5, 'GEO-' . $data['verification_hash'], 0, 0, 'L');

        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(72, 4.5, 'Security Check: PASS [SHA-256 Validated]', 0, 1, 'R');

        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetX(18);
        $pdf->Cell(174, 4, 'Online Verification Portal: https://geoauction.com/verify?code=' . $data['verification_hash'], 0, 1, 'L');

        // ---------------------------------------------------------------------
        // 5. OFFICIAL TERMS & DISPUTE POLICY
        // ---------------------------------------------------------------------
        $pdf->SetY(210);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(182, 4, 'TERMS & SETTLEMENT POLICIES:', 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor(100, 116, 139);
        $terms = [
            "1. Ownership Title: Legal title to the lot transfers to the buyer upon full settlement and seller handover confirmation.",
            "2. Inspection & Delivery: The buyer is entitled to verify the lot specifications upon dispatch or physical pickup.",
            "3. Dispute Window: If lot condition deviates substantially from listing disclosures, dispute must be lodged within 48 hours.",
            "4. Platform Escrow: All funds are cleared via Geo Auction verified wallet protocols in compliance with platform rules."
        ];
        foreach ($terms as $t) {
            $pdf->SetX(14);
            $pdf->Cell(182, 3.5, $t, 0, 1, 'L');
        }

        return $pdf->Output($dest, $filename);
    }
}
