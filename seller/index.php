<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/messaging.php';
require_once __DIR__ . '/../includes/support_messaging.php';

require_role('SELLER');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$flash = flash_get();
$page_title = 'Seller Dashboard | RealEstateAI';
$page_description = 'Manage your RealEstateAI property listings.';

$stats = [
    'total' => 0,
    'available' => 0,
    'pending' => 0,
    'sold' => 0,
    'inactive' => 0,
];
$unreadMessages = 0;
$unreadSupport = 0;
$loadError = null;

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS total
         FROM properties
         WHERE listed_by_user_id = ?
         GROUP BY status'
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $status = strtoupper((string) ($row['status'] ?? ''));
        $total = (int) ($row['total'] ?? 0);
        $stats['total'] += $total;
        if ($status === 'AVAILABLE') {
            $stats['available'] = $total;
        } elseif ($status === 'PENDING') {
            $stats['pending'] = $total;
        } elseif ($status === 'SOLD') {
            $stats['sold'] = $total;
        } elseif ($status === 'INACTIVE') {
            $stats['inactive'] = $total;
        }
    }
    $unreadMessages = message_unread_count($pdo, $userId, 'SELLER');
    $unreadSupport = support_unread_count_for_user($pdo, $userId);
} catch (Throwable $e) {
    $loadError = 'Unable to load your listing statistics right now.';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="admin-eyebrow">Seller area</p>
                <h1 class="admin-title">Welcome, <?php echo e($user['full_name'] ?? ''); ?></h1>
                <p class="admin-welcome">Manage your property listings and track their moderation status.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-auth" href="<?php echo e(url('seller/property_create.php')); ?>">Add Property</a>
                <a class="btn btn-outline-secondary" href="<?php echo e(url('seller/properties.php')); ?>">My Properties</a>
            </div>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <section class="admin-section" aria-labelledby="seller-stats-heading">
                <div class="admin-section-header">
                    <h2 id="seller-stats-heading" class="admin-section-title">My listing overview</h2>
                    <p class="admin-section-text">Counts for properties you listed only.</p>
                </div>
                <div class="seller-stats-grid">
                    <div class="seller-stat-card"><span class="stat-label">Total</span><strong class="stat-value"><?php echo e((string) $stats['total']); ?></strong></div>
                    <div class="seller-stat-card"><span class="stat-label">Available</span><strong class="stat-value"><?php echo e((string) $stats['available']); ?></strong></div>
                    <div class="seller-stat-card"><span class="stat-label">Pending</span><strong class="stat-value"><?php echo e((string) $stats['pending']); ?></strong></div>
                    <div class="seller-stat-card"><span class="stat-label">Sold</span><strong class="stat-value"><?php echo e((string) $stats['sold']); ?></strong></div>
                    <div class="seller-stat-card"><span class="stat-label">Inactive</span><strong class="stat-value"><?php echo e((string) $stats['inactive']); ?></strong></div>
                    <div class="seller-stat-card"><span class="stat-label">Unread messages</span><strong class="stat-value"><?php echo e((string) $unreadMessages); ?></strong></div>
                </div>
            </section>

            <section class="admin-section">
                <div class="row g-3">
                    <div class="col-md-4">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('seller/property_create.php')); ?>">
                            <h3 class="shortcut-title">Add Property</h3>
                            <p class="shortcut-text">Create a new listing for Admin review.</p>
                            <span class="shortcut-badge">New listing</span>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('seller/properties.php')); ?>">
                            <h3 class="shortcut-title">My Properties</h3>
                            <p class="shortcut-text">View and edit the listings you own.</p>
                            <span class="shortcut-badge">Manage</span>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('seller/sold_history.php')); ?>">
                            <h3 class="shortcut-title">Sold History</h3>
                            <p class="shortcut-text">Review properties you have marked as sold.</p>
                            <span class="shortcut-badge"><?php echo $stats['sold'] > 0 ? e((string) $stats['sold']) . ' sold' : 'View history'; ?></span>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('seller/messages.php')); ?>">
                            <h3 class="shortcut-title">Messages</h3>
                            <p class="shortcut-text">Read and reply to buyer inquiries for your listings.</p>
                            <span class="shortcut-badge"><?php echo $unreadMessages > 0 ? e((string) $unreadMessages) . ' unread' : 'Open inbox'; ?></span>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('support/index.php')); ?>">
                            <h3 class="shortcut-title">Help / Contact Admin</h3>
                            <p class="shortcut-text">Send a private support inquiry to RealEstateAI Admin.</p>
                            <span class="shortcut-badge"><?php echo $unreadSupport > 0 ? e((string) $unreadSupport) . ' unread' : 'Open support'; ?></span>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('ai/estimate.php')); ?>">
                            <h3 class="shortcut-title">AI Price Estimator</h3>
                            <p class="shortcut-text">Generate an AI-powered house price estimate for Sri Lankan properties.</p>
                            <span class="shortcut-badge">Get estimate</span>
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
