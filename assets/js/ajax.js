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
            url: '/geo-auction-system/public/bid/place',
            method: 'POST',
            data: { auction_id: auctionId, amount: amount, ajax: 1 },
            dataType: 'json',
            success: callback,
            error: function() {
                showToast('error', 'Failed to place bid. Please try again.');
            }
        });
    },
    
    history: function(auctionId, callback) {
        $.ajax({
            url: '/geo-auction-system/public/bid/history/' + auctionId,
            method: 'GET',
            dataType: 'json',
            success: callback
        });
    },
    
    currentPrice: function(auctionId, callback) {
        $.ajax({
            url: '/geo-auction-system/public/auction/current-price/' + auctionId,
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
            url: '/geo-auction-system/public/search/ajax',
            method: 'GET',
            data: { q: query },
            dataType: 'json',
            success: callback
        });
    },
    
    advanced: function(params, callback) {
        $.ajax({
            url: '/geo-auction-system/public/search',
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
            url: '/geo-auction-system/public/notification/fetch',
            method: 'GET',
            dataType: 'json',
            success: callback
        });
    },
    
    markRead: function(id) {
        $.post('/geo-auction-system/public/notification/read/' + id);
    },
    
    markAllRead: function() {
        $.post('/geo-auction-system/public/notification/read-all');
    }
};

// ==========================================
// WISHLIST AJAX
// ==========================================
const WishlistAPI = {
    toggle: function(auctionId, callback) {
        $.ajax({
            url: '/geo-auction-system/public/wishlist/toggle',
            method: 'POST',
            data: { auction_id: auctionId, ajax: 1 },
            dataType: 'json',
            success: callback
        });
    },
    
    check: function(auctionId, callback) {
        $.ajax({
            url: '/geo-auction-system/public/wishlist/check/' + auctionId,
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
