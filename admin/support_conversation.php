<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/support_messaging.php';
require_once __DIR__ . '/_helpers.php';

require_role('ADMIN');

$user = current_user();
$adminId = (int) ($user['user_id'] ?? 0);
$otherUserId = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$context = null;
$thread = [];
$invalid = false;
$loadError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_support_reply') {
    $postUserId = (int) ($_POST['user_id'] ?? 0);
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid request. Please try again.');
    } else {
        try {
            [$ok, $message] = support_send_from_admin(
                db(),
                $user,
                $postUserId,
                (string) ($_POST['message_text'] ?? '')
            );
            flash_set($ok ? 'success' : 'error', $message);
        } catch (Throwable $e) {
            flash_set('error', 'Unable to send your reply right now.');
        }
    }
    redirect('admin/support_conversation.php?user_id=' . max(0, $postUserId));
}

try {
    $pdo = db();
    $context = support_get_admin_context($pdo, $user, $otherUserId);
    if ($context === null) {
        $invalid = true;
    } else {
        $thread = $context['thread'];
        support_mark_read($pdo, $adminId, (int) $context['user_id']);
        $thread = support_fetch_thread($pdo, (int) $context['user_id'], $adminId);
    }
} catch (Throwable $e) {
    $loadError = 'Unable to load this support conversation right now.';
}

$flash = flash_get();
$page_title = $invalid ? 'Support conversation unavailable | RealEstateAI' : 'Support Conversation | RealEstateAI';
$page_description = 'Reply to a user support inquiry.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('admin/index.php')); ?>">Admin Dashboard</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo e(url('admin/support.php')); ?>">Support Inbox</a>
            <span aria-hidden="true">/</span>
            <span>Conversation</span>
        </nav>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
            <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/support.php')); ?>">Back to Support Inbox</a>
        <?php elseif ($invalid || $context === null): ?>
            <div class="summary-panel">
                <h1 class="admin-title h3">Conversation not found</h1>
                <p class="empty-state">This support conversation is unavailable or you do not have access.</p>
                <a class="btn btn-auth" href="<?php echo e(url('admin/support.php')); ?>">Back to Support Inbox</a>
            </div>
        <?php else: ?>
            <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="admin-eyebrow">Support inquiry</p>
                    <h1 class="admin-title h3"><?php echo e((string) $context['user_name']); ?></h1>
                    <p class="admin-welcome mb-0">
                        Role: <?php echo e((string) $context['user_role']); ?>
                    </p>
                </div>
                <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/support.php')); ?>">Back to Support Inbox</a>
            </div>

            <section class="message-thread-panel summary-panel" aria-labelledby="support-thread-heading">
                <h2 id="support-thread-heading" class="summary-title">Conversation</h2>
                <?php if ($thread === []): ?>
                    <p class="empty-state mb-0">No messages in this conversation.</p>
                <?php else: ?>
                    <div class="message-thread" role="log" aria-live="polite">
                        <?php foreach ($thread as $message): ?>
                            <?php
                            $isOutgoing = (int) ($message['sender_id'] ?? 0) === $adminId;
                            $bubbleClass = $isOutgoing ? 'message-bubble is-outgoing' : 'message-bubble is-incoming';
                            $senderLabel = $isOutgoing ? 'You' : (string) $context['user_name'];
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
                <h2 id="support-compose-heading" class="summary-title">Reply</h2>
                <form method="post" action="<?php echo e(url('admin/support_conversation.php')); ?>" class="message-compose-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="send_support_reply">
                    <input type="hidden" name="user_id" value="<?php echo e((string) $context['user_id']); ?>">
                    <div class="mb-3">
                        <label for="message_text" class="form-label">Your reply</label>
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
                    <button type="submit" class="btn btn-auth">Send reply</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
