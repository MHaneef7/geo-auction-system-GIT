<?php
/**
 * Admin Login Modal Component
 * File: includes/components/admin_login_modal.php
 * 
 * Reusable AJAX-powered Admin Authentication Modal.
 * Can be included in any page (root, user, or admin directory).
 */

$current_script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$current_folder_name = basename(dirname($current_script_path));
$is_admin_folder     = ($current_folder_name === 'admin');
$is_user_folder      = ($current_folder_name === 'user');

$ajax_endpoint_url   = $is_admin_folder ? 'ajax_admin_login.php' : ($is_user_folder ? '../admin/ajax_admin_login.php' : 'admin/ajax_admin_login.php');
$admin_dashboard_url = $is_admin_folder ? 'dashboard.php' : ($is_user_folder ? '../admin/dashboard.php' : 'admin/dashboard.php');
?>
<!-- Admin Login Modal CSS -->
<style>
.admin-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.78);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 999999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.25s;
}

.admin-modal-backdrop.active {
    opacity: 1;
    visibility: visible;
}

.admin-modal-dialog {
    width: 100%;
    max-width: 440px;
    background: #0f172a;
    color: #f8fafc;
    border: 1px solid rgba(4, 120, 87, 0.4);
    border-radius: 20px;
    padding: 32px 30px 28px 30px;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 30px rgba(4, 120, 87, 0.2);
    transform: scale(0.92) translateY(14px);
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    box-sizing: border-box;
}

.admin-modal-backdrop.active .admin-modal-dialog {
    transform: scale(1) translateY(0);
}

.admin-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.admin-modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.4);
    transform: rotate(90deg);
}

.admin-modal-header {
    text-align: center;
    margin-bottom: 24px;
}

.admin-icon-shield {
    width: 62px;
    height: 62px;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(4, 120, 87, 0.35) 0%, rgba(4, 120, 87, 0.1) 100%);
    border: 1.5px solid rgb(4 120 87);
    color: #34d399;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin: 0 auto 14px auto;
    box-shadow: 0 0 20px rgba(4, 120, 87, 0.35);
    animation: adminPulse 2.5s infinite;
}

@keyframes adminPulse {
    0%, 100% {
        box-shadow: 0 0 15px rgba(4, 120, 87, 0.35);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 0 28px rgba(52, 211, 153, 0.6);
        transform: scale(1.03);
    }
}

.admin-modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 6px 0;
    letter-spacing: -0.3px;
}

.admin-modal-subtitle {
    font-size: 13px;
    color: #94a3b8;
    margin: 0;
    line-height: 1.4;
}

.admin-alert-banner {
    display: none;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 20px;
    align-items: center;
    gap: 10px;
    line-height: 1.4;
    transition: all 0.2s ease;
}

.admin-alert-banner.active {
    display: flex;
}

.admin-alert-banner.error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.4);
    color: #fca5a5;
}

.admin-alert-banner.success {
    background: rgba(4, 120, 87, 0.2);
    border: 1px solid rgba(4, 120, 87, 0.6);
    color: #6ee7b7;
}

.admin-field-group {
    margin-bottom: 18px;
    text-align: left;
}

.admin-field-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #cbd5e1;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.admin-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.admin-input-wrap i.admin-left-icon {
    position: absolute;
    left: 14px;
    color: #64748b;
    font-size: 14px;
    pointer-events: none;
    transition: color 0.2s ease;
}

.admin-input-control {
    width: 100%;
    height: 44px;
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 10px;
    padding: 0 42px 0 40px;
    font-size: 14px;
    color: #f8fafc;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.admin-input-control:focus {
    border-color: rgb(4 120 87);
    background: #0f172a;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.25);
}

.admin-input-control:focus + .admin-left-icon,
.admin-input-wrap:focus-within i.admin-left-icon {
    color: #34d399;
}

.admin-pwd-toggle-btn {
    position: absolute;
    right: 12px;
    background: transparent;
    border: none;
    color: #64748b;
    font-size: 14px;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
}

.admin-pwd-toggle-btn:hover {
    color: #cbd5e1;
}

.admin-btn-submit {
    width: 100%;
    height: 46px;
    background: linear-gradient(135deg, rgb(4 120 87) 0%, rgb(3 98 71) 100%);
    border: 1px solid rgba(52, 211, 153, 0.3);
    border-radius: 10px;
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    margin-top: 24px;
    box-shadow: 0 4px 14px rgba(4, 120, 87, 0.4);
    transition: all 0.2s ease;
}

.admin-btn-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, rgb(5 150 105) 0%, rgb(4 120 87) 100%);
    box-shadow: 0 6px 20px rgba(4, 120, 87, 0.55);
    transform: translateY(-1px);
}

.admin-btn-submit:active:not(:disabled) {
    transform: translateY(0);
}

.admin-btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.admin-modal-footer {
    margin-top: 18px;
    text-align: center;
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
</style>

<!-- Admin Login Modal Markup -->
<div id="adminLoginModalBackdrop" class="admin-modal-backdrop" onclick="if(event.target === this) closeAdminLoginModal();">
    <div class="admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="adminModalHeading">
        <button type="button" class="admin-modal-close" onclick="closeAdminLoginModal();" aria-label="Close admin modal">
            <i class="fas fa-times"></i>
        </button>

        <div class="admin-modal-header">
            <div class="admin-icon-shield">
                <i class="fas fa-shield-halved"></i>
            </div>
            <h3 class="admin-modal-title" id="adminModalHeading">Admin Console</h3>
            <p class="admin-modal-subtitle">Authorized administrator credential verification</p>
        </div>

        <!-- Dynamic Alert Message -->
        <div id="adminModalAlert" class="admin-alert-banner" role="alert">
            <i class="fas fa-circle-exclamation" id="adminModalAlertIcon"></i>
            <span id="adminModalAlertText"></span>
        </div>

        <!-- AJAX Form -->
        <form id="adminModalLoginForm" onsubmit="handleAdminModalLoginSubmit(event);" novalidate>
            <input type="hidden" name="redirect" id="adminModalRedirectUrl" value="<?php echo htmlspecialchars($admin_dashboard_url); ?>">

            <div class="admin-field-group">
                <label class="admin-field-label" for="adminModalUsername">Admin Username or Email</label>
                <div class="admin-input-wrap">
                    <input type="text" 
                           id="adminModalUsername" 
                           name="username" 
                           class="admin-input-control" 
                           placeholder="admin or admin@geoauction.com" 
                           required 
                           autocomplete="username">
                    <i class="fas fa-user-shield admin-left-icon"></i>
                </div>
            </div>

            <div class="admin-field-group">
                <label class="admin-field-label" for="adminModalPassword">Security Password</label>
                <div class="admin-input-wrap">
                    <input type="password" 
                           id="adminModalPassword" 
                           name="password" 
                           class="admin-input-control" 
                           placeholder="••••••••••••" 
                           required 
                           autocomplete="current-password">
                    <i class="fas fa-key admin-left-icon"></i>
                    <button type="button" class="admin-pwd-toggle-btn" onclick="toggleAdminModalPwdVisibility();" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="adminModalEyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" id="adminModalSubmitBtn" class="admin-btn-submit">
                <i class="fas fa-arrow-right-to-bracket" id="adminModalSubmitIcon"></i>
                <span id="adminModalSubmitLabel">Authenticate Admin</span>
            </button>
        </form>

        <div class="admin-modal-footer">
            <i class="fas fa-lock text-[10px]"></i>
            <span>Encrypted Session • Rate-Limit Protected</span>
        </div>
    </div>
</div>

<!-- Admin Login Modal Logic -->
<script>
const ADMIN_AJAX_ENDPOINT = <?php echo json_encode($ajax_endpoint_url); ?>;
const ADMIN_DEFAULT_DASHBOARD = <?php echo json_encode($admin_dashboard_url); ?>;

function openAdminLoginModal(customRedirect = null) {
    const backdrop = document.getElementById('adminLoginModalBackdrop');
    if (!backdrop) return;
    
    // Clear alert
    hideAdminModalAlert();
    
    // Set custom redirect if provided
    const redirectInput = document.getElementById('adminModalRedirectUrl');
    if (redirectInput) {
        redirectInput.value = customRedirect || ADMIN_DEFAULT_DASHBOARD;
    }

    // Reset submit button state
    resetAdminModalSubmitBtn();

    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Auto-focus username field after modal animation
    setTimeout(() => {
        const usernameInput = document.getElementById('adminModalUsername');
        if (usernameInput) usernameInput.focus();
    }, 150);
}

function closeAdminLoginModal() {
    const backdrop = document.getElementById('adminLoginModalBackdrop');
    if (!backdrop) return;
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

function toggleAdminModalPwdVisibility() {
    const pwdInput = document.getElementById('adminModalPassword');
    const eyeIcon = document.getElementById('adminModalEyeIcon');
    if (!pwdInput || !eyeIcon) return;

    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        eyeIcon.className = 'fas fa-eye-slash';
    } else {
        pwdInput.type = 'password';
        eyeIcon.className = 'fas fa-eye';
    }
}

function showAdminModalAlert(type, message) {
    const alertBox = document.getElementById('adminModalAlert');
    const icon = document.getElementById('adminModalAlertIcon');
    const text = document.getElementById('adminModalAlertText');
    if (!alertBox || !icon || !text) return;

    alertBox.className = 'admin-alert-banner active ' + (type === 'success' ? 'success' : 'error');
    icon.className = 'fas ' + (type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation');
    text.textContent = message;
}

function hideAdminModalAlert() {
    const alertBox = document.getElementById('adminModalAlert');
    if (alertBox) alertBox.className = 'admin-alert-banner';
}

function resetAdminModalSubmitBtn() {
    const btn = document.getElementById('adminModalSubmitBtn');
    const icon = document.getElementById('adminModalSubmitIcon');
    const label = document.getElementById('adminModalSubmitLabel');
    if (!btn || !icon || !label) return;
    btn.disabled = false;
    icon.className = 'fas fa-arrow-right-to-bracket';
    label.textContent = 'Authenticate Admin';
}

async function handleAdminModalLoginSubmit(event) {
    event.preventDefault();
    hideAdminModalAlert();

    const usernameInput = document.getElementById('adminModalUsername');
    const passwordInput = document.getElementById('adminModalPassword');
    const redirectInput = document.getElementById('adminModalRedirectUrl');
    const btn = document.getElementById('adminModalSubmitBtn');
    const icon = document.getElementById('adminModalSubmitIcon');
    const label = document.getElementById('adminModalSubmitLabel');

    const username = usernameInput ? usernameInput.value.trim() : '';
    const password = passwordInput ? passwordInput.value.trim() : '';

    if (!username || !password) {
        showAdminModalAlert('error', 'Please enter your administrator username and password.');
        if (!username && usernameInput) usernameInput.focus();
        else if (passwordInput) passwordInput.focus();
        return;
    }

    // Set loading state
    btn.disabled = true;
    icon.className = 'fas fa-circle-notch fa-spin';
    label.textContent = 'Verifying Credentials...';

    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    if (redirectInput && redirectInput.value) {
        formData.append('redirect', redirectInput.value);
    }

    try {
        const response = await fetch(ADMIN_AJAX_ENDPOINT, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        let data;
        try {
            data = await response.json();
        } catch (e) {
            throw new Error('Server returned invalid response format.');
        }

        if (response.ok && data.status === 'success') {
            showAdminModalAlert('success', data.message || 'Authentication successful! Redirecting to dashboard...');
            icon.className = 'fas fa-check';
            label.textContent = 'Access Granted!';

            // Target redirect URL resolution
            let dest = data.redirect_url || ADMIN_DEFAULT_DASHBOARD;
            
            // Normalize path depending on where modal is rendered
            const inAdminFolder = window.location.pathname.includes('/admin/');
            if (inAdminFolder && dest.startsWith('admin/')) {
                dest = dest.replace('admin/', '');
            } else if (!inAdminFolder && !dest.startsWith('admin/') && !dest.startsWith('../')) {
                dest = 'admin/' + dest;
            }

            setTimeout(() => {
                window.location.href = dest;
            }, 800);
        } else {
            resetAdminModalSubmitBtn();
            const errMsg = data.message || 'Authentication failed. Please verify your credentials.';
            showAdminModalAlert('error', errMsg);
            if (passwordInput) {
                passwordInput.value = '';
                passwordInput.focus();
            }
        }
    } catch (err) {
        resetAdminModalSubmitBtn();
        showAdminModalAlert('error', err.message || 'Connection error. Please ensure the server is running.');
    }
}

// Global keyboard listeners: Esc to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdminLoginModal();
    }
});

// Auto-open if query parameter ?admin_login=1 or hash #adminLogin is present
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('admin_login') === '1' || urlParams.get('admin') === '1' || window.location.hash === '#adminLogin') {
        openAdminLoginModal();
    }
});
</script>
