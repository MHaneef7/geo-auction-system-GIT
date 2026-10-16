/**
 * Geo Auction System - AJAX Handlers
 * Dedicated AJAX functions for live bidding, search, and notifications
 */

// ==========================================
// BIDDING AJAX
// ==========================================
const BidAPI = {
    place: function(auctionId, amount, callback) {
        $.ajax({
            url: 'ajax/bid.php',
            method: 'POST',
            data: { item_id: auctionId, bid_amount: amount, ajax: 1 },
            dataType: 'json',
            success: callback,
            error: function() {
                showToast('error', 'Failed to place bid. Please try again.');
            }
        });
    },
    
    history: function(auctionId, callback) {
        $.ajax({
            url: 'live_auction.php?ajax=1&id=' + auctionId,
            method: 'GET',
            dataType: 'json',
            success: callback
        });
    },
    
    currentPrice: function(auctionId, callback) {
        $.ajax({
            url: 'live_auction.php?ajax=1&id=' + auctionId,
            method: 'GET',
            dataType: 'json',
            success: callback
        });
    }
};

// ==========================================
// SEARCH AJAX
// ==========================================
const SearchAPI = {
    quick: function(query, callback) {
        $.ajax({
            url: 'ajax/search.php',
            method: 'GET',
            data: { q: query },
            dataType: 'json',
            success: callback
        });
    },
    
    advanced: function(params, callback) {
        $.ajax({
            url: 'search.php',
            method: 'GET',
            data: params,
            dataType: 'json',
            success: callback
        });
    }
};

// ==========================================
// NOTIFICATION AJAX
// ==========================================
const NotificationAPI = {
    fetch: function(callback) {
        $.ajax({
            url: 'ajax/notification.php?action=fetch',
            method: 'GET',
            dataType: 'json',
            success: callback
        });
    },
    
    markRead: function(id) {
        $.post('ajax/notification.php?action=mark_one&id=' + id);
    },
    
    markAllRead: function() {
        $.post('ajax/notification.php?action=mark_read');
    }
};

// ==========================================
// WISHLIST AJAX
// ==========================================
const WishlistAPI = {
    toggle: function(auctionId, callback) {
        $.ajax({
            url: 'user/wishlist.php?ajax=1&action=toggle',
            method: 'POST',
            data: { auction_id: auctionId, ajax: 1 },
            dataType: 'json',
            success: callback
        });
    },
    
    check: function(auctionId, callback) {
        $.ajax({
            url: 'user/wishlist.php?ajax=1&action=check&id=' + auctionId,
            method: 'GET',
            dataType: 'json',
            success: callback
        });
    }
};

// ==========================================
// WATCHLIST TOGGLE
// ==========================================
$(document).on('click', '.wishlist-btn', function(e) {
    e.preventDefault();
    const btn = $(this);
    const auctionId = btn.data('auction-id');
    
    if (!auctionId) return;
    
    WishlistAPI.toggle(auctionId, function(response) {
        if (response.status === 'added') {
            btn.addClass('active').html('<i class="fas fa-heart"></i>');
            showToast('success', 'Added to wishlist');
        } else if (response.status === 'removed') {
            btn.removeClass('active').html('<i class="far fa-heart"></i>');
            showToast('success', 'Removed from wishlist');
        } else if (response.redirect) {
            window.location.href = response.redirect;
        }
    });
});
