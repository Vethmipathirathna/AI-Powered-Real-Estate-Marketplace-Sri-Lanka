<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../properties/_helpers.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/messaging.php';

require_role('BUYER');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$flash = flash_get();
$availableCount = null;
$favoritesCount = null;
$unreadMessages = null;
$loadError = null;

try {
    $pdo = db();
    $availableCount = marketplace_count_available($pdo);
    $favoritesCount = buyer_count_available_favorites($pdo, $userId);
    $unreadMessages = message_unread_count($pdo, $userId, 'BUYER');
} catch (Throwable $e) {
    $loadError = 'Unable to load marketplace stats right now.';
}

$page_title = 'Buyer Dashboard | RealEstateAI';
$page_description = 'Your RealEstateAI buyer dashboard.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <div class="admin-hero">
            <p class="admin-eyebrow">Buyer area</p>
            <h1 class="admin-title">Welcome, <?php echo e($user['full_name'] ?? ''); ?></h1>
            <p class="admin-welcome">
                Browse available listings, save favorites, and explore Sri Lanka property opportunities.
            </p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-warning" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <span class="stat-label">Available listings</span>
                        <span class="stat-value"><?php echo e((string) $availableCount); ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <span class="stat-label">Saved favorites (available)</span>
                        <span class="stat-value"><?php echo e((string) $favoritesCount); ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <span class="stat-label">Unread messages</span>
                        <span class="stat-value"><?php echo e((string) ($unreadMessages ?? 0)); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <section class="admin-section" aria-labelledby="buyer-shortcuts-heading">
            <div class="admin-section-header">
                <h2 id="buyer-shortcuts-heading" class="admin-section-title">Quick access</h2>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <a class="shortcut-card shortcut-card-link h-100" href="<?php echo e(url('properties/index.php')); ?>">
                        <h3 class="shortcut-title">Browse Properties</h3>
                        <p class="shortcut-text">Search and filter AVAILABLE homes, apartments, land and commercial listings.</p>
                        <span class="shortcut-badge">Open marketplace</span>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a class="shortcut-card shortcut-card-link h-100" href="<?php echo e(url('buyer/favorites.php')); ?>">
                        <h3 class="shortcut-title">My Favorites</h3>
                        <p class="shortcut-text">Review properties you have saved for later.</p>
                        <span class="shortcut-badge">View saved listings</span>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="shortcut-card h-100">
                        <h3 class="shortcut-title">AI Price Estimator</h3>
                        <p class="shortcut-text">Estimate fair market value with AI insights built for Sri Lanka.</p>
                        <span class="shortcut-badge">Coming soon</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a class="shortcut-card shortcut-card-link h-100" href="<?php echo e(url('buyer/messages.php')); ?>">
                        <h3 class="shortcut-title">Messages</h3>
                        <p class="shortcut-text">Contact property listers and follow up on your conversations.</p>
                        <span class="shortcut-badge">
                            <?php echo ($unreadMessages ?? 0) > 0 ? e((string) $unreadMessages) . ' unread' : 'Open inbox'; ?>
                        </span>
                    </a>
                </div>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
