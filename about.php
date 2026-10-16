<?php 
/**
 * About Platform Page - Geo Auction System
 * File: about.php
 */

declare(strict_types=1);

$require_auth = false;
include_once __DIR__ . '/includes/header.php'; 
?>

<div class="container py-5">
    <!-- Hero Banner -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 text-white" style="background: linear-gradient(135deg, #022c22 0%, #064e3b 50%, #047857 100%);">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="badge bg-white bg-opacity-20 text-white px-3 py-1.5 rounded-pill mb-3 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">About Us</span>
            <h1 class="display-5 fw-bold mb-3">Empowering Transparent & Real-Time Auctions</h1>
            <p class="lead text-white-50 mx-auto mb-0" style="max-width: 720px;">
                Geo Auction System is a next-generation dynamic marketplace connecting verified buyers and sellers with live anti-sniping protection, escrow security, and geolocation-aware trading.
            </p>
        </div>
    </div>

    <!-- Core Pillars Grid -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px; background: rgba(4, 120, 87, 0.1); color: #047857;">
                    <i class="fa-solid fa-bolt fa-xl"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">Real-Time Bidding</h3>
                <p class="text-muted small mb-0">Dynamic countdowns, automatic outbid alerts, and live counter-bid reactions ensure you never miss out on high-demand lots.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px; background: rgba(4, 120, 87, 0.1); color: #047857;">
                    <i class="fa-solid fa-shield-halved fa-xl"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">Pessimistic Wallet Escrow</h3>
                <p class="text-muted small mb-0">Funds are safely held during active bids with bank-grade ACID row locking, preventing double-spending and non-paying buyers.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px; background: rgba(4, 120, 87, 0.1); color: #047857;">
                    <i class="fa-solid fa-map-location-dot fa-xl"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">Geo-Targeted Discovery</h3>
                <p class="text-muted small mb-0">Discover valuable lots near you with integrated distance calculations and regional collection points across Pakistan.</p>
            </div>
        </div>
    </div>

    <!-- Mission & Quality Statement -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <span class="badge px-3 py-1.5 rounded-pill mb-2 fw-semibold" style="background: rgba(4, 120, 87, 0.1); color: #047857;">Our Commitment</span>
                <h2 class="h3 fw-bold text-dark mb-3">Fair, Transparent & Verified Trading</h2>
                <p class="text-muted mb-3">
                    Every lot listed on Geo Auction undergoes multi-tier verification. We enforce strict anti-shill bidding algorithms, preventing sellers from inflating their own prices and ensuring genuine competition.
                </p>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-2 text-dark small fw-semibold">
                        <i class="fa-solid fa-circle-check text-success"></i> 100% Verified Sellers & Identity Verification
                    </div>
                    <div class="d-flex align-items-center gap-2 text-dark small fw-semibold">
                        <i class="fa-solid fa-circle-check text-success"></i> Anti-Sniping Dynamic 3-Minute Extension
                    </div>
                    <div class="d-flex align-items-center gap-2 text-dark small fw-semibold">
                        <i class="fa-solid fa-circle-check text-success"></i> Official PDF Invoice with SHA-256 Digital Verification
                    </div>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img src="assets/image/Geo-auction-logo-clean.png" alt="Geo Auction" class="img-fluid" style="max-height: 180px;" onerror="this.onerror=null;this.src='assets/image/Geo-auction.png';">
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
