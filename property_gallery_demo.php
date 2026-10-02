<?php
/**
 * ============================================================================
 * STANDALONE DEMO: BAYUT-INSPIRED PROPERTY IMAGE GALLERY & SLIDER
 * URL: http://localhost/geo-auction-system-GIT/property_gallery_demo.php
 * Inspired by: Bayut.com Listing Layout (https://www.bayut.com/property/details-16407079.html)
 * ============================================================================
 */

// ----------------------------------------------------------------------------
// 1. PHP DATA SIMULATION (Sample Property Auction Data & Images)
// ----------------------------------------------------------------------------
$sample_auction_item = [
    'id' => 101,
    'auction_code' => 'DXB-VILLA-8802',
    'title' => 'Ultra-Luxury 6-Bedroom Golf Sanctuary Villa with Private Lagoon',
    'description' => 'Spectacular bespoke residence boasting double-height ceilings, Italian marble flooring, temperature-controlled infinity swimming pool, private elevator, and sunken outdoor fire lounge. Directly facing the championship 18-hole golf greens.',
    'starting_price' => 12500000.00,
    'current_price' => 14800000.00,
    'min_increment' => 100000.00,
    'bid_count' => 19,
    'watcher_count' => 142,
    'seller_name' => 'Signature Prime Estates UAE',
    'location_city' => 'Dubai',
    'location_area' => 'Dubai Hills Estate',
    'end_time' => date('Y-m-d H:i:s', time() + 86400 * 2 + 3600 * 5) // 2 days, 5 hours left
];

// Rich array of high-resolution property images
$gallery_images = [
    [
        'url' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1600&q=80',
        'title' => 'Grand Villa Facade & Arrival Court',
        'caption' => 'Dramatic contemporary facade with water features and mature royal palms'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Resort-Style Infinity Swimming Pool',
        'caption' => 'Seamless overflow edge pool with sunken fire pit lounge and sun decks'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600565193348-f74bd3c7ccdf?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Grand Double-Height Living Salon',
        'caption' => 'Sprawling reception lounge with soaring ceilings and floor-to-ceiling panoramic glass'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Show Kitchen & Quartz Island',
        'caption' => 'State-of-the-art Italian chef kitchen with Sub-Zero and Miele appliances'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Primary Master Suite',
        'caption' => 'Lavish bedroom retreat with custom walk-in dressing room and private horizon terrace'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600566753376-12c8ab7fb75b?auto=format&fit=crop&w=1200&q=80',
        'title' => 'En-Suite Spa Sanctuary',
        'caption' => 'Calacatta gold marble bathroom with freestanding soaking tub and dual rain showers'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Sunset Terrace & Skyline View',
        'caption' => 'Rooftop sky lounge offering uninterrupted sunset vistas over Dubai skyline'
    ]
];

$current_item = $sample_auction_item;
$dist_km = 4.2;
$is_watched = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bayut-Style Property Image Slider & Gallery Demo - Geo Auction System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #0f172a; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen py-8">

<div class="max-w-7xl mx-auto px-4 sm:px-6">

    <!-- Header Navigation Bar -->
    <div class="flex items-center justify-between bg-white border border-slate-200 rounded-2xl px-6 py-4 shadow-sm mb-6">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-extrabold text-sm shadow">GA</span>
            <div>
                <h1 class="text-base font-bold text-slate-900 leading-tight">Property Gallery Showcase</h1>
                <p class="text-xs text-slate-500">Bayut.com Listing Architecture &bull; Geo Auction System</p>
            </div>
        </div>
        <div class="flex items-center gap-3 text-xs">
            <a href="live_auction.php" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg shadow-sm transition">
                <i class="fa-solid fa-gavel mr-1.5"></i> Back to Live Auction
            </a>
        </div>
    </div>

    <!-- BAYUT PROPERTY GALLERY COMPONENT EMBED -->
    <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-6 shadow-sm mb-6">
        <?php include __DIR__ . '/includes/components/property_gallery.php'; ?>
        
        <!-- Property Listing Summary Header Under Gallery -->
        <div class="mt-4 pt-4 border-t border-slate-100 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-md">
                        #<?php echo $current_item['auction_code']; ?>
                    </span>
                    <span class="text-xs font-semibold text-slate-500 bg-slate-100 border border-slate-200 px-2.5 py-0.5 rounded-md">
                        <i class="fa-solid fa-shield-halved text-emerald-600 mr-1"></i> Verified Property Lot
                    </span>
                </div>
                <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900"><?php echo htmlspecialchars($current_item['title']); ?></h2>
                <p class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                    <i class="fa-solid fa-location-dot text-emerald-600"></i>
                    <span><?php echo $current_item['location_area']; ?>, <?php echo $current_item['location_city']; ?></span>
                    <span>&bull;</span>
                    <span>Listed by <strong><?php echo $current_item['seller_name']; ?></strong></span>
                </p>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-right shrink-0 min-w-[200px]">
                <span class="text-xs text-slate-500 font-medium block">Current Highest Bid</span>
                <span class="text-2xl font-extrabold text-emerald-600">
                    Rs. <?php echo number_format($current_item['current_price'], 2); ?>
                </span>
                <div class="text-[11px] text-slate-400 mt-0.5" id="timerDisplay">
                    <i class="fa-regular fa-clock text-amber-500 mr-1"></i> 02d 05h 32m remaining
                </div>
            </div>
        </div>
    </section>

    <!-- Information Card Explaining Architecture -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <i class="fa-solid fa-circle-info text-emerald-600"></i>
            Architectural Features Breakdown
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-600">
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80">
                <strong class="text-slate-900 block font-semibold text-sm mb-1">1. Bayut Split-Grid Layout</strong>
                <p>On desktop (≥1024px), the gallery renders a 65% primary hero image and a 35% 2x2 split thumbnail grid. The 4th thumbnail features a frosted "+N More" overlay when additional photos exist.</p>
            </div>
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80">
                <strong class="text-slate-900 block font-semibold text-sm mb-1">2. Touch Swipe Mobile Slider</strong>
                <p>On mobile and tablets (<1024px), the layout transforms into an app-like swipeable carousel with touch-drag gestures, pagination dots, and floating camera photo counter.</p>
            </div>
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80">
                <strong class="text-slate-900 block font-semibold text-sm mb-1">3. Fullscreen Lightbox Modal</strong>
                <p>Clicking any photo triggers a high-resolution dark modal viewer with keyboard arrow navigation (<kbd class="px-1.5 py-0.5 bg-slate-200 rounded">←</kbd> / <kbd class="px-1.5 py-0.5 bg-slate-200 rounded">→</kbd>), Escape to close, and bottom filmstrip.</p>
            </div>
        </div>
    </div>

</div>

</body>
</html>
