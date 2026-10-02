<?php
/**
 * ============================================================================
 * BAYUT-INSPIRED PROPERTY IMAGE GALLERY & SLIDER COMPONENT
 * Geo Auction System - Modern Real Estate Listing Showcase
 * ============================================================================
 * 
 * Technical Features:
 * 1. PHP Data Simulation & Dynamic Database Loop Integration
 * 2. Bayut-Style Desktop Layout (65% Hero / 35% 2x2 Split Thumbnail Grid)
 * 3. Responsive Touch-Swipe Mobile Carousel with Pagination Dots
 * 4. Rich Overlays: Live Auction beacon, Real-Time Countdown Timer, Watchlist Heart, Share, Location
 * 5. Fullscreen Lightbox / Modal Viewer with Keyboard Nav (Left/Right/Esc) & Touch Swipe
 * 6. Lightweight 60fps Vanilla JavaScript (Zero external slider dependencies)
 */

// ----------------------------------------------------------------------------
// 1. PHP DATA SIMULATION & DYNAMIC IMAGE LOADER
// ----------------------------------------------------------------------------

// High-resolution sample property images inspired by Bayut.com luxury listings
$default_sample_property_images = [
    [
        'url' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1600&q=80',
        'title' => 'Luxury Villa Facade & Landscaped Entrance',
        'caption' => 'Architectural exterior featuring modern minimalist facade with LED lighting'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Resort-Style Infinity Pool & Sunken Patio',
        'caption' => 'Private designer infinity pool surrounded by premium travertine stone decking'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600565193348-f74bd3c7ccdf?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Double-Height Living Salon & Glass Atrium',
        'caption' => 'Spacious open-concept living room bathed in natural light through floor-to-ceiling glass'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Designer Gourmet Chef Kitchen',
        'caption' => 'Custom Italian cabinetry, waterfall quartz island, and smart integrated appliances'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Master Bedroom Suite with Panoramic Balcony',
        'caption' => 'Primary suite with hardwood herringbone flooring and walk-out sunset balcony'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1600566753376-12c8ab7fb75b?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Spa-Inspired En-Suite Bathroom',
        'caption' => 'Freestanding soaking tub, frameless rain shower, and imported porcelain marble tiles'
    ],
    [
        'url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1200&q=80',
        'title' => 'Rooftop Lounge & Panoramic Horizon View',
        'caption' => 'Elevated rooftop terrace ideal for outdoor entertaining and sunset relaxation'
    ]
];

// Determine gallery images: use passed array or blend with samples for full Bayut 5-card presentation
$active_gallery_list = [];

if (!empty($gallery_images) && is_array($gallery_images)) {
    foreach ($gallery_images as $idx => $img) {
        if (is_array($img)) {
            $active_gallery_list[] = [
                'url' => $img['url'] ?? ($img['image_path'] ?? ''),
                'title' => $img['title'] ?? ('Photo ' . ($idx + 1)),
                'caption' => $img['caption'] ?? ''
            ];
        } else if (is_string($img) && trim($img) !== '') {
            $active_gallery_list[] = [
                'url' => trim($img),
                'title' => 'Property View ' . ($idx + 1),
                'caption' => ''
            ];
        }
    }
}

// If item has fewer than 5 images (common in single-upload records), supplement with high-res property samples
if (count($active_gallery_list) < 5) {
    foreach ($default_sample_property_images as $sample) {
        $exists = false;
        foreach ($active_gallery_list as $existing) {
            if ($existing['url'] === $sample['url']) { $exists = true; break; }
        }
        if (!$exists) {
            $active_gallery_list[] = $sample;
        }
        if (count($active_gallery_list) >= 7) break;
    }
}

// Fallback safety
if (empty($active_gallery_list)) {
    $active_gallery_list = $default_sample_property_images;
}

$total_gallery_count = count($active_gallery_list);
$hero_initial_img = $active_gallery_list[0]['url'];
$hero_initial_title = $active_gallery_list[0]['title'];
$property_lot_title = !empty($current_item['title']) ? $current_item['title'] : 'Exclusive Luxury Property Auction';
$property_lot_code = !empty($current_item['auction_code']) ? $current_item['auction_code'] : ('LOT-' . ($current_item['id'] ?? '1'));
$property_location = !empty($current_item['location_area']) ? $current_item['location_area'] : (!empty($current_item['location_city']) ? $current_item['location_city'] : 'Hyderabad');
$property_distance = isset($dist_km) && $dist_km !== null ? $dist_km : null;
$is_lot_watched = !empty($is_watched);
$lot_id_num = (int)($current_item['id'] ?? 0);
?>

<!-- ========================================================================
     2. MODERN CSS - BAYUT GALLERY COMPONENT STYLES
     ======================================================================== -->
<style>
/* Bayut Gallery Aesthetics & Animations */
.bayut-gallery-root {
    --bayut-radius: 1rem;
    --bayut-accent: #059669;
    --bayut-accent-hover: #047857;
    --bayut-dark: #0f172a;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
}

.bayut-hero-viewport {
    position: relative;
    overflow: hidden;
    background-color: #0b0f19;
    border-radius: var(--bayut-radius) 0 0 var(--bayut-radius);
}

@media (max-width: 1023px) {
    .bayut-hero-viewport {
        border-radius: var(--bayut-radius);
    }
}

.bayut-hero-img-transition {
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
}

.bayut-thumb-card {
    position: relative;
    overflow: hidden;
    cursor: pointer;
    background-color: #0f172a;
}

.bayut-thumb-card img {
    transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), filter 0.3s ease;
}

.bayut-thumb-card:hover img {
    transform: scale(1.08);
}

.bayut-thumb-card.is-active {
    box-shadow: inset 0 0 0 3px #10b981;
}

.bayut-thumb-card.is-active::after {
    content: '';
    position: absolute;
    inset: 0;
    border: 3px solid #10b981;
    pointer-events-none;
    z-index: 10;
}

/* Glassmorphism Overlays */
.bayut-glass-pill {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: #ffffff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}

.bayut-action-circle {
    width: 40px;
    height: 40px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    color: #1e293b;
    border: 1px solid rgba(255, 255, 255, 0.8);
    cursor: pointer;
}

.bayut-action-circle:hover {
    background: #ffffff;
    transform: translateY(-2px) scale(1.06);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
}

.bayut-action-circle:active {
    transform: translateY(0) scale(0.95);
}

.bayut-nav-arrow {
    width: 44px;
    height: 44px;
    border-radius: 9999px;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s ease;
    opacity: 0;
    cursor: pointer;
}

.bayut-hero-viewport:hover .bayut-nav-arrow {
    opacity: 1;
}

.bayut-nav-arrow:hover {
    background: rgba(5, 150, 105, 0.9);
    border-color: rgba(255, 255, 255, 0.4);
    transform: translateY(-50%) scale(1.08);
}

/* Lightbox Modal */
.bayut-lightbox-overlay {
    position: fixed;
    inset: 0;
    background-color: rgba(2, 6, 23, 0.96);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    z-index: 99999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.3s;
    display: flex;
    flex-direction: column;
}

.bayut-lightbox-overlay.is-active {
    opacity: 1;
    visibility: visible;
}

.bayut-lightbox-strip-item {
    width: 72px;
    height: 52px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    opacity: 0.5;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    flex-shrink: 0;
}

.bayut-lightbox-strip-item:hover {
    opacity: 0.85;
}

.bayut-lightbox-strip-item.is-selected {
    opacity: 1;
    border-color: #10b981;
    transform: scale(1.05);
}

/* Heart animation */
@keyframes heartBeat {
    0% { transform: scale(1); }
    30% { transform: scale(1.3); }
    50% { transform: scale(0.9); }
    100% { transform: scale(1); }
}
.bayut-heart-animate {
    animation: heartBeat 0.45s cubic-bezier(0.17, 0.89, 0.32, 1.49);
}

/* Swipe Indicator Dots */
.bayut-dot {
    width: 7px;
    height: 7px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.4);
    transition: all 0.3s ease;
}

.bayut-dot.is-active {
    width: 22px;
    background: #10b981;
}
</style>

<!-- ========================================================================
     3. CLEAN SEMANTIC HTML MARKUP - BAYUT PROPERTY GALLERY
     ======================================================================== -->
<div class="bayut-gallery-root w-full select-none mb-6" id="bayutGalleryRoot">

    <!-- DESKTOP SHOWCASE: 65% HERO ON LEFT / 35% 2x2 THUMBNAIL GRID ON RIGHT -->
    <div class="hidden lg:grid grid-cols-12 gap-2 h-[470px] rounded-2xl overflow-hidden shadow-sm bg-slate-950 border border-slate-800">
        
        <!-- LEFT: PRIMARY LARGE HERO IMAGE AREA (8 Cols / ~66.6%) -->
        <div class="col-span-8 bayut-hero-viewport h-full group cursor-pointer" 
             id="bayutDesktopHeroContainer"
             onclick="openBayutLightbox(bayutActiveIndex)">
            
            <!-- Main Hero Image -->
            <img id="bayutHeroImg" 
                 src="<?php echo htmlspecialchars($hero_initial_img); ?>" 
                 alt="<?php echo htmlspecialchars($hero_initial_title); ?>" 
                 class="w-full h-full object-cover bayut-hero-img-transition group-hover:scale-[1.02]">

            <!-- Gradient Vignette Overlays for High Contrast Badges -->
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-transparent to-slate-950/50 pointer-events-none"></div>

            <!-- TOP LEFT: Live Auction Badge & Real-Time Countdown Overlay -->
            <div class="absolute top-4 left-4 flex items-center gap-2.5 z-20">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-rose-600/95 backdrop-blur-md text-white text-[11px] font-extrabold uppercase tracking-wider shadow-lg border border-rose-400/30">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                    </span>
                    Live Auction
                </span>

                <!-- Synced Real-Time Countdown Overlay Badge -->
                <span id="bayutHeroTimerBadge" class="bayut-glass-pill inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold">
                    <i class="fa-regular fa-clock text-amber-400 text-xs"></i>
                    <span id="bayutHeroTimerCountdown">Syncing...</span>
                </span>
            </div>

            <!-- TOP RIGHT: Favorite, Share, and Fullscreen Action Icons -->
            <div class="absolute top-4 right-4 flex items-center gap-2 z-20" onclick="event.stopPropagation()">
                <!-- Save / Favorite Button -->
                <button type="button" 
                        id="bayutHeroFavBtn" 
                        onclick="triggerGalleryFavorite(event, <?php echo $lot_id_num; ?>)" 
                        class="bayut-action-circle" 
                        title="<?php echo $is_lot_watched ? 'Remove from Wishlist' : 'Save to Wishlist'; ?>"
                        aria-label="Save to Wishlist">
                    <i class="fa-<?php echo $is_lot_watched ? 'solid' : 'regular'; ?> fa-heart text-rose-500 text-base" id="bayutHeroFavIcon"></i>
                </button>

                <!-- Share Button -->
                <button type="button" 
                        onclick="triggerGalleryShare(event)" 
                        class="bayut-action-circle" 
                        title="Share Listing"
                        aria-label="Share Listing">
                    <i class="fa-solid fa-share-nodes text-slate-700 text-sm"></i>
                </button>

                <!-- Fullscreen Expand Button -->
                <button type="button" 
                        onclick="openBayutLightbox(bayutActiveIndex)" 
                        class="bayut-action-circle" 
                        title="Expand Fullscreen Gallery"
                        aria-label="Expand Fullscreen Gallery">
                    <i class="fa-solid fa-expand text-slate-700 text-sm"></i>
                </button>
            </div>

            <!-- PREV / NEXT HOVER NAVIGATION ARROWS -->
            <button type="button" 
                    id="bayutPrevArrow"
                    onclick="stepGalleryHero(-1, event)" 
                    class="bayut-nav-arrow absolute left-4 top-1/2 -translate-y-1/2 z-20"
                    aria-label="Previous Photo">
                <i class="fa-solid fa-chevron-left text-sm"></i>
            </button>
            <button type="button" 
                    id="bayutNextArrow"
                    onclick="stepGalleryHero(1, event)" 
                    class="bayut-nav-arrow absolute right-4 top-1/2 -translate-y-1/2 z-20"
                    aria-label="Next Photo">
                <i class="fa-solid fa-chevron-right text-sm"></i>
            </button>

            <!-- BOTTOM LEFT: Location & Proximity Tag -->
            <div class="absolute bottom-4 left-4 z-20 flex items-center gap-2">
                <div class="bayut-glass-pill inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium">
                    <i class="fa-solid fa-location-dot text-emerald-400 text-xs"></i>
                    <span class="font-semibold text-slate-100"><?php echo htmlspecialchars($property_location); ?></span>
                    <?php if ($property_distance !== null): ?>
                        <span class="text-slate-400">•</span>
                        <span class="text-emerald-400 font-bold"><?php echo $property_distance; ?> km away</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- BOTTOM RIGHT: Total Photos Counter Pill Badge -->
            <div class="absolute bottom-4 right-4 z-20">
                <button type="button" 
                        onclick="openBayutLightbox(bayutActiveIndex)" 
                        class="bayut-glass-pill hover:bg-slate-900 inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all hover:scale-105 active:scale-95 cursor-pointer border border-white/20">
                    <i class="fa-solid fa-camera text-emerald-400 text-xs"></i>
                    <span id="bayutPhotoPillText">1/<?php echo $total_gallery_count; ?> Photos</span>
                </button>
            </div>
        </div>

        <!-- RIGHT: 2x2 PREVIEW THUMBNAIL GRID (4 Cols / ~33.3%) -->
        <div class="col-span-4 grid grid-cols-2 grid-rows-2 gap-2 h-full">
            <?php 
            // Display up to 4 preview thumbnails from index 1 to 4
            for ($i = 1; $i <= 4; $i++): 
                $img_data = $active_gallery_list[$i] ?? ($active_gallery_list[$i % $total_gallery_count] ?? $active_gallery_list[0]);
                $is_last_slot = ($i === 4);
                $remaining_count = $total_gallery_count - 5; // Photos beyond slot 4
                $show_more_overlay = ($is_last_slot && $total_gallery_count > 5);
            ?>
                <div class="bayut-thumb-card rounded-lg overflow-hidden relative group" 
                     id="bayutThumb_<?php echo $i; ?>"
                     onclick="<?php echo $show_more_overlay ? 'openBayutLightbox(4)' : 'selectBayutImage('.$i.')'; ?>">
                    
                    <img src="<?php echo htmlspecialchars($img_data['url']); ?>" 
                         alt="<?php echo htmlspecialchars($img_data['title']); ?>" 
                         class="w-full h-full object-cover">
                    
                    <!-- Frosted Glass Overlay for "+N More Photos" on the 4th thumbnail -->
                    <?php if ($show_more_overlay): ?>
                        <div class="absolute inset-0 bg-slate-950/75 group-hover:bg-slate-950/85 backdrop-blur-xs transition flex flex-col items-center justify-center text-white text-center p-2 z-10">
                            <i class="fa-solid fa-images text-xl text-emerald-400 mb-1 group-hover:scale-110 transition duration-200"></i>
                            <span class="text-sm font-extrabold tracking-tight">+<?php echo $remaining_count + 1; ?> More</span>
                            <span class="text-[10px] text-slate-300 font-medium uppercase tracking-wider mt-0.5">View All (<?php echo $total_gallery_count; ?>)</span>
                        </div>
                    <?php else: ?>
                        <!-- Subtle dark gradient on hover -->
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-transparent transition"></div>
                        <div class="absolute bottom-2 left-2 px-2 py-0.5 rounded bg-black/60 backdrop-blur-xs text-white text-[10px] font-semibold opacity-0 group-hover:opacity-100 transition">
                            Photo <?php echo $i + 1; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- MOBILE / TABLET SWIPEABLE CAROUSEL (< 1024px) -->
    <div class="lg:hidden relative rounded-2xl overflow-hidden shadow-sm bg-slate-950 border border-slate-800" 
         id="bayutMobileCarousel">
        
        <!-- Mobile Swipe Viewport -->
        <div class="relative h-72 sm:h-80 w-full overflow-hidden cursor-grab active:cursor-grabbing touch-pan-y" 
             id="bayutMobileViewport"
             onclick="openBayutLightbox(bayutActiveIndex)">
            
            <img id="bayutMobileImg" 
                 src="<?php echo htmlspecialchars($hero_initial_img); ?>" 
                 alt="<?php echo htmlspecialchars($hero_initial_title); ?>" 
                 class="w-full h-full object-cover transition-transform duration-300">

            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-transparent to-slate-950/50 pointer-events-none"></div>

            <!-- Top Left: Live Auction & Countdown Pill -->
            <div class="absolute top-3 left-3 flex flex-wrap items-center gap-1.5 z-20">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-rose-600/95 text-white text-[10px] font-bold uppercase tracking-wider shadow">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                    Live
                </span>
                <span id="bayutMobileTimerBadge" class="bayut-glass-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold">
                    <i class="fa-regular fa-clock text-amber-400 text-[10px]"></i>
                    <span id="bayutMobileTimerText">Syncing...</span>
                </span>
            </div>

            <!-- Top Right: Favorite & Share -->
            <div class="absolute top-3 right-3 flex items-center gap-1.5 z-20" onclick="event.stopPropagation()">
                <button type="button" 
                        onclick="triggerGalleryFavorite(event, <?php echo $lot_id_num; ?>)" 
                        class="bayut-action-circle !w-9 !h-9 text-rose-500" 
                        title="Save to Wishlist">
                    <i class="fa-<?php echo $is_lot_watched ? 'solid' : 'regular'; ?> fa-heart text-sm" id="bayutMobileFavIcon"></i>
                </button>
                <button type="button" 
                        onclick="triggerGalleryShare(event)" 
                        class="bayut-action-circle !w-9 !h-9 text-slate-700" 
                        title="Share">
                    <i class="fa-solid fa-share-nodes text-xs"></i>
                </button>
            </div>

            <!-- Mobile Chevrons -->
            <button type="button" 
                    onclick="stepGalleryHero(-1, event)" 
                    class="absolute left-2.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/50 text-white flex items-center justify-center backdrop-blur-xs z-20"
                    aria-label="Previous">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </button>
            <button type="button" 
                    onclick="stepGalleryHero(1, event)" 
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-black/50 text-white flex items-center justify-center backdrop-blur-xs z-20"
                    aria-label="Next">
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </button>

            <!-- Bottom Left: Location -->
            <div class="absolute bottom-3 left-3 z-20">
                <span class="bayut-glass-pill inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-medium">
                    <i class="fa-solid fa-location-dot text-emerald-400 text-[10px]"></i>
                    <span><?php echo htmlspecialchars($property_location); ?></span>
                </span>
            </div>

            <!-- Bottom Right: Photo Counter -->
            <div class="absolute bottom-3 right-3 z-20">
                <span class="bayut-glass-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold">
                    <i class="fa-solid fa-camera text-emerald-400 text-[10px]"></i>
                    <span id="bayutMobileCounterText">1/<?php echo $total_gallery_count; ?></span>
                </span>
            </div>
        </div>

        <!-- Mobile Pagination Dots -->
        <div class="bg-slate-900/90 py-2.5 px-4 flex items-center justify-center gap-1.5" id="bayutMobileDotsContainer">
            <?php for ($d = 0; $d < min($total_gallery_count, 10); $d++): ?>
                <span class="bayut-dot <?php echo $d === 0 ? 'is-active' : ''; ?>" data-dot-index="<?php echo $d; ?>" onclick="selectBayutImage(<?php echo $d; ?>)"></span>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- ========================================================================
     4. FULLSCREEN LIGHTBOX / MODAL VIEWER
     ======================================================================== -->
<div id="bayutLightboxModal" 
     class="bayut-lightbox-overlay" 
     role="dialog" 
     aria-modal="true" 
     aria-label="Property Image Gallery Lightbox"
     tabindex="-1">
    
    <!-- LIGHTBOX HEADER -->
    <div class="p-4 sm:px-6 flex items-center justify-between border-b border-white/10 shrink-0 bg-slate-950/80">
        <div class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg bg-emerald-600/20 text-emerald-400 flex items-center justify-center border border-emerald-500/30">
                <i class="fa-solid fa-images text-sm"></i>
            </span>
            <div>
                <h3 class="text-white font-bold text-sm tracking-tight line-clamp-1 max-w-md">
                    <?php echo htmlspecialchars($property_lot_title); ?>
                </h3>
                <span class="text-xs text-slate-400 font-medium" id="bayutLightboxCounter">
                    Photo 1 of <?php echo $total_gallery_count; ?>
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" 
                    onclick="triggerGalleryShare(event)" 
                    class="w-9 h-9 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition" 
                    title="Share Listing">
                <i class="fa-solid fa-share-nodes text-xs"></i>
            </button>
            <button type="button" 
                    onclick="closeBayutLightbox()" 
                    class="w-9 h-9 rounded-lg bg-rose-600/80 hover:bg-rose-600 text-white flex items-center justify-center transition cursor-pointer" 
                    title="Close (Esc)" 
                    aria-label="Close Gallery">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
    </div>

    <!-- LIGHTBOX MAIN VIEWPORT -->
    <div class="relative flex-1 flex items-center justify-center p-4 sm:p-8 overflow-hidden touch-pan-y" 
         id="bayutLightboxStage">
        
        <!-- Large High-Res Image Container -->
        <div class="relative max-w-6xl w-full h-full flex items-center justify-center">
            <img id="bayutLightboxMainImg" 
                 src="<?php echo htmlspecialchars($hero_initial_img); ?>" 
                 alt="Enlarged view" 
                 class="max-w-full max-h-[72vh] object-contain rounded-xl shadow-2xl transition duration-300">
        </div>

        <!-- Lightbox Left / Right Arrows -->
        <button type="button" 
                onclick="stepLightboxImage(-1)" 
                class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-emerald-600 text-white backdrop-blur-md border border-white/20 flex items-center justify-center transition shadow-2xl cursor-pointer" 
                aria-label="Previous Photo (Left Arrow)">
            <i class="fa-solid fa-chevron-left text-base"></i>
        </button>
        
        <button type="button" 
                onclick="stepLightboxImage(1)" 
                class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-emerald-600 text-white backdrop-blur-md border border-white/20 flex items-center justify-center transition shadow-2xl cursor-pointer" 
                aria-label="Next Photo (Right Arrow)">
            <i class="fa-solid fa-chevron-right text-base"></i>
        </button>
    </div>

    <!-- LIGHTBOX BOTTOM THUMBNAIL FILMSTRIP -->
    <div class="p-3 sm:p-4 border-t border-white/10 bg-slate-950/90 shrink-0 flex items-center justify-center">
        <div class="flex items-center gap-2 overflow-x-auto max-w-4xl py-1 px-2 scrollbar-none" id="bayutLightboxThumbStrip">
            <?php foreach ($active_gallery_list as $k => $item): ?>
                <div class="bayut-lightbox-strip-item <?php echo $k === 0 ? 'is-selected' : ''; ?>" 
                     data-strip-index="<?php echo $k; ?>" 
                     onclick="selectBayutImage(<?php echo $k; ?>)">
                    <img src="<?php echo htmlspecialchars($item['url']); ?>" 
                         alt="Thumb <?php echo $k + 1; ?>" 
                         class="w-full h-full object-cover">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ========================================================================
     5. LIGHTWEIGHT VANILLA JAVASCRIPT LOGIC
     ======================================================================== -->
<script>
(function() {
    // 1. Data Store
    window.bayutGalleryData = <?php echo json_encode($active_gallery_list); ?>;
    window.bayutActiveIndex = 0;
    var totalPhotos = window.bayutGalleryData.length;

    // Elements
    var heroImg = document.getElementById('bayutHeroImg');
    var mobileImg = document.getElementById('bayutMobileImg');
    var photoPillText = document.getElementById('bayutPhotoPillText');
    var mobileCounterText = document.getElementById('bayutMobileCounterText');
    var lightboxModal = document.getElementById('bayutLightboxModal');
    var lightboxMainImg = document.getElementById('bayutLightboxMainImg');
    var lightboxCounter = document.getElementById('bayutLightboxCounter');

    // 2. Select & Update Main View
    window.selectBayutImage = function(index) {
        if (index < 0 || index >= totalPhotos) return;
        window.bayutActiveIndex = index;
        var cur = window.bayutGalleryData[index];

        // Update Desktop Hero
        if (heroImg) {
            heroImg.style.opacity = '0.3';
            setTimeout(function() {
                heroImg.src = cur.url;
                heroImg.alt = cur.title || '';
                heroImg.style.opacity = '1';
            }, 120);
        }

        // Update Mobile Hero
        if (mobileImg) {
            mobileImg.style.opacity = '0.3';
            setTimeout(function() {
                mobileImg.src = cur.url;
                mobileImg.alt = cur.title || '';
                mobileImg.style.opacity = '1';
            }, 120);
        }

        // Update Counters
        if (photoPillText) photoPillText.textContent = (index + 1) + '/' + totalPhotos + ' Photos';
        if (mobileCounterText) mobileCounterText.textContent = (index + 1) + '/' + totalPhotos;

        // Update Desktop Split Thumbnails Active State
        var thumbCards = document.querySelectorAll('.bayut-thumb-card');
        thumbCards.forEach(function(card, idx) {
            if ((idx + 1) === index) {
                card.classList.add('is-active');
            } else {
                card.classList.remove('is-active');
            }
        });

        // Update Mobile Pagination Dots
        var dots = document.querySelectorAll('#bayutMobileDotsContainer .bayut-dot');
        dots.forEach(function(d, dIdx) {
            if (dIdx === (index % dots.length)) {
                d.classList.add('is-active');
            } else {
                d.classList.remove('is-active');
            }
        });

        // Update Lightbox if open
        if (lightboxModal && lightboxModal.classList.contains('is-active')) {
            if (lightboxMainImg) {
                lightboxMainImg.style.opacity = '0.4';
                setTimeout(function() {
                    lightboxMainImg.src = cur.url;
                    lightboxMainImg.style.opacity = '1';
                }, 100);
            }
            if (lightboxCounter) {
                lightboxCounter.textContent = 'Photo ' + (index + 1) + ' of ' + totalPhotos;
            }
            var stripItems = document.querySelectorAll('.bayut-lightbox-strip-item');
            stripItems.forEach(function(si, sIdx) {
                if (sIdx === index) {
                    si.classList.add('is-selected');
                    si.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                } else {
                    si.classList.remove('is-selected');
                }
            });
        }
    };

    // 3. Step Through Hero
    window.stepGalleryHero = function(delta, e) {
        if (e) e.stopPropagation();
        var nextIdx = (window.bayutActiveIndex + delta + totalPhotos) % totalPhotos;
        selectBayutImage(nextIdx);
    };

    // 4. Lightbox Controls
    window.openBayutLightbox = function(startIndex) {
        if (typeof startIndex === 'number') {
            selectBayutImage(startIndex);
        }
        if (!lightboxModal) return;
        lightboxModal.classList.add('is-active');
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', handleLightboxKeyDown);
    };

    window.closeBayutLightbox = function() {
        if (!lightboxModal) return;
        lightboxModal.classList.remove('is-active');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleLightboxKeyDown);
    };

    window.stepLightboxImage = function(delta) {
        var nextIdx = (window.bayutActiveIndex + delta + totalPhotos) % totalPhotos;
        selectBayutImage(nextIdx);
    };

    function handleLightboxKeyDown(e) {
        if (e.key === 'ArrowRight' || e.key === 'Right') {
            stepLightboxImage(1);
        } else if (e.key === 'ArrowLeft' || e.key === 'Left') {
            stepLightboxImage(-1);
        } else if (e.key === 'Escape' || e.key === 'Esc') {
            closeBayutLightbox();
        }
    }

    // Close on backdrop click
    if (lightboxModal) {
        lightboxModal.addEventListener('click', function(e) {
            if (e.target === lightboxModal || e.target.id === 'bayutLightboxStage') {
                closeBayutLightbox();
            }
        });
    }

    // 5. Touch Swipe Support (Mobile Carousel & Lightbox)
    function setupTouchSwipe(elementId, onSwipeLeft, onSwipeRight) {
        var el = document.getElementById(elementId);
        if (!el) return;
        var startX = 0, startY = 0, distThreshold = 45;

        el.addEventListener('touchstart', function(e) {
            var touch = e.changedTouches[0];
            startX = touch.pageX;
            startY = touch.pageY;
        }, { passive: true });

        el.addEventListener('touchend', function(e) {
            var touch = e.changedTouches[0];
            var diffX = touch.pageX - startX;
            var diffY = touch.pageY - startY;

            // Ensure horizontal swipe dominates vertical scroll
            if (Math.abs(diffX) > distThreshold && Math.abs(diffX) > Math.abs(diffY)) {
                if (diffX < 0) {
                    onSwipeLeft();
                } else {
                    onSwipeRight();
                }
            }
        }, { passive: true });
    }

    setupTouchSwipe('bayutMobileViewport', function() { stepGalleryHero(1); }, function() { stepGalleryHero(-1); });
    setupTouchSwipe('bayutLightboxStage', function() { stepLightboxImage(1); }, function() { stepLightboxImage(-1); });

    // 6. Share Listing Handler
    window.triggerGalleryShare = function(e) {
        if (e) e.stopPropagation();
        var shareUrl = window.location.href;
        var shareTitle = <?php echo json_encode($property_lot_title); ?>;
        
        if (navigator.share) {
            navigator.share({
                title: shareTitle,
                text: 'Check out this property auction on Geo Auction System: ' + shareTitle,
                url: shareUrl
            }).catch(function() {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(shareUrl).then(function() {
                if (typeof showLiveModalPopup === 'function') {
                    showLiveModalPopup('Link Copied!', 'Auction listing link copied to your clipboard.', true);
                } else {
                    alert('Auction listing link copied to clipboard!');
                }
            });
        }
    };

    // 7. Favorite / Watchlist Handler (Interacts with existing toggleWatchlist)
    window.triggerGalleryFavorite = function(e, itemId) {
        if (e) e.stopPropagation();
        var favIcon = document.getElementById('bayutHeroFavIcon');
        var mobileFavIcon = document.getElementById('bayutMobileFavIcon');
        
        if (favIcon) favIcon.classList.add('bayut-heart-animate');
        if (mobileFavIcon) mobileFavIcon.classList.add('bayut-heart-animate');

        setTimeout(function() {
            if (favIcon) favIcon.classList.remove('bayut-heart-animate');
            if (mobileFavIcon) mobileFavIcon.classList.remove('bayut-heart-animate');
        }, 500);

        if (typeof toggleWatchlist === 'function') {
            toggleWatchlist(itemId);
        } else {
            // Direct fetch fallback
            var fd = new FormData();
            fd.append('action', 'toggle_watchlist');
            fd.append('item_id', itemId);
            fetch('live_auction.php?ajax=1', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        var isW = data.in_watchlist;
                        var iconClass = 'fa-' + (isW ? 'solid' : 'regular') + ' fa-heart text-rose-500';
                        if (favIcon) favIcon.className = iconClass + ' text-base';
                        if (mobileFavIcon) mobileFavIcon.className = iconClass + ' text-sm';
                    }
                });
        }
    };

    // 8. Sync Hero Countdown Overlay with Main Timer
    setInterval(function() {
        var mainTimer = document.getElementById('timerDisplay');
        var heroCountdown = document.getElementById('bayutHeroTimerCountdown');
        var mobileTimerText = document.getElementById('bayutMobileTimerText');
        
        if (mainTimer) {
            var rawText = mainTimer.innerText || mainTimer.textContent;
            var cleanText = rawText.replace(/Calculating\.\.\./i, 'Ending Soon').trim();
            if (heroCountdown) heroCountdown.textContent = cleanText;
            if (mobileTimerText) mobileTimerText.textContent = cleanText;
        }
    }, 1000);

})();
</script>
