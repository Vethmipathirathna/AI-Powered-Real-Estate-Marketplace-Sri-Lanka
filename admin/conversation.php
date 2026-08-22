<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/messaging.php';
require_once __DIR__ . '/../admin/_helpers.php';

require_role('ADMIN');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$propertyId = (int) ($_GET['property_id'] ?? $_POST['property_id'] ?? 0);
$buyerId = (int) ($_GET['buyer_id'] ?? $_POST['buyer_id'] ?? 0);
$context = null;
$thread = [];
$invalid = false;
$loadError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid request. Please try again.');
    } else {
        $postPropertyId = (int) ($_POST['property_id'] ?? 0);
        $postBuyerId = (int) ($_POST['buyer_id'] ?? 0);
        try {
            [$ok, $message] = message_send(
                db(),
                $user,
                $postPropertyId,
                (string) ($_POST['message_text'] ?? ''),
                $postBuyerId
            );
            flash_set($ok ? 'success' : 'error', $message);
        } catch (Throwable $e) {
            flash_set('error', 'Unable to send your message right now.');
        }
        redirect('admin/conversation.php?property_id=' . max(0, $postPropertyId) . '&buyer_id=' . max(0, $postBuyerId));
    }
}

try {
    $pdo = db();
    $context = message_get_context($pdo, $user, $propertyId, $buyerId, false);
    if ($context === null) {
        $invalid = true;
    } else {
        $thread = message_fetch_thread(
            $pdo,
            $propertyId,
            (int) $context['buyer_id'],
            (int) $context['lister_id']
        );
        message_mark_read($pdo, $userId, $propertyId, (int) $context['buyer_id']);
    }
} catch (Throwable $e) {
    $loadError = 'Unable to load this conversation right now. Please try again later.';
}

$flash = flash_get();
$page_title = $invalid ? 'Conversation unavailable | RealEstateAI' : 'Conversation | RealEstateAI';
$page_description = 'Reply to a buyer inquiry for your Admin-owned listing.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('admin/index.php')); ?>">Admin Dashboard</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo e(url('admin/messages.php')); ?>">My Listing Messages</a>
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
            <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/messages.php')); ?>">Back to messages</a>
        <?php elseif ($invalid || $context === null): ?>
            <div class="summary-panel">
                <h1 class="admin-title h3">Conversation not found</h1>
                <p class="empty-state">This conversation is unavailable or you do not have access.</p>
                <a class="btn btn-auth" href="<?php echo e(url('admin/messages.php')); ?>">Back to messages</a>
            </div>
        <?php else: ?>
            <?php
            $property = $context['property'];
            $isAvailable = (bool) $context['is_available'];
            $hasHistory = $thread !== [];
            $canReply = $hasHistory;
            $otherPartyLabel = (string) $context['buyer_name'];
            $viewerId = $userId;
            $formAction = 'admin/conversation.php';
            ?>
            <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="admin-eyebrow">Buyer inquiry · your listing</p>
                    <h1 class="admin-title h3"><?php echo e((string) ($property['title'] ?? '')); ?></h1>
                    <p class="admin-welcome mb-1">
                        Buyer: <?php echo e((string) $context['buyer_name']); ?>
                        <span class="status-pill"><?php echo e((string) $context['buyer_role']); ?></span>
                    </p>
                    <p class="text-muted mb-0">
                        <?php echo e((string) ($property['district'] ?? '')); ?>
                        · <?php echo e(admin_format_lkr($property['asking_price_lkr'] ?? 0)); ?>
                    </p>
                </div>
                <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/messages.php')); ?>">All messages</a>
            </div>

            <?php if (!$isAvailable): ?>
                <div class="alert alert-warning" role="alert">
                    This property is no longer available on the marketplace. You can still reply to continue an existing conversation.
                </div>
            <?php endif; ?>

            <?php include __DIR__ . '/../includes/_conversation_thread.php'; ?>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
