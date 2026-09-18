/**
 * Geo Auction System - Form Validation
 * Client-side validation for all forms
 */

$(document).ready(function() {
    validateRegistrationForm();
    validateLoginForm();
    validateAuctionForm();
    validateBidForm();
    validatePasswordForm();
    validateContactForm();
});

// ==========================================
// REGISTRATION FORM
// ==========================================
function validateRegistrationForm() {
    $('#registerForm').on('submit', function(e) {
        const name = $(this).find('input[name="name"]').val().trim();
        const email = $(this).find('input[name="email"]').val().trim();
        const password = $(this).find('input[name="password"]').val();
        const confirm = $(this).find('input[name="confirm_password"]').val();
        
        clearErrors(this);
        
        if (!name) { showFieldError(this, 'name', 'Name is required'); e.preventDefault(); return; }
        if (!email) { showFieldError(this, 'email', 'Email is required'); e.preventDefault(); return; }
        if (!isValidEmail(email)) { showFieldError(this, 'email', 'Invalid email format'); e.preventDefault(); return; }
        if (!password || password.length < 6) { showFieldError(this, 'password', 'Password must be at least 6 characters'); e.preventDefault(); return; }
        if (password !== confirm) { showFieldError(this, 'confirm_password', 'Passwords do not match'); e.preventDefault(); return; }
    });
}

// ==========================================
// LOGIN FORM
// ==========================================
function validateLoginForm() {
    $('#loginForm').on('submit', function(e) {
        const email = $(this).find('input[name="email"]').val().trim();
        const password = $(this).find('input[name="password"]').val();
        
        clearErrors(this);
        
        if (!email) { showFieldError(this, 'email', 'Email is required'); e.preventDefault(); return; }
        if (!password) { showFieldError(this, 'password', 'Password is required'); e.preventDefault(); return; }
    });
}

// ==========================================
// AUCTION FORM (Create/Edit)
// ==========================================
function validateAuctionForm() {
    $('#auctionForm').on('submit', function(e) {
        const title = $(this).find('input[name="title"]').val().trim();
        const price = $(this).find('input[name="starting_price"]').val();
        const endTime = $(this).find('input[name="end_time"]').val();
        
        clearErrors(this);
        
        if (!title || title.length < 3) { showFieldError(this, 'title', 'Title must be at least 3 characters'); e.preventDefault(); return; }
        if (!price || parseFloat(price) <= 0) { showFieldError(this, 'starting_price', 'Starting price must be greater than 0'); e.preventDefault(); return; }
        if (!endTime) { showFieldError(this, 'end_time', 'End time is required'); e.preventDefault(); return; }
        
        // Validate end time is in the future
        const end = new Date(endTime).getTime();
        const now = new Date().getTime();
        if (end <= now) { showFieldError(this, 'end_time', 'End time must be in the future'); e.preventDefault(); return; }
    });
}

// ==========================================
// BID FORM
// ==========================================
function validateBidForm() {
    $('.bid-form').on('submit', function(e) {
        const amount = $(this).find('input[name="bid_amount"]').val();
        const minAmount = $(this).find('input[name="bid_amount"]').attr('min');
        
        clearErrors(this);
        
        if (!amount || parseFloat(amount) <= 0) {
            showFieldError(this, 'bid_amount', 'Please enter a valid bid amount');
            e.preventDefault();
            return;
        }
        
        if (minAmount && parseFloat(amount) < parseFloat(minAmount)) {
            showFieldError(this, 'bid_amount', 'Minimum bid is $' + parseFloat(minAmount).toLocaleString());
            e.preventDefault();
            return;
        }
    });
}

// ==========================================
// PASSWORD CHANGE FORM
// ==========================================
function validatePasswordForm() {
    $('#passwordForm').on('submit', function(e) {
        const current = $(this).find('input[name="current_password"]').val();
        const newPass = $(this).find('input[name="new_password"]').val();
        const confirm = $(this).find('input[name="confirm_password"]').val();
        
        clearErrors(this);
        
        if (!current) { showFieldError(this, 'current_password', 'Current password is required'); e.preventDefault(); return; }
        if (!newPass || newPass.length < 6) { showFieldError(this, 'new_password', 'New password must be at least 6 characters'); e.preventDefault(); return; }
        if (newPass !== confirm) { showFieldError(this, 'confirm_password', 'Passwords do not match'); e.preventDefault(); return; }
    });
}

// ==========================================
// CONTACT FORM
// ==========================================
function validateContactForm() {
    $('#contactForm').on('submit', function(e) {
        const name = $(this).find('input[name="name"]').val().trim();
        const email = $(this).find('input[name="email"]').val().trim();
        const message = $(this).find('textarea[name="message"]').val().trim();
        
        clearErrors(this);
        
        if (!name) { showFieldError(this, 'name', 'Name is required'); e.preventDefault(); return; }
        if (!email) { showFieldError(this, 'email', 'Email is required'); e.preventDefault(); return; }
        if (!isValidEmail(email)) { showFieldError(this, 'email', 'Invalid email format'); e.preventDefault(); return; }
        if (!message || message.length < 10) { showFieldError(this, 'message', 'Message must be at least 10 characters'); e.preventDefault(); return; }
    });
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function showFieldError(form, field, message) {
    const input = $(form).find(`[name="${field}"]`);
    input.addClass('is-invalid');
    
    // Remove existing error
    input.next('.invalid-feedback').remove();
    
    // Add error message
    input.after(`<div class="invalid-feedback">${message}</div>`);
}

function clearErrors(form) {
    $(form).find('.is-invalid').removeClass('is-invalid');
    $(form).find('.invalid-feedback').remove();
}

// Show general alert
function showAlert(message, type) {
    const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
    const html = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    
    $('.alert-container').html(html);
    
    setTimeout(() => {
        $('.alert-container .alert').fadeOut();
    }, 5000);
}

// Password strength indicator
$(document).on('input', 'input[name="password"]', function() {
    const pw = $(this).val();
    const strength = getPasswordStrength(pw);
    const bar = $(this).closest('.form-group').find('.password-strength');
    

    
    const fill = $(this).closest('.form-group').find('.progress-bar');
    const text = $(this).closest('.form-group').find('small');
    
    if (pw.length === 0) {
        fill.css({width: '0%', background: '#eee'});
        text.text('');
        return;
    }
    
    const levels = [
        { width: '20%', color: '#dc3545', label: 'Weak' },
        { width: '40%', color: '#fd7e14', label: 'Fair' },
        { width: '60%', color: '#ffc107', label: 'Good' },
        { width: '80%', color: '#198754', label: 'Strong' },
        { width: '100%', color: '#146c43', label: 'Very Strong' }
    ];
    
    const idx = Math.min(strength, 4);
    fill.css({width: levels[idx].width, background: levels[idx].color});
    text.text(levels[idx].label).css('color', levels[idx].color);
});

function getPasswordStrength(pw) {
    let score = 0;
    if (pw.length >= 6) score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return score;
}

// Confirm password match
$(document).on('input', 'input[name="confirm_password"]', function() {
    const pw = $('input[name="password"]').val();
    const confirm = $(this).val();
    const msg = $(this).closest('.form-group').find('.confirm-match');
    
    if (!msg.length) {
        $(this).after('<small class="confirm-match mt-1 d-block"></small>');
    }
    
    const el = $(this).closest('.form-group').find('.confirm-match');
    if (confirm.length === 0) {
        el.text('');
        return;
    }
    
    if (pw === confirm) {
        el.text('✓ Passwords match').css('color', '#198754');
        $(this).removeClass('is-invalid').addClass('is-valid');
    } else {
        el.text('✗ Passwords do not match').css('color', '#dc3545');
        $(this).removeClass('is-valid').addClass('is-invalid');
    }
});
