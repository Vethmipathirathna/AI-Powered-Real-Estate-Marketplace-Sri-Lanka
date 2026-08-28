<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/messaging.php';
require_once __DIR__ . '/../admin/_helpers.php';

require_role('BUYER');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$flash = flash_get();
$summaries = [];
$loadError = null;

try {
    $summaries = message_summaries_for_buyer(db(), $userId);
} catch (Throwable $e) {
    $loadError = 'Unable to load your messages right now. Please try again later.';
}

$page_title = 'Messages | RealEstateAI';
$page_description = 'Your property conversations on RealEstateAI.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('buyer/index.php')); ?>">Buyer Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>Messages</span>
        </nav>

        <div class="admin-hero">
            <p class="admin-eyebrow">Buyer area</p>
            <h1 class="admin-title">Messages</h1>
            <p class="admin-welcome">Conversations with property listers about listings you have contacted.</p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php elseif ($summaries === []): ?>
            <div class="summary-panel empty-state-panel">
                <p class="empty-state">You have no conversations yet.</p>
                <a class="btn btn-auth" href="<?php echo e(url('properties/index.php')); ?>">Browse properties</a>
            </div>
        <?php else: ?>
            <div class="message-inbox-list">
                <?php foreach ($summaries as $row): ?>
                    <?php
                    $propertyId = (int) ($row['property_id'] ?? 0);
                    $unread = (int) ($row['unread_count'] ?? 0);
                    $status = strtoupper((string) ($row['status'] ?? ''));
                    $href = message_conversation_path_for_role('BUYER', $propertyId);
                    ?>
                    <a class="message-inbox-item<?php echo $unread > 0 ? ' has-unread' : ''; ?>" href="<?php echo e(url($href)); ?>">
                        <div class="message-inbox-main">
                            <h2 class="message-inbox-title"><?php echo e((string) ($row['title'] ?? '')); ?></h2>
                            <p class="message-inbox-subtitle mb-1">
                                Lister: <?php echo e((string) ($row['lister_name'] ?? '')); ?>
                                <span class="status-pill"><?php echo e((string) ($row['lister_role'] ?? '')); ?></span>
                                <?php if ($status !== 'AVAILABLE'): ?>
                                    <span class="status-pill status-pill-muted"><?php echo e($status); ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="message-inbox-preview mb-0"><?php echo e(message_preview($row['last_message'] ?? null)); ?></p>
                        </div>
                        <div class="message-inbox-meta">
                            <time datetime="<?php echo e((string) ($row['last_activity'] ?? '')); ?>">
                                <?php echo e(message_format_datetime($row['last_activity'] ?? null)); ?>
                            </time>
                            <?php if ($unread > 0): ?>
                                <span class="message-unread-badge"><?php echo e((string) $unread); ?> unread</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
