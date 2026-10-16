<?php
/**
 * Messages & Real-Time Conversation Portal
 * File: messages.php
 * 
 * Tech Stack: PHP 8+, OOP PHP, PDO, Vanilla JS, Fetch API, Web Audio API
 * Features:
 * - Two-column responsive layout (Conversations list + Active Chat)
 * - Auto-select conversation or initiate new conversation from query param (?user_id=X&item_id=Y)
 * - Outgoing vs incoming styled chat bubbles with timestamps
 * - "Regarding Lot" context card when chatting about an auction item
 * - Polling every 5 seconds for new messages without reloading
 * - Synthesized Web Audio API chime on new incoming message
 * - Slide-in toast alerts
 * - Instant unread count update
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/MessageService.php';

$loginRedirectTarget = 'messages.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
require_login('login.php?redirect=' . urlencode($loginRedirectTarget));

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$msgService    = new MessageService();

$targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$targetItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Prevent user chatting with themselves
if ($targetUserId === $currentUserId) {
    $targetUserId = 0;
}

// Fetch initial target user info if provided
$targetUser = null;
if ($targetUserId > 0) {
    $targetUser = $msgService->getUser($targetUserId);
    if (!$targetUser) {
        $targetUserId = 0;
    }
}

// Fetch initial target item info if provided
$targetItem = null;
if ($targetItemId > 0) {
    $targetItem = $msgService->getItem($targetItemId);
}

// Preload conversations & all users
$conversations = $msgService->getConversations($currentUserId);
$allUsers      = $msgService->getAllUsers($currentUserId);

// Determine active partner ID
$activePartnerId = $targetUserId;
if ($activePartnerId <= 0 && !empty($conversations)) {
    $activePartnerId = (int)$conversations[0]['partner_id'];
}

include_once __DIR__ . '/includes/header.php';
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

<style>
/* --------------------------------------------------------------------------
   Messages Portal Styling - Clean Geo-Auction Theme
   -------------------------------------------------------------------------- */
:root {
    --chat-primary: #047857;
    --chat-primary-hover: #065f46;
    --chat-accent: #e67e22;
    --chat-bg: #f8fafc;
    --chat-border: #e2e8f0;
    --chat-surface: #ffffff;
    --chat-text: #0f172a;
    --chat-muted: #64748b;
    --bubble-in: #ffffff;
    --bubble-out: #047857;
    --bubble-out-text: #ffffff;
}

html.dark-theme {
    --chat-bg: #0b0f19;
    --chat-border: #1e293b;
    --chat-surface: #0f172a;
    --chat-text: #f8fafc;
    --chat-muted: #94a3b8;
    --bubble-in: #1e293b;
    --bubble-out: #047857;
    --bubble-out-text: #ffffff;
}

.chat-container-card {
    background: var(--chat-surface);
    border: 1px solid var(--chat-border);
    border-radius: 16px;
    height: calc(100vh - 120px);
    min-height: 580px;
    display: flex;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    position: relative;
}

/* ── Sidebar Conversations ── */
.chat-sidebar {
    width: 340px;
    border-right: 1px solid var(--chat-border);
    display: flex;
    flex-direction: column;
    background: var(--chat-surface);
    flex-shrink: 0;
    transition: transform 0.25s ease;
}

.chat-sidebar-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--chat-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chat-sidebar-title {
    font-size: 18px;
    font-weight: 800;
    color: var(--chat-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chat-search-wrap {
    padding: 12px 16px;
    border-bottom: 1px solid var(--chat-border);
    position: relative;
}

.chat-search-wrap input {
    width: 100%;
    padding: 9px 14px 9px 34px;
    border-radius: 20px;
    border: 1px solid var(--chat-border);
    background: var(--chat-bg);
    color: var(--chat-text);
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
}

.chat-search-wrap input:focus {
    border-color: var(--chat-primary);
}

.chat-search-wrap i {
    position: absolute;
    left: 28px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--chat-muted);
    font-size: 13px;
}

.chat-conversations-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.chat-convo-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
    color: inherit;
    position: relative;
}

.chat-convo-item:hover {
    background: var(--chat-bg);
}

.chat-convo-item.active {
    background: rgba(4, 120, 87, 0.1);
    border: 1px solid rgba(4, 120, 87, 0.25);
}

.chat-convo-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    border: 2px solid var(--chat-border);
    background: #e2e8f0;
}

.chat-convo-info {
    flex: 1;
    min-width: 0;
}

.chat-convo-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 3px;
}

.chat-convo-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--chat-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-convo-time {
    font-size: 11px;
    color: var(--chat-muted);
    flex-shrink: 0;
}

.chat-convo-preview {
    font-size: 12px;
    color: var(--chat-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
}

.chat-convo-item.active .chat-convo-name {
    color: var(--chat-primary);
}

.chat-unread-badge {
    background: var(--chat-accent);
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 12px;
    margin-left: 6px;
    flex-shrink: 0;
}

/* ── Sidebar Tabs ── */
.chat-tab-bar {
    display: flex;
    padding: 0 12px 10px 12px;
    gap: 8px;
    border-bottom: 1px solid var(--chat-border);
}

.chat-tab-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 8px;
    border: 1px solid transparent;
    background: transparent;
    color: var(--chat-muted);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}

.chat-tab-btn:hover {
    background: var(--chat-bg);
    color: var(--chat-text);
}

.chat-tab-btn.active {
    background: rgba(4, 120, 87, 0.1);
    color: var(--chat-primary);
    border-color: rgba(4, 120, 87, 0.25);
    font-weight: 700;
}

.chat-tab-count {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 10px;
    background: var(--chat-border);
    color: var(--chat-muted);
    font-weight: 700;
}

.chat-tab-btn.active .chat-tab-count {
    background: var(--chat-primary);
    color: #fff;
}

/* ── Section Headers in Sidebar ── */
.chat-section-header {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--chat-muted);
    padding: 10px 12px 6px 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ── User Item in Sidebar ── */
.chat-user-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
    color: inherit;
    border: 1px solid transparent;
}

.chat-user-item:hover {
    background: var(--chat-bg);
    border-color: var(--chat-border);
}

.chat-user-item.active {
    background: rgba(4, 120, 87, 0.1);
    border-color: rgba(4, 120, 87, 0.25);
}

.chat-role-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 12px;
    text-transform: capitalize;
    display: inline-block;
}

.role-admin {
    background: #fee2e2;
    color: #991b1b;
}

.role-seller {
    background: #fef3c7;
    color: #92400e;
}

.role-buyer {
    background: #e0f2fe;
    color: #0369a1;
}

html.dark-theme .role-admin {
    background: #7f1d1d;
    color: #fecaca;
}

html.dark-theme .role-seller {
    background: #78350f;
    color: #fde68a;
}

html.dark-theme .role-buyer {
    background: #0c4a6e;
    color: #bae6fd;
}

.chat-msg-btn-pill {
    background: var(--chat-primary);
    color: #ffffff;
    border: none;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.chat-msg-btn-pill:hover {
    background: var(--chat-primary-hover);
    transform: scale(1.03);
}

.chat-empty-intro {
    text-align: center;
    padding: 20px 14px 10px 14px;
    color: var(--chat-muted);
}

.chat-empty-intro-icon {
    font-size: 32px;
    color: var(--chat-primary);
    margin-bottom: 8px;
}

.chat-empty-intro-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--chat-text);
    margin-bottom: 4px;
}

.chat-empty-intro-sub {
    font-size: 12px;
    color: var(--chat-muted);
    line-height: 1.4;
}

/* ── Main Chat Area ── */
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--chat-bg);
    min-width: 0;
    position: relative;
}

.chat-header {
    padding: 14px 20px;
    background: var(--chat-surface);
    border-bottom: 1px solid var(--chat-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    z-index: 10;
}

.chat-header-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.chat-btn-back {
    display: none;
    background: none;
    border: none;
    color: var(--chat-muted);
    font-size: 18px;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 8px;
}

.chat-btn-back:hover {
    background: var(--chat-bg);
    color: var(--chat-text);
}

.chat-header-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--chat-primary);
}

.chat-header-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--chat-text);
    margin: 0;
    line-height: 1.2;
}

.chat-header-status {
    font-size: 11px;
    color: var(--chat-primary);
    font-weight: 600;
}

/* Regarding Lot Item Banner */
.chat-lot-banner {
    background: var(--chat-surface);
    border-bottom: 1px solid var(--chat-border);
    padding: 10px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    font-size: 13px;
    animation: fadeIn 0.25s ease;
}

.chat-lot-banner-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.chat-lot-thumb {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--chat-border);
}

.chat-lot-title {
    font-weight: 700;
    color: var(--chat-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-lot-code {
    font-size: 11px;
    font-family: monospace;
    color: var(--chat-primary);
    font-weight: 700;
}

/* Chat Messages Scroll Container */
.chat-messages-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: var(--chat-bg);
}

/* Message Rows & Bubbles */
.chat-message-row {
    display: flex;
    flex-direction: column;
    max-width: 72%;
    animation: popIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.chat-message-row.outgoing {
    align-self: flex-end;
    align-items: flex-end;
}

.chat-message-row.incoming {
    align-self: flex-start;
    align-items: flex-start;
}

.chat-bubble {
    padding: 12px 18px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.5;
    word-break: break-word;
    position: relative;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.chat-message-row.outgoing .chat-bubble {
    background: var(--bubble-out);
    color: var(--bubble-out-text);
    border-bottom-right-radius: 4px;
}

.chat-message-row.incoming .chat-bubble {
    background: var(--bubble-in);
    color: var(--chat-text);
    border: 1px solid var(--chat-border);
    border-bottom-left-radius: 4px;
}

.chat-meta {
    font-size: 10px;
    color: var(--chat-muted);
    margin-top: 4px;
    padding: 0 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.chat-message-row.outgoing .chat-meta {
    justify-content: flex-end;
}

/* Footer / Input Area */
.chat-footer {
    padding: 14px 20px;
    background: var(--chat-surface);
    border-top: 1px solid var(--chat-border);
}

.chat-form {
    display: flex;
    align-items: center;
    gap: 12px;
}

.chat-input {
    flex: 1;
    padding: 12px 18px;
    border-radius: 24px;
    border: 1px solid var(--chat-border);
    background: var(--chat-bg);
    color: var(--chat-text);
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
    resize: none;
    height: 46px;
    line-height: 20px;
}

.chat-input:focus {
    border-color: var(--chat-primary);
    background: var(--chat-surface);
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.15);
}

.chat-send-btn {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: var(--chat-primary);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.chat-send-btn:hover:not(:disabled) {
    background: var(--chat-primary-hover);
    transform: scale(1.05);
}

.chat-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Empty State */
.chat-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--chat-muted);
    text-align: center;
    padding: 30px;
}

.chat-empty-icon {
    font-size: 48px;
    color: var(--chat-primary);
    margin-bottom: 16px;
    opacity: 0.8;
}

/* Floating Toast Notification */
.chat-toast-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 999999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}

.chat-toast {
    background: #0f172a;
    color: #fff;
    border: 1px solid rgba(4, 120, 87, 0.4);
    border-left: 4px solid var(--chat-primary);
    padding: 14px 18px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: flex-start;
    gap: 12px;
    max-width: 360px;
    pointer-events: auto;
    cursor: pointer;
    animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    transition: all 0.2s ease;
}

.chat-toast:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 35px rgba(0, 0, 0, 0.45);
}

@keyframes toastSlideIn {
    from { transform: translateX(120%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes popIn {
    from { opacity: 0; transform: translateY(8px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .chat-container-card {
        height: calc(100vh - 90px);
        border-radius: 0;
    }
    .chat-sidebar {
        width: 100%;
        position: absolute;
        inset: 0;
        z-index: 20;
    }
    .chat-sidebar.hidden-mobile {
        display: none;
    }
    .chat-btn-back {
        display: inline-flex;
    }
}
</style>

<div class="chat-container-card" id="chatPortalContainer">
    <!-- ── Left: Conversations Sidebar ── -->
    <aside class="chat-sidebar" id="chatSidebar">
        <div class="chat-sidebar-header">
            <h2 class="chat-sidebar-title">
                <i class="fa-solid fa-comments text-emerald-600"></i> Messages
            </h2>
            <span id="sidebarTotalUnread" class="chat-unread-badge" style="display:none;">0</span>
        </div>

        <div class="chat-search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="convoSearchInput" placeholder="Search conversations or users..." oninput="filterConversations()">
        </div>

        <!-- Tab Bar: Chats vs All Users -->
        <div class="chat-tab-bar">
            <button type="button" class="chat-tab-btn active" id="tabConvos" onclick="switchSidebarTab('convos')">
                <i class="fa-solid fa-message"></i> Chats <span id="convoCountBadge" class="chat-tab-count">0</span>
            </button>
            <button type="button" class="chat-tab-btn" id="tabUsers" onclick="switchSidebarTab('users')">
                <i class="fa-solid fa-users"></i> All Users <span id="userCountBadge" class="chat-tab-count">0</span>
            </button>
        </div>

        <div class="chat-conversations-list" id="convoList">
            <!-- Rendered dynamically via JavaScript -->
            <div style="text-align:center;padding:30px;color:var(--chat-muted);font-size:13px;">
                <i class="fa-solid fa-circle-notch fa-spin"></i> Loading conversations...
            </div>
        </div>
    </aside>

    <!-- ── Right: Active Chat Area ── -->
    <section class="chat-main" id="chatMain">
        <!-- Top Chat Header -->
        <header class="chat-header" id="chatHeader" style="display:none;">
            <div class="chat-header-user">
                <button type="button" class="chat-btn-back" onclick="showConversationsListMobile()" aria-label="Back to conversations">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <img src="" alt="" class="chat-header-avatar" id="activeUserAvatar">
                <div>
                    <h3 class="chat-header-name" id="activeUserName">Selected User</h3>
                    <div class="chat-header-status" id="activeUserStatus">Active on Geo Auction</div>
                </div>
            </div>

            <div>
                <a href="#" id="viewProfileBtn" class="nav-icon-btn" title="View Profile" target="_blank">
                    <i class="fa-regular fa-user"></i>
                </a>
            </div>
        </header>

        <!-- Regarding Lot Card (Optional) -->
        <div class="chat-lot-banner" id="chatLotBanner" style="display:none;">
            <div class="chat-lot-banner-left">
                <img src="" alt="" class="chat-lot-thumb" id="lotBannerImg">
                <div>
                    <span class="chat-lot-code" id="lotBannerCode">#LOT</span>
                    <div class="chat-lot-title" id="lotBannerTitle">Lot Title</div>
                </div>
            </div>
            <a href="#" id="lotBannerLink" class="btn btn-outline btn-sm" style="border-color:var(--chat-primary);color:var(--chat-primary);font-size:11px;font-weight:700;padding:4px 10px;" target="_blank">
                <i class="fa-solid fa-up-right-from-square"></i> View Lot
            </a>
        </div>

        <!-- Scrollable Messages Body -->
        <div class="chat-messages-body" id="chatMessagesBody">
            <div class="chat-empty-state" id="chatEmptyPlaceholder">
                <div class="chat-empty-icon">
                    <i class="fa-regular fa-comments"></i>
                </div>
                <h4 style="font-size:18px;font-weight:700;margin-bottom:6px;color:var(--chat-text);">Your Messages</h4>
                <p style="font-size:13px;max-width:320px;line-height:1.5;">Select an existing conversation from the list or message a seller directly from an auction lot.</p>
            </div>
        </div>

        <!-- Bottom Form Input -->
        <footer class="chat-footer" id="chatFooter" style="display:none;">
            <form class="chat-form" id="chatMessageForm" onsubmit="handleSendSubmit(event)">
                <input type="text" 
                       id="chatMessageInput" 
                       class="chat-input" 
                       placeholder="Type your message here..." 
                       autocomplete="off" 
                       maxlength="5000">
                <button type="submit" id="chatSendBtn" class="chat-send-btn" title="Send Message">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </footer>
    </section>
</div>

<!-- Floating Toast Container -->
<div class="chat-toast-container" id="chatToastContainer"></div>

<!-- Client-side Logic (Vanilla JS, Fetch API, Web Audio API) -->
<script>
// State
const CURRENT_USER_ID = <?php echo (int)$currentUserId; ?>;
let activePartnerId   = <?php echo (int)$activePartnerId; ?>;
let activeItemId      = <?php echo (int)$targetItemId; ?>;
let conversationsData = <?php echo json_encode($conversations ?? [], JSON_HEX_TAG | JSON_HEX_AMP); ?> || [];
let allUsersData      = <?php echo json_encode($allUsers ?? [], JSON_HEX_TAG | JSON_HEX_AMP); ?> || [];
let currentSidebarTab = 'convos';
let lastMessageIdSeen = 0;
let isFirstLoad       = true;
let isSending         = false;

// --------------------------------------------------------------------------
// 1. Web Audio API Synthesized Chime (No external asset dependency)
// --------------------------------------------------------------------------
function playNotificationChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        // Dual pleasant chime (587Hz D5 -> 880Hz A5)
        const osc1 = ctx.createOscillator();
        const osc2 = ctx.createOscillator();
        const gain = ctx.createGain();

        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, ctx.currentTime);
        osc1.frequency.exponentialRampToValueAtTime(880.00, ctx.currentTime + 0.12);

        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880.00, ctx.currentTime + 0.12);

        gain.gain.setValueAtTime(0.01, ctx.currentTime);
        gain.gain.linearRampToValueAtTime(0.2, ctx.currentTime + 0.04);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);

        osc1.connect(gain);
        osc2.connect(gain);
        gain.connect(ctx.destination);

        osc1.start(ctx.currentTime);
        osc2.start(ctx.currentTime + 0.1);
        osc1.stop(ctx.currentTime + 0.4);
        osc2.stop(ctx.currentTime + 0.4);
    } catch (e) {
        // Autoplay restrictions or unsupported audio
    }
}

// --------------------------------------------------------------------------
// 2. Toast Notifications
// --------------------------------------------------------------------------
function showMessageToast(senderName, messageText, partnerId) {
    const container = document.getElementById('chatToastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'chat-toast';
    toast.innerHTML = `
        <i class="fa-solid fa-message" style="color:var(--chat-primary);font-size:18px;margin-top:2px;"></i>
        <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:13px;color:#fff;">${escapeHtml(senderName)}</div>
            <div style="font-size:12px;color:#cbd5e1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;">
                ${escapeHtml(messageText)}
            </div>
        </div>
        <i class="fa-solid fa-times" style="color:#64748b;font-size:12px;cursor:pointer;" onclick="this.parentElement.remove();event.stopPropagation();"></i>
    `;

    toast.onclick = function() {
        selectConversation(partnerId);
        toast.remove();
    };

    container.appendChild(toast);

    setTimeout(() => {
        if (toast.parentElement) toast.remove();
    }, 5500);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// --------------------------------------------------------------------------
// 3. Load & Render Conversations
// --------------------------------------------------------------------------
async function loadConversations() {
    try {
        const res = await fetch('get_conversations.php');
        const data = await res.json();
        if (data.success) {
            conversationsData = data.conversations || [];
            if (data.users) {
                allUsersData = data.users;
            }
            updateSidebarCounts();
            renderConversationsList();
            updateSidebarTotalUnread();
        }
    } catch (e) {
        console.error('Error fetching conversations:', e);
    }
}

function updateSidebarCounts() {
    const convoBadge = document.getElementById('convoCountBadge');
    const userBadge  = document.getElementById('userCountBadge');
    if (convoBadge) convoBadge.textContent = conversationsData.length;
    if (userBadge)  userBadge.textContent  = allUsersData.length;
}

function switchSidebarTab(tabName) {
    currentSidebarTab = tabName;
    document.getElementById('tabConvos')?.classList.toggle('active', tabName === 'convos');
    document.getElementById('tabUsers')?.classList.toggle('active', tabName === 'users');
    renderConversationsList();
}

function renderUserCardHtml(u, isActive = false) {
    const roleLower = (u.role || '').toLowerCase();
    const roleClass = (roleLower === 'admin') ? 'role-admin' : ((roleLower === 'seller') ? 'role-seller' : 'role-buyer');

    return `
        <div class="chat-user-item ${isActive ? 'active' : ''}" onclick="selectConversation(${u.id})">
            <img src="${u.avatar}" alt="${escapeHtml(u.name)}" class="chat-convo-avatar" onerror="this.src='assets/image/other.png';">
            <div class="chat-convo-info">
                <div class="chat-convo-top">
                    <span class="chat-convo-name">${escapeHtml(u.name)}</span>
                    <span class="chat-role-badge ${roleClass}">${escapeHtml(u.role || 'User')}</span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <span class="chat-convo-preview" style="color:var(--chat-muted);font-size:11px;">
                        ${u.email ? escapeHtml(u.email) : 'Click to chat'}
                    </span>
                    <button type="button" class="chat-msg-btn-pill" onclick="selectConversation(${u.id});event.stopPropagation();">
                        <i class="fa-solid fa-comment-dots"></i> Chat
                    </button>
                </div>
            </div>
        </div>
    `;
}

function renderConvoCardHtml(c, isActive = false) {
    const unreadBadge = c.unread_count > 0 ? `<span class="chat-unread-badge">${c.unread_count}</span>` : '';
    const outgoingPrefix = c.is_outgoing ? '<span style="color:var(--chat-muted);margin-right:2px;">You: </span>' : '';

    return `
        <div class="chat-convo-item ${isActive ? 'active' : ''}" onclick="selectConversation(${c.partner_id})">
            <img src="${c.avatar}" alt="${escapeHtml(c.name)}" class="chat-convo-avatar" onerror="this.src='assets/image/other.png';">
            <div class="chat-convo-info">
                <div class="chat-convo-top">
                    <span class="chat-convo-name">${escapeHtml(c.name)}</span>
                    <span class="chat-convo-time">${escapeHtml(c.last_time || '')}</span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <p class="chat-convo-preview">${outgoingPrefix}${escapeHtml(c.last_message || '')}</p>
                    ${unreadBadge}
                </div>
            </div>
        </div>
    `;
}

function renderConversationsList() {
    const listEl = document.getElementById('convoList');
    if (!listEl) return;

    const query = (document.getElementById('convoSearchInput')?.value || '').toLowerCase().trim();
    let html = '';

    // Search Mode: Query is present
    if (query !== '') {
        const matchedConvos = conversationsData.filter(c => 
            (c.name && c.name.toLowerCase().includes(query)) || 
            (c.username && c.username.toLowerCase().includes(query)) ||
            (c.last_message && c.last_message.toLowerCase().includes(query))
        );

        const matchedUsers = allUsersData.filter(u => 
            (u.name && u.name.toLowerCase().includes(query)) || 
            (u.username && u.username.toLowerCase().includes(query)) ||
            (u.email && u.email.toLowerCase().includes(query)) ||
            (u.role && u.role.toLowerCase().includes(query))
        );

        if (matchedConvos.length === 0 && matchedUsers.length === 0) {
            listEl.innerHTML = `
                <div style="text-align:center;padding:40px 16px;color:var(--chat-muted);font-size:13px;">
                    <i class="fa-solid fa-magnifying-glass" style="font-size:24px;margin-bottom:8px;display:block;opacity:0.6;"></i>
                    No conversations or users found for "<strong>${escapeHtml(query)}</strong>"
                </div>`;
            return;
        }

        if (matchedConvos.length > 0) {
            html += `<div class="chat-section-header"><i class="fa-solid fa-comments"></i> Conversations (${matchedConvos.length})</div>`;
            matchedConvos.forEach(c => {
                html += renderConvoCardHtml(c, c.partner_id === activePartnerId);
            });
        }

        if (matchedUsers.length > 0) {
            html += `<div class="chat-section-header" style="${matchedConvos.length > 0 ? 'margin-top:12px;' : ''}"><i class="fa-solid fa-users"></i> All Users (${matchedUsers.length})</div>`;
            matchedUsers.forEach(u => {
                html += renderUserCardHtml(u, u.id === activePartnerId);
            });
        }

        listEl.innerHTML = html;
        return;
    }

    // Default Mode: Tab view
    if (currentSidebarTab === 'convos') {
        if (conversationsData.length === 0) {
            html += `
                <div class="chat-empty-intro">
                    <div class="chat-empty-intro-icon"><i class="fa-regular fa-comments"></i></div>
                    <div class="chat-empty-intro-title">No conversations yet</div>
                    <div class="chat-empty-intro-sub">Select any user below to start a conversation:</div>
                </div>
            `;
            if (allUsersData.length > 0) {
                html += `<div class="chat-section-header"><i class="fa-solid fa-users"></i> Available Users (${allUsersData.length})</div>`;
                allUsersData.forEach(u => {
                    html += renderUserCardHtml(u, u.id === activePartnerId);
                });
            }
        } else {
            conversationsData.forEach(c => {
                html += renderConvoCardHtml(c, c.partner_id === activePartnerId);
            });
        }
    } else {
        // 'users' tab
        if (allUsersData.length === 0) {
            html = `
                <div style="text-align:center;padding:40px 16px;color:var(--chat-muted);font-size:13px;">
                    <i class="fa-regular fa-user" style="font-size:24px;margin-bottom:8px;display:block;"></i>
                    No other users found.
                </div>`;
        } else {
            html += `<div class="chat-section-header"><i class="fa-solid fa-users"></i> All Registered Users (${allUsersData.length})</div>`;
            allUsersData.forEach(u => {
                html += renderUserCardHtml(u, u.id === activePartnerId);
            });
        }
    }

    listEl.innerHTML = html;
}

function updateSidebarTotalUnread() {
    const total = conversationsData.reduce((acc, c) => acc + (c.unread_count || 0), 0);
    const badge = document.getElementById('sidebarTotalUnread');
    if (badge) {
        if (total > 0) {
            badge.textContent = total;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}

function filterConversations() {
    renderConversationsList();
}

// --------------------------------------------------------------------------
// 4. Select & Load Active Conversation
// --------------------------------------------------------------------------
async function selectConversation(partnerId, itemId = null) {
    if (!partnerId || partnerId <= 0) return;
    activePartnerId = partnerId;
    if (itemId !== null) activeItemId = itemId;

    // Mobile layout adjustment
    const sidebar = document.getElementById('chatSidebar');
    if (sidebar && window.innerWidth <= 768) {
        sidebar.classList.add('hidden-mobile');
    }

    renderConversationsList();
    await fetchActiveChatHistory(true);
    document.getElementById('chatMessageInput')?.focus();
}

function showConversationsListMobile() {
    const sidebar = document.getElementById('chatSidebar');
    if (sidebar) {
        sidebar.classList.remove('hidden-mobile');
    }
}

async function fetchActiveChatHistory(scrollBottom = false) {
    if (!activePartnerId || activePartnerId <= 0) return;

    try {
        const itemParam = activeItemId > 0 ? `&item_id=${activeItemId}` : '';
        const res = await fetch(`fetch_messages.php?user_id=${activePartnerId}${itemParam}&mark_read=1`);
        const data = await res.json();

        if (data.success) {
            // Update Header Info
            const header = document.getElementById('chatHeader');
            const footer = document.getElementById('chatFooter');
            const placeholder = document.getElementById('chatEmptyPlaceholder');

            if (header) header.style.display = 'flex';
            if (footer) footer.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';

            const user = data.other_user;
            document.getElementById('activeUserName').textContent = user.name;
            document.getElementById('activeUserAvatar').src = user.avatar;
            document.getElementById('activeUserStatus').textContent = (user.role ? (user.role.toUpperCase() + ' • ') : '') + (user.status || 'Active');
            document.getElementById('viewProfileBtn').href = `user/profile.php?user_id=${user.id}`;

            // Regarding Lot Banner
            const banner = document.getElementById('chatLotBanner');
            if (data.related_item) {
                const item = data.related_item;
                document.getElementById('lotBannerCode').textContent = '#' + item.auction_code;
                document.getElementById('lotBannerTitle').textContent = item.title;
                document.getElementById('lotBannerImg').src = item.image || 'assets/image/Geo-auction.png';
                document.getElementById('lotBannerLink').href = `live_auction.php?id=${item.id}`;
                if (banner) banner.style.display = 'flex';
            } else {
                if (banner) banner.style.display = 'none';
            }

            renderMessages(data.messages, scrollBottom);

            // Update badge counts in conversation list
            const currentConvo = conversationsData.find(c => c.partner_id === activePartnerId);
            if (currentConvo) currentConvo.unread_count = 0;
            updateSidebarTotalUnread();
        }
    } catch (e) {
        console.error('Error fetching chat history:', e);
    }
}

function renderMessages(messages, forceScroll = false) {
    const bodyEl = document.getElementById('chatMessagesBody');
    if (!bodyEl) return;

    if (!messages || messages.length === 0) {
        bodyEl.innerHTML = `
            <div style="text-align:center;padding:40px;color:var(--chat-muted);font-size:13px;">
                <i class="fa-regular fa-comment-dots" style="font-size:32px;color:var(--chat-primary);margin-bottom:8px;display:block;"></i>
                Start your conversation with ${escapeHtml(document.getElementById('activeUserName')?.textContent || 'this user')}.
            </div>`;
        return;
    }

    let html = '';
    let maxId = 0;

    messages.forEach(m => {
        if (m.id > maxId) maxId = m.id;
        const isOut = m.is_outgoing;
        const rowClass = isOut ? 'outgoing' : 'incoming';
        const checkmark = isOut ? (m.is_read ? '<i class="fa-solid fa-check-double" style="color:#34d399;"></i>' : '<i class="fa-solid fa-check"></i>') : '';

        html += `
            <div class="chat-message-row ${rowClass}" data-msg-id="${m.id}">
                <div class="chat-bubble">
                    ${m.subject ? `<div style="font-size:11px;font-weight:700;margin-bottom:4px;opacity:0.85;">${m.subject}</div>` : ''}
                    <div>${m.message_body}</div>
                </div>
                <div class="chat-meta">
                    <span>${m.time_formatted}</span>
                    ${checkmark}
                </div>
            </div>`;
    });

    // Check if new incoming message arrived while viewing
    if (!isFirstLoad && maxId > lastMessageIdSeen) {
        const lastMsg = messages[messages.length - 1];
        if (lastMsg && !lastMsg.is_outgoing) {
            playNotificationChime();
        }
    }

    lastMessageIdSeen = maxId;
    isFirstLoad = false;

    bodyEl.innerHTML = html;

    if (forceScroll) {
        scrollToBottom();
    }
}

function scrollToBottom() {
    const bodyEl = document.getElementById('chatMessagesBody');
    if (bodyEl) {
        bodyEl.scrollTop = bodyEl.scrollHeight;
    }
}

// --------------------------------------------------------------------------
// 5. Send Message Submission
// --------------------------------------------------------------------------
async function handleSendSubmit(event) {
    event.preventDefault();
    if (isSending || !activePartnerId) return;

    const input = document.getElementById('chatMessageInput');
    const sendBtn = document.getElementById('chatSendBtn');
    const text = (input?.value || '').trim();

    if (!text) return;

    isSending = true;
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';

    const payload = {
        receiver_id: activePartnerId,
        message_body: text
    };
    if (activeItemId > 0) {
        payload.item_id = activeItemId;
    }

    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        const res = await fetch('send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (data.success) {
            input.value = '';
            // Immediately append outgoing message and scroll
            await fetchActiveChatHistory(true);
            await loadConversations();
        } else {
            alert(data.message || 'Unable to send message.');
        }
    } catch (e) {
        alert('Network connection error while sending message.');
    } finally {
        isSending = false;
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
        input.focus();
    }
}

// --------------------------------------------------------------------------
// 6. Polling (every 5 seconds)
// --------------------------------------------------------------------------
setInterval(async () => {
    // Check for updates
    if (activePartnerId > 0) {
        await fetchActiveChatHistory(false);
    }
    await loadConversations();
}, 5000);

// Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', async () => {
    updateSidebarCounts();
    renderConversationsList();
    await loadConversations();
    if (activePartnerId > 0) {
        selectConversation(activePartnerId, activeItemId);
    }
});
</script>

<?php
include_once __DIR__ . '/includes/footer.php';
?>
