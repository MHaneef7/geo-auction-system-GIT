<?php
/**
 * Full-Screen Loading Spinner Overlay Component
 * File: includes/components/loading_spinner_overlay.php
 * 
 * Usage:
 *   include_once __DIR__ . '/includes/components/loading_spinner_overlay.php';
 * 
 * JavaScript API:
 *   showLoader("Authenticating...");
 *   hideLoader();
 */
?>
<!-- Full-Screen Loading Spinner Overlay -->
<div id="fullScreenLoader" class="geo-loader-overlay" role="status" aria-live="polite" aria-hidden="true">
  <div class="geo-loader-box">
    <!-- Clean Circular Two-Arrow Loop SVG Spinner -->
    <svg class="geo-spinner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polyline points="23 4 23 10 17 10"></polyline>
      <polyline points="1 20 1 14 7 14"></polyline>
      <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
    </svg>
    <div id="loaderMessage" class="geo-loader-text">Authenticating...</div>
    <div class="geo-loader-subtext">Please wait a moment</div>
  </div>
</div>

<style>
/* Full-Screen Semi-Transparent Overlay with Backdrop Blur */
.geo-loader-overlay {
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 999999;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.25s ease, visibility 0.25s ease;
  user-select: none;
}

.geo-loader-overlay.active {
  opacity: 1;
  visibility: visible;
}

.geo-loader-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2.25rem 2.5rem;
  border-radius: 1rem;
  background: #ffffff;
  color: #000000;
  border: 1px solid rgba(0, 0, 0, 0.08);
  box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.14), 0 4px 16px rgba(0, 0, 0, 0.06);
  transform: scale(0.92);
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.geo-loader-overlay.active .geo-loader-box {
  transform: scale(1);
}

/* Two-Arrow Loop Spinner with 360-degree rotation */
.geo-spinner-icon {
  width: 52px;
  height: 52px;
  color: #000000;
  animation: geoSpin 0.85s linear infinite;
}

@keyframes geoSpin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.geo-loader-text {
  margin-top: 1.15rem;
  color: #000000;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.01em;
}

.geo-loader-subtext {
  margin-top: 0.35rem;
  color: #334155;
  font-size: 0.825rem;
}
</style>

<script>
/**
 * Show the full-screen loading spinner overlay
 * @param {string} [message="Authenticating..."]
 */
function showLoader(message = 'Authenticating...') {
  const loader = document.getElementById('fullScreenLoader');
  const msgEl = document.getElementById('loaderMessage');
  if (loader) {
    if (msgEl) msgEl.textContent = message;
    loader.classList.add('active');
    loader.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
}

/**
 * Hide the full-screen loading spinner overlay
 */
function hideLoader() {
  const loader = document.getElementById('fullScreenLoader');
  if (loader) {
    loader.classList.remove('active');
    loader.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
}

// Ensure loader is hidden if user navigates back using browser history (BFCache)
window.addEventListener('pageshow', function(event) {
  hideLoader();
});
</script>
