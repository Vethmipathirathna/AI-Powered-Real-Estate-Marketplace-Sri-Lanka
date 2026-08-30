<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/support_messaging.php';

require_role('BUYER', 'SELLER');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$flash = null;
$loadError = null;
$noAdmin = false;
$context = null;
$thread = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_support') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid request. Please try again.');
    } else {
        try {
            [$ok, $message] = support_send_from_user(
                db(),
                $user,
                (string) ($_POST['message_text'] ?? '')
            );
            flash_set($ok ? 'success' : 'error', $message);
        } catch (Throwable $e) {
            flash_set('error', 'Unable to send your message right now. Please try again later.');
        }
    }
    redirect('support/index.php');
}

try {
    $pdo = db();
    if (support_find_active_admin($pdo) === null && support_find_assigned_admin_for_user($pdo, $userId) === null) {
        $noAdmin = true;
    } else {
        $context = support_get_user_context($pdo, $user);
        if ($context === null) {
            $noAdmin = true;
        } else {
            $thread = $context['thread'];
            support_mark_read($pdo, $userId, (int) $context['admin_id']);
            // Refresh after mark-read for display consistency
            $thread = support_fetch_thread($pdo, $userId, (int) $context['admin_id']);
        }
    }
} catch (Throwable $e) {
    $loadError = 'Unable to load support right now. Please try again later.';
}

$flash = flash_get();
$dashboardPath = dashboard_path_for_role((string) ($user['role'] ?? 'BUYER'));
$page_title = 'Contact Admin / Support | RealEstateAI';
$page_description = 'Send a support inquiry to RealEstateAI Admin.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url($dashboardPath)); ?>">Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>Support</span>
        </nav>

        <div class="admin-hero">
            <p class="admin-eyebrow">Help</p>
            <h1 class="admin-title">Contact Admin / Support</h1>
            <p class="admin-welcome mb-0">
                Send a private inquiry to RealEstateAI support. This is separate from property listing messages.
            </p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php elseif ($noAdmin): ?>
            <div class="summary-panel">
                <p class="empty-state mb-0">Support is temporarily unavailable. Please try again later.</p>
            </div>
        <?php else: ?>
            <section class="message-thread-panel summary-panel" aria-labelledby="support-thread-heading">
                <h2 id="support-thread-heading" class="summary-title">Admin Support</h2>

                <?php if ($thread === []): ?>
                    <p class="empty-state mb-0">No messages yet. Send your first support inquiry below.</p>
                <?php else: ?>
                    <div class="message-thread" role="log" aria-live="polite">
                        <?php foreach ($thread as $message): ?>
                            <?php
                            $isOutgoing = (int) ($message['sender_id'] ?? 0) === $userId;
                            $bubbleClass = $isOutgoing ? 'message-bubble is-outgoing' : 'message-bubble is-incoming';
                            $senderLabel = $isOutgoing ? 'You' : 'Support Admin';
                            ?>
                            <article class="<?php echo e($bubbleClass); ?>">
                                <header class="message-meta">
                                    <span class="message-sender"><?php echo e($senderLabel); ?></span>
                                    <time class="message-time" datetime="<?php echo e((string) ($message['created_at'] ?? '')); ?>">
                                        <?php echo e(message_format_datetime($message['created_at'] ?? null)); ?>
                                    </time>
                                </header>
                                <div class="message-body"><?php echo nl2br(e((string) ($message['message_text'] ?? ''))); ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="summary-panel message-compose-panel" aria-labelledby="support-compose-heading">
                <h2 id="support-compose-heading" class="summary-title"><?php echo $thread === [] ? 'Send inquiry' : 'Reply'; ?></h2>
                <form method="post" action="<?php echo e(url('support/index.php')); ?>" class="message-compose-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="send_support">
                    <div class="mb-3">
                        <label for="message_text" class="form-label">Your message</label>
                        <textarea
                            class="form-control"
                            id="message_text"
                            name="message_text"
                            rows="4"
                            maxlength="<?php echo e((string) MESSAGE_MAX_LENGTH); ?>"
                            required
                        ></textarea>
                        <div class="form-text">Up to <?php echo e((string) MESSAGE_MAX_LENGTH); ?> characters.</div>
                    </div>
                    <button type="submit" class="btn btn-auth">Send message</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
