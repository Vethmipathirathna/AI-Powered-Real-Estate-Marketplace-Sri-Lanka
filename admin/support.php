<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/support_messaging.php';
require_once __DIR__ . '/_helpers.php';

require_role('ADMIN');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$flash = flash_get();
$summaries = [];
$loadError = null;

try {
    $summaries = support_inbox_for_admin(db(), $userId);
} catch (Throwable $e) {
    $loadError = 'Unable to load the support inbox right now. Please try again later.';
}

$page_title = 'Support Inbox | RealEstateAI';
$page_description = 'Admin support inquiries from buyers and sellers.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('admin/index.php')); ?>">Admin Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>Support Inbox</span>
        </nav>

        <div class="admin-hero">
            <p class="admin-eyebrow">Administration</p>
            <h1 class="admin-title">Support Inbox</h1>
            <p class="admin-welcome mb-0">
                User inquiries assigned to you. This is separate from My Listing Messages (property conversations).
            </p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php elseif ($summaries === []): ?>
            <div class="summary-panel">
                <p class="empty-state mb-0">No support inquiries assigned to you yet.</p>
            </div>
        <?php else: ?>
            <div class="message-inbox-list">
                <?php foreach ($summaries as $row): ?>
                    <?php
                    $otherId = (int) ($row['user_id'] ?? 0);
                    $unread = (int) ($row['unread_count'] ?? 0);
                    $href = 'admin/support_conversation.php?user_id=' . $otherId;
                    ?>
                    <a class="message-inbox-item<?php echo $unread > 0 ? ' has-unread' : ''; ?>" href="<?php echo e(url($href)); ?>">
                        <div class="message-inbox-main">
                            <h2 class="message-inbox-title"><?php echo e((string) ($row['user_name'] ?? '')); ?></h2>
                            <p class="message-inbox-subtitle mb-1">
                                <span class="status-pill"><?php echo e((string) ($row['user_role'] ?? '')); ?></span>
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
                            <span class="message-inbox-action">View / Reply</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
