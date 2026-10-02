<?php
/**
 * ============================================================================
 * ITEM COMMENTS / DISCUSSION COMPONENT
 * File: includes/components/item_comments.php
 * Geo Auction System
 * ============================================================================
 * 
 * Visually nested directly inside the main post / active lot detail view container.
 * Features:
 * - Interactive expand / collapse header toggle
 * - Integrated comment composer with active user avatar & character counter (500 max)
 * - Expandable / scrollable live discussion stream
 * - Seller & Admin role badges, verified tags, and relative timestamps
 * - Smooth AJAX submission & real-time polling updates
 */

if (!isset($current_item) || empty($current_item)) {
    return;
}

$lot_id = (int)$current_item['id'];
$lot_seller_id = (int)($current_item['seller_id'] ?? 0);
$comments_count = isset($current_item_comments) ? count($current_item_comments) : 0;
?>

<!-- NESTED COMMENTS SECTION COMPONENT -->
<div id="nestedLotCommentsComponent" class="bg-slate-50/80 border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-2xs space-y-4 transition-all duration-300">
    
    <!-- Component Header (Integrated within Detail View) -->
    <div class="flex items-center justify-between border-b border-slate-200/80 pb-3 gap-2">
        <div class="flex items-center gap-2.5 min-w-0">
            <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm shrink-0 shadow-2xs border border-emerald-100">
                <i class="fa-regular fa-comments"></i>
            </span>
            <div class="min-w-0">
                <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-800 flex items-center gap-2 leading-none">
                    <span>Lot Discussion & Inquiries</span>
                </h3>
                <p class="text-[11px] text-slate-400 mt-1 truncate">
                    Ask questions or discuss lot specifications with the seller & bidders
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <!-- Live Comment Counter Pill -->
            <span class="inline-flex items-center gap-1.5 text-[11px] text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full font-bold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span id="activeLotCommentCount"><?php echo $comments_count; ?></span> Comments
            </span>

            <!-- Expand / Collapse Section Toggle Button -->
            <button type="button" 
                    id="toggleCommentsCollapseBtn" 
                    onclick="commentStreamState.toggleCollapse()"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer shadow-2xs" 
                    title="Expand or collapse the comments section"
                    aria-expanded="true"
                    aria-controls="nestedCommentsBody">
                <span id="toggleCommentsBtnText" class="hidden sm:inline">Collapse</span>
                <i id="toggleCommentsBtnIcon" class="fa-solid fa-chevron-up text-[10px] transition-transform duration-200"></i>
            </button>
        </div>
    </div>

    <!-- Collapsible / Expandable Component Body -->
    <div id="nestedCommentsBody" class="space-y-4 transition-all duration-300">

        <!-- 1. Integrated Comment Composer / Add Comment Form -->
        <div class="bg-white border border-slate-200/90 rounded-xl p-3.5 shadow-2xs">
            <?php if ($is_authenticated): ?>
                <form id="activeLotCommentForm" class="space-y-2.5">
                    <input type="hidden" name="action" value="post_comment">
                    <input type="hidden" name="item_id" value="<?php echo $lot_id; ?>">
                    
                    <div class="flex items-start gap-2.5">
                        <!-- Active User Avatar / Initials -->
                        <div class="shrink-0 pt-0.5">
                            <?php if (!empty($current_user_avatar)): ?>
                                <img src="<?php echo htmlspecialchars($current_user_avatar); ?>" class="w-8 h-8 rounded-full object-cover border border-slate-200 shadow-2xs" alt="<?php echo htmlspecialchars($current_user_name); ?>">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shadow-2xs">
                                    <?php echo htmlspecialchars($current_user_initial); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Textarea Input Field -->
                        <div class="flex-1 min-w-0">
                            <textarea id="activeLotCommentTextarea" 
                                      name="comment" 
                                      rows="2" 
                                      maxlength="500" 
                                      placeholder="Ask the seller a question or share a thought on this lot..." 
                                      required
                                      class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-100 transition resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Composer Action Toolbar -->
                    <div class="flex items-center justify-between pl-10.5 pt-1">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] text-slate-400 font-medium">
                                <span id="activeLotCharCounter" class="font-bold text-slate-600">0</span> / 500 characters
                            </span>
                            <span class="hidden md:inline text-[10px] text-slate-400">
                                &bull; <kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded text-[9px] text-slate-500 font-mono">Ctrl</kbd> + <kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded text-[9px] text-slate-500 font-mono">Enter</kbd> to post
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" 
                                    id="activeLotCommentClearBtn" 
                                    onclick="commentStreamState.clearInput()" 
                                    class="hidden px-2.5 py-1.5 text-xs text-slate-400 hover:text-slate-600 font-medium transition cursor-pointer">
                                Clear
                            </button>
                            <button type="submit" 
                                    id="activeLotCommentSubmitBtn"
                                    class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs rounded-xl shadow-xs transition active:scale-95 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-paper-plane text-[11px]"></i>
                                <span>Post Comment</span>
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <!-- Unauthenticated Prompt -->
                <div class="flex items-center justify-between gap-3 text-xs text-slate-600 p-1">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </span>
                        <div>
                            <p class="font-bold text-slate-800">Have a question about this property lot?</p>
                            <p class="text-[11px] text-slate-400">Sign in to participate in the live community discussion and message the seller.</p>
                        </div>
                    </div>
                    <a href="login.php" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition shrink-0">
                        Sign In
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Expandable / Scrollable Comments Stream -->
        <div class="space-y-2.5 max-h-72 sm:max-h-80 overflow-y-auto pr-1.5 scrollbar-thin transition-all duration-300" id="itemCommentsStream">
            <?php if (!empty($current_item_comments)): ?>
                <?php foreach ($current_item_comments as $comm): 
                    $c_user = !empty($comm['user_name']) ? $comm['user_name'] : (!empty($comm['username']) ? $comm['username'] : 'Bidder #' . $comm['user_id']);
                    $c_initial = strtoupper(substr($c_user, 0, 1));
                    $c_avatar = !empty($comm['avatar']) ? cleanImagePath($comm['avatar']) : '';
                    $is_c_seller = ($lot_seller_id > 0 && (int)$comm['user_id'] === $lot_seller_id);
                    $c_role = strtolower(trim($comm['user_role'] ?? 'buyer'));
                    $c_time = formatCommentTime($comm['created_at']);
                ?>
                    <div class="p-3 rounded-xl <?php echo $is_c_seller ? 'bg-emerald-50/70 border border-emerald-200/80' : 'bg-white border border-slate-200/80'; ?> text-xs space-y-1.5 transition hover:border-slate-300 shadow-2xs" data-comment-id="<?php echo $comm['id']; ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <?php if (!empty($c_avatar)): ?>
                                    <img src="<?php echo htmlspecialchars($c_avatar); ?>" class="w-7 h-7 rounded-full object-cover border border-slate-200 shadow-2xs" alt="Avatar">
                                <?php else: ?>
                                    <div class="w-7 h-7 rounded-full <?php echo $is_c_seller ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700'; ?> flex items-center justify-center font-bold text-xs shadow-2xs">
                                        <?php echo $c_initial; ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="font-bold text-slate-900"><?php echo htmlspecialchars($c_user); ?></span>
                                    <?php if ($is_c_seller): ?>
                                        <span class="ml-1 text-[9px] bg-emerald-600 text-white font-extrabold px-1.5 py-0.5 rounded uppercase tracking-wider">Seller</span>
                                    <?php elseif ($c_role === 'admin'): ?>
                                        <span class="ml-1 text-[9px] bg-purple-600 text-white font-extrabold px-1.5 py-0.5 rounded uppercase tracking-wider">Admin</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="text-[10px] text-slate-400 font-medium"><?php echo $c_time; ?></span>
                        </div>
                        <p class="text-slate-700 text-xs leading-relaxed whitespace-pre-wrap break-words pl-9">
                            <?php echo nl2br(htmlspecialchars($comm['comment'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center py-7 text-slate-400 bg-white rounded-xl border border-dashed border-slate-200" id="noCommentsPrompt">
                    <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-2 text-slate-300">
                        <i class="fa-regular fa-comment-dots text-xl"></i>
                    </div>
                    <p class="text-xs font-semibold text-slate-700">No questions or comments yet.</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Be the first to ask the seller a question or start the discussion!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Stream Expand / Show All Footer (when comments > 3) -->
        <?php if ($comments_count > 3): ?>
            <div id="expandStreamControlBar" class="pt-1">
                <button type="button" 
                        id="expandCommentsStreamBtn" 
                        onclick="commentStreamState.toggleExpandStream()" 
                        class="w-full py-1.5 text-center text-xs font-semibold text-emerald-700 hover:text-emerald-800 bg-emerald-50/70 hover:bg-emerald-100/70 rounded-xl transition border border-emerald-200/60 flex items-center justify-center gap-1.5 cursor-pointer">
                    <span id="expandCommentsStreamText">Show expanded scroll view (<?php echo $comments_count; ?> comments)</span>
                    <i id="expandCommentsStreamIcon" class="fa-solid fa-angles-down text-[10px]"></i>
                </button>
            </div>
        <?php endif; ?>

    </div>
</div>
