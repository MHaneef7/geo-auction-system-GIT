/**
 * Geo Auction System - Main JavaScript
 * Handles countdown timers, live bidding, notifications, search, and UI interactions
 */

// Document Ready
$(document).ready(function() {
    initCountdowns();
    initNotifications();
    initSearch();
    initBidForm();
    initTooltips();
    initDarkMode();
    initAutoRefresh();
});

// ==========================================
// COUNTDOWN TIMERS
// ==========================================
function initCountdowns() {
    $('.countdown-timer').each(function() {
        const endTime = $(this).data('end');
        if (!endTime) return;
        
        updateCountdown(this, endTime);
        setInterval(() => updateCountdown(this, endTime), 1000);
    });
}

function updateCountdown(el, endTime) {
    const now = new Date().getTime();
    const end = new Date(endTime).getTime();
    const diff = Math.max(0, Math.floor((end - now) / 1000));
    
    if (diff <= 0) {
        $(el).html('<span class="text-danger">Ended</span>');
        return;
    }
    
    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const s = diff % 60;
    
    $(el).html(
        String(h).padStart(2, '0') + ':' +
        String(m).padStart(2, '0') + ':' +
        String(s).padStart(2, '0')
    );
    
    // Add urgency class if less than 1 hour
    if (diff < 3600) {
        $(el).addClass('text-danger').removeClass('text-muted');
    }
}

// ==========================================
// SEARCH
// ==========================================
let searchTimeout;
function initSearch() {
    $('#searchInput, #searchToolbar').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val();
        const results = $('#searchResults');
        
        if (query.length < 2) {
            results.hide();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            $.ajax({
                url: 'ajax/search.php',
                method: 'GET',
                data: { q: query },
                dataType: 'json',
                success: function(data) {
                    if (data && data.results && data.results.length > 0) {
                        const itemsHtml = data.results.map(r => `
                            <a href="${r.url}" class="d-flex align-items-center gap-3 p-2 text-decoration-none border-bottom search-item-hover">
                                <img src="${r.image}" style="width:40px;height:40px;object-fit:cover;border-radius:6px;" onerror="this.src='assets/image/placeholder.png'">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold small text-truncate text-dark">${r.title}</div>
                                    <div class="text-success small fw-bold">${r.price}</div>
                                </div>
                            </a>
                        `).join('');
                        results.html(itemsHtml).show();
                    } else {
                        results.html('<div class="p-3 text-muted small text-center">No results found</div>').show();
                    }
                },
                error: function() {
                    results.html('<div class="p-3 text-danger small text-center">Search error. Please try again.</div>').show();
                }
            });
        }, 300);
    });
    
    // Close search on outside click
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#searchInput, #searchResults').length) {
            $('#searchResults').hide();
        }
    });
}

// ==========================================
// LIVE BIDDING (AJAX)
// ==========================================
function initBidForm() {
    $(document).on('submit', '.bid-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const data = form.serialize();
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Placing...');
        
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: data + '&ajax=1',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('success', response.message);
                    form.find('input[name="bid_amount"]').val('');
                    loadBidHistory();
                } else {
                    showToast('error', response.message);
                }
            },
            error: function() {
                showToast('error', 'Network error. Please try again.');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-gavel me-1"></i> Place Bid');
            }
        });
    });
}

// Load bid history via AJAX
function loadBidHistory(auctionId) {
    if (!auctionId) {
        auctionId = $('#bidHistory').data('auction-id');
    }
    if (!auctionId) return;
    
    $.ajax({
        url: 'live_auction.php?ajax=1&id=' + auctionId,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.html) {
                $('#bidHistory').html(data.html);
            }
        }
    });
}

// ==========================================
// NOTIFICATIONS
// ==========================================
function initNotifications() {
    loadNotifications();
    setInterval(loadNotifications, 30000); // Poll every 30s
    
    // Mark as read on click
    $(document).on('click', '.notification-item', function() {
        const id = $(this).data('id');
        if (id) {
            $.post('mark_as_read.php?notification_id=' + id);
            $(this).removeClass('unread');
        }
    });
}

function loadNotifications() {
    $.ajax({
        url: 'ajax/notification.php?action=fetch',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            const count = data.unread !== undefined ? data.unread : (data.count !== undefined ? data.count : 0);
            $('#notificationCount').text(count);
            if (count > 0) {
                $('#notificationCount').show();
            } else {
                $('#notificationCount').hide();
            }
            if (data.html) {
                $('#notificationList').html(data.html);
            }
        }
    });
}

// ==========================================
// TOAST NOTIFICATIONS
// ==========================================
function showToast(type, message) {
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const bg = type === 'success' ? 'bg-success' : 'bg-danger';
    
    const toast = `
        <div class="toast align-items-center text-white ${bg} border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${icon} me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    const container = $('#toastContainer');
    if (!container.length) {
        $('body').append('<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>');
    }
    
    $('#toastContainer').append(toast);
    
    setTimeout(() => {
        $('#toastContainer .toast:first').remove();
    }, 5000);
}

// ==========================================
// TOOLTIPS
// ==========================================
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) {
        return new bootstrap.Tooltip(el);
    });
}

// ==========================================
// DARK MODE TOGGLE
// ==========================================
function initDarkMode() {
    const saved = localStorage.getItem('darkMode');
    if (saved === 'true') {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        $('#darkModeToggle').html('<i class="fas fa-sun"></i>');
    }
    
    $(document).on('click', '#darkModeToggle', function() {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-bs-theme');
            localStorage.setItem('darkMode', 'false');
            $(this).html('<i class="fas fa-moon"></i>');
        } else {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            localStorage.setItem('darkMode', 'true');
            $(this).html('<i class="fas fa-sun"></i>');
        }
    });
}

// ==========================================
// AUTO REFRESH for live auctions
// ==========================================
function initAutoRefresh() {
    // Auto-refresh bid data every 10 seconds on live auction pages
    if ($('#liveAuctionData').length) {
        setInterval(() => {
            const auctionId = $('#liveAuctionData').data('auction-id');
            if (auctionId) {
                loadBidHistory(auctionId);
                $.ajax({
                    url: '/geo-auction-system/public/auction/current-price/' + auctionId,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (data.price) {
                            $('#currentPrice').text('$' + Number(data.price).toLocaleString('en-US', {minimumFractionDigits: 2}));
                        }
                        if (data.bidder) {
                            $('#highestBidder').text(data.bidder);
                        }
                    }
                });
            }
        }, 10000);
    }
}

// ==========================================
// CUSTOM POPUP / MODAL DIALOG (Alert & Confirm Replacement)
// ==========================================
function openPopup(title, message, options = {}) {
    let modal = document.getElementById("customModal");
    if (!modal) {
        modal = document.createElement("div");
        modal.id = "customModal";
        modal.className = "modal-overlay hidden";
        modal.innerHTML = `
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="customModalTitle">Created Successfully!</h3>
              <button class="close-x" onclick="closePopup()">&times;</button>
            </div>
            <div class="modal-body" id="customModalBody">
              <p>Aapka naya item kamyabi se create ho chuka hai.</p>
            </div>
            <div class="modal-footer" id="customModalFooter">
              <button class="btn secondary" onclick="closePopup()">OK</button>
            </div>
          </div>
        `;
        document.body.appendChild(modal);

        modal.addEventListener("click", (e) => {
            if (e.target === modal) closePopup();
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && modal && !modal.classList.contains("hidden")) {
                closePopup();
            }
        });
    }

    const titleEl = document.getElementById("customModalTitle");
    const bodyEl = document.getElementById("customModalBody");
    const footerEl = document.getElementById("customModalFooter");

    if (title && titleEl) titleEl.innerHTML = title;
    if (message && bodyEl) bodyEl.innerHTML = typeof message === 'string' ? `<p>${message}</p>` : message;

    if (footerEl) {
        if (options && options.isConfirm) {
            footerEl.innerHTML = `
                <button type="button" class="btn secondary" id="modalCancelBtn">${options.cancelText || 'Cancel'}</button>
                <button type="button" class="btn primary" id="modalConfirmBtn">${options.confirmText || 'Confirm'}</button>
            `;
            const cancelBtn = document.getElementById("modalCancelBtn");
            const confirmBtn = document.getElementById("modalConfirmBtn");
            if (cancelBtn) {
                cancelBtn.onclick = () => {
                    closePopup();
                    if (typeof options.onCancel === 'function') options.onCancel();
                };
            }
            if (confirmBtn) {
                confirmBtn.onclick = () => {
                    closePopup();
                    if (typeof options.onConfirm === 'function') options.onConfirm();
                };
            }
        } else if (options && (options.okText || options.onConfirm)) {
            footerEl.innerHTML = `
                <button type="button" class="btn primary" id="modalOkBtn">${options.okText || 'OK'}</button>
            `;
            const okBtn = document.getElementById("modalOkBtn");
            if (okBtn) {
                okBtn.onclick = () => {
                    closePopup();
                    if (typeof options.onConfirm === 'function') options.onConfirm();
                };
            }
        } else {
            footerEl.innerHTML = `<button type="button" class="btn secondary" onclick="closePopup()">OK</button>`;
        }
    }

    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
}

function closePopup() {
    const modal = document.getElementById("customModal");
    if (modal) {
        modal.classList.add("hidden");
        document.body.style.overflow = "";
    }
}

// Replaces default browser confirm() with custom modal
function confirmAction(message, callback) {
    openPopup("Please Confirm", message || "Are you sure you want to proceed?", {
        isConfirm: true,
        confirmText: "Yes, Proceed",
        onConfirm: callback
    });
}

// ==========================================
// QUICK BID BUTTONS
// ==========================================
function setBidAmount(amount) {
    $('#bidAmount').val(amount);
}

// ==========================================
// CHART.JS DASHBOARD CHARTS
// ==========================================
function initRevenueChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: data,
                borderColor: '#198754',
                backgroundColor: 'rgba(25,135,84,0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '$' + value.toLocaleString(); }
                    }
                }
            }
        }
    });
}

function initAuctionsChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: ['#198754', '#ffc107', '#6c757d', '#0dcaf0'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
