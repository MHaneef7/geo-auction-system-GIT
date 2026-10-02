<?php
/**
 * User Reviews & Messages Component for Profile Page
 * File: includes/components/profile_reviews_messages.php
 * 
 * Tech Stack: PHP 8+, PDO MySQL, HTML5, Modern CSS (rem, Flexbox)
 * 
 * Displays recent reviews/messages sent to a profile user with:
 * - Circular overlapping sender avatar with white border and soft shadow
 * - Warm pastel yellow (#ffe271) pill-shaped message bubble with italic text
 * - Bottom-left attached crisp white author label with accent blue (#83aebd) sender name
 * - Mobile-responsive design and pop/slide entrance animations
 */

declare(strict_types=1);

require_once __DIR__ . '/../Database.php';

/**
 * Fetch and render recent reviews/messages for a specified user profile
 *
 * @param int      $profileUserId The receiver user ID whose profile is being viewed
 * @param PDO|null $pdo           Optional existing PDO connection instance
 * @param int      $limit         Maximum number of messages to display (default: 5)
 */
function render_profile_reviews_messages(int $profileUserId, ?PDO $pdo = null, int $limit = 5): void
{
    if ($profileUserId <= 0) {
        return;
    }

    $db = $pdo ?? Database::getPdo();
    $limit = max(1, min(20, $limit));

    // 1. Database & Query: Fetch latest messages where receiver_id = :profile_user_id
    $sql = "SELECT 
                m.id,
                m.sender_id,
                m.receiver_id,
                m.message,
                m.subject,
                m.created_at,
                u.name AS sender_name,
                u.username AS sender_username,
                u.avatar AS sender_avatar
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = :profile_user_id
            ORDER BY m.created_at DESC
            LIMIT :limit";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':profile_user_id', $profileUserId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- Modern Testimonial / Chat Bubble Styles -->
    <style>
        .geo-reviews-widget {
            width: 100%;
            margin: 1.5rem 0;
            box-sizing: border-box;
            font-family: inherit;
        }

        .geo-reviews-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #edf2f7;
        }

        .geo-reviews-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1a202c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            letter-spacing: -0.02rem;
        }

        .geo-reviews-title i {
            color: #83aebd;
            font-size: 1.15rem;
        }

        .geo-reviews-count-pill {
            font-size: 0.75rem;
            font-weight: 700;
            color: #4a5568;
            background: #edf2f7;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.03rem;
        }

        .geo-reviews-stream {
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }

        /* -------------------------------------------------------------
           Card & Animation Structure
           ------------------------------------------------------------- */
        .geo-review-card {
            display: flex;
            align-items: flex-start;
            position: relative;
            padding-left: 1.25rem;
            box-sizing: border-box;
            opacity: 0;
            animation: geoPopSlideFromBottom 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            animation-delay: calc(var(--anim-index, 0) * 0.12s);
        }

        @keyframes geoPopSlideFromBottom {
            0% {
                opacity: 0;
                transform: translateY(1.5rem) scale(0.96);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* -------------------------------------------------------------
           Circular Overlapping Avatar
           ------------------------------------------------------------- */
        .geo-review-avatar-container {
            position: absolute;
            top: -0.625rem;
            left: 0;
            z-index: 3;
            flex-shrink: 0;
        }

        .geo-review-avatar {
            width: 3.25rem;
            height: 3.25rem;
            border-radius: 50%;
            border: 0.25rem solid #ffffff;
            box-shadow: 0 0.35rem 0.85rem rgba(0, 0, 0, 0.12);
            object-fit: cover;
            background: #f7fafc;
            display: block;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .geo-review-card:hover .geo-review-avatar {
            transform: scale(1.06);
            box-shadow: 0 0.5rem 1.2rem rgba(0, 0, 0, 0.18);
        }

        /* -------------------------------------------------------------
           Main Bubble Group
           ------------------------------------------------------------- */
        .geo-review-bubble-group {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
            padding-left: 2rem;
            box-sizing: border-box;
        }

        /* Message Bubble: Warm Pastel Yellow (#ffe271), Sharp Top-Left, Rounded Right */
        .geo-review-bubble {
            background-color: #ffe271;
            color: #2d3748;
            padding: 1.125rem 1.75rem 1.125rem 1.5rem;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 2.25rem;
            border-bottom-right-radius: 2.25rem;
            border-bottom-left-radius: 0.35rem;
            box-shadow: 0 0.35rem 1rem rgba(255, 226, 113, 0.45), 0 0.1rem 0.25rem rgba(0, 0, 0, 0.04);
            position: relative;
            max-width: 100%;
            box-sizing: border-box;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .geo-review-card:hover .geo-review-bubble {
            transform: translateY(-0.1rem);
            box-shadow: 0 0.5rem 1.35rem rgba(255, 226, 113, 0.65), 0 0.15rem 0.35rem rgba(0, 0, 0, 0.06);
        }

        .geo-review-subject {
            font-size: 0.8125rem;
            font-weight: 700;
            color: #744210;
            text-transform: uppercase;
            letter-spacing: 0.04rem;
            margin-bottom: 0.375rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .geo-review-text {
            font-family: inherit;
            font-style: italic;
            font-size: 0.95rem;
            line-height: 1.55;
            color: #2d3748;
            margin: 0;
            word-break: break-word;
        }

        /* -------------------------------------------------------------
           Author Label: Attached directly beneath bubble on bottom-left
           ------------------------------------------------------------- */
        .geo-review-author-label {
            background: #ffffff;
            padding: 0.4rem 0.85rem;
            border-radius: 0 0 0.75rem 0.75rem;
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            box-shadow: 0 0.25rem 0.6rem rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-top: none;
            margin-left: 0.5rem;
            box-sizing: border-box;
        }

        .geo-review-author-name {
            font-size: 0.825rem;
            font-weight: 700;
            color: #83aebd; /* Accent Blue */
            display: flex;
            align-items: center;
            gap: 0.375rem;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .geo-review-author-name:hover {
            color: #5d8ea1;
        }

        .geo-review-separator {
            color: #cbd5e0;
            font-size: 0.65rem;
            user-select: none;
        }

        .geo-review-time {
            font-size: 0.75rem;
            color: #718096;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
        }

        /* -------------------------------------------------------------
           Empty State
           ------------------------------------------------------------- */
        .geo-reviews-empty {
            text-align: center;
            padding: 2.5rem 1.5rem;
            background: #f8fafc;
            border-radius: 1rem;
            border: 2px dashed #e2e8f0;
            color: #718096;
        }

        .geo-reviews-empty-icon {
            font-size: 2.25rem;
            color: #83aebd;
            margin-bottom: 0.75rem;
            opacity: 0.8;
        }

        .geo-reviews-empty p {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* -------------------------------------------------------------
           Responsive Rules (Pure CSS, rem and Flexbox)
           ------------------------------------------------------------- */
        @media (max-width: 640px) {
            .geo-review-card {
                padding-left: 0.875rem;
            }

            .geo-review-avatar {
                width: 2.75rem;
                height: 2.75rem;
                border-width: 0.2rem;
            }

            .geo-review-bubble-group {
                padding-left: 1.5rem;
            }

            .geo-review-bubble {
                padding: 0.875rem 1.25rem 0.875rem 1.125rem;
                border-top-right-radius: 1.75rem;
                border-bottom-right-radius: 1.75rem;
            }

            .geo-review-text {
                font-size: 0.875rem;
            }

            .geo-review-author-label {
                padding: 0.35rem 0.65rem;
                font-size: 0.75rem;
                margin-left: 0.25rem;
            }
        }
    </style>

    <div class="geo-reviews-widget">
        <div class="geo-reviews-header">
            <h3 class="geo-reviews-title">
                <i class="fa-solid fa-comments"></i> User Reviews & Messages
            </h3>
            <span class="geo-reviews-count-pill">
                <?= count($messages); ?> <?= count($messages) === 1 ? 'Message' : 'Messages'; ?>
            </span>
        </div>

        <?php if (!empty($messages)): ?>
            <div class="geo-reviews-stream">
                <?php foreach ($messages as $index => $msg): 
                    // Sanitize user inputs to prevent XSS
                    $senderName  = htmlspecialchars((string)($msg['sender_name'] ?? 'Anonymous'), ENT_QUOTES, 'UTF-8');
                    $rawBody     = !empty($msg['message_body']) ? $msg['message_body'] : ($msg['message'] ?? '');
                    $bodyEscaped = nl2br(htmlspecialchars((string)$rawBody, ENT_QUOTES, 'UTF-8'));
                    $subject     = !empty($msg['subject']) ? htmlspecialchars((string)$msg['subject'], ENT_QUOTES, 'UTF-8') : '';
                    
                    // Format timestamp
                    $timestamp = strtotime($msg['created_at']);
                    $timeFormatted = date('M d, Y • h:i A', $timestamp);

                    // Avatar URL resolver with safe fallback
                    $avatar = trim((string)($msg['sender_avatar'] ?? ''));
                    if (!empty($avatar)) {
                        if (preg_match('/^(https?:\/\/|\/\/|data:)/i', $avatar)) {
                            $avatarSrc = htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8');
                        } else {
                            $cleanAvatar = ltrim($avatar, '/\\');
                            $avatarSrc = (file_exists(__DIR__ . '/../../' . $cleanAvatar)) 
                                ? '../' . htmlspecialchars($cleanAvatar, ENT_QUOTES, 'UTF-8')
                                : htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8');
                        }
                    } else {
                        $avatarSrc = 'https://ui-avatars.com/api/?name=' . urlencode($msg['sender_name'] ?? 'User') . '&background=83aebd&color=ffffff&size=128&bold=true';
                    }
                ?>
                    <article class="geo-review-card" style="--anim-index: <?= (int)$index; ?>;">
                        <!-- Circular Sender Avatar Overlapping Top-Left Edge -->
                        <div class="geo-review-avatar-container">
                            <img src="<?= $avatarSrc; ?>" 
                                 alt="<?= $senderName; ?>" 
                                 class="geo-review-avatar"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($msg['sender_name'] ?? 'User'); ?>&background=83aebd&color=ffffff&size=128&bold=true';">
                        </div>

                        <!-- Message Content Group -->
                        <div class="geo-review-bubble-group">
                            <!-- Message Bubble: Warm Pastel Yellow (#ffe271), Sharp Top-Left, Pill Right, Italic -->
                            <div class="geo-review-bubble">
                                <?php if (!empty($subject)): ?>
                                    <div class="geo-review-subject">
                                        <i class="fa-solid fa-tag"></i> <?= $subject; ?>
                                    </div>
                                <?php endif; ?>
                                <p class="geo-review-text">
                                    &ldquo;<?= $bodyEscaped; ?>&rdquo;
                                </p>
                            </div>

                            <!-- Author Label: Directly beneath on bottom-left, Crisp White, Accent Blue (#83aebd) -->
                            <div class="geo-review-author-label">
                                <span class="geo-review-author-name">
                                    <i class="fa-solid fa-circle-user"></i> <?= $senderName; ?>
                                </span>
                                <span class="geo-review-separator">&bull;</span>
                                <time class="geo-review-time" datetime="<?= htmlspecialchars($msg['created_at'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fa-regular fa-clock"></i> <?= $timeFormatted; ?>
                                </time>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="geo-reviews-empty">
                <i class="fa-regular fa-comments geo-reviews-empty-icon"></i>
                <p>No messages or reviews received yet on this profile.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
