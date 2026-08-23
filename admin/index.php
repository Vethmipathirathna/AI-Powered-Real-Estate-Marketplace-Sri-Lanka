<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/messaging.php';
require_once __DIR__ . '/../includes/support_messaging.php';

require_role('ADMIN');

$user = current_user();
$flash = flash_get();
$page_title = 'Admin Dashboard | RealEstateAI';
$page_description = 'Admin overview of users, properties and AI prediction activity.';

$stats = [
    'total_users' => 0,
    'buyers' => 0,
    'sellers' => 0,
    'admins' => 0,
    'total_properties' => 0,
    'available' => 0,
    'pending' => 0,
    'sold' => 0,
    'inactive' => 0,
    'ai_predictions' => 0,
];
$recentUsers = [];
$recentProperties = [];
$ownListingUnread = 0;
$supportUnread = 0;
$loadError = null;

try {
    $pdo = db();

    $stats['total_users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['total_properties'] = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
    $stats['ai_predictions'] = (int) $pdo->query('SELECT COUNT(*) FROM ai_predictions')->fetchColumn();

    $roleStmt = $pdo->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role');
    foreach ($roleStmt->fetchAll() as $row) {
        $role = strtoupper((string) ($row['role'] ?? ''));
        $total = (int) ($row['total'] ?? 0);
        if ($role === 'BUYER') {
            $stats['buyers'] = $total;
        } elseif ($role === 'SELLER') {
            $stats['sellers'] = $total;
        } elseif ($role === 'ADMIN') {
            $stats['admins'] = $total;
        }
    }

    $statusStmt = $pdo->query('SELECT status, COUNT(*) AS total FROM properties GROUP BY status');
    foreach ($statusStmt->fetchAll() as $row) {
        $status = strtoupper((string) ($row['status'] ?? ''));
        $total = (int) ($row['total'] ?? 0);
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

    $usersStmt = $pdo->query(
        'SELECT full_name, email, role, status, created_at
         FROM users
         ORDER BY created_at DESC, user_id DESC
         LIMIT 5'
    );
    $recentUsers = $usersStmt->fetchAll();

    $propertiesStmt = $pdo->query(
        'SELECT p.title, p.district, p.asking_price_lkr, p.status, p.created_at, u.full_name AS lister_name
         FROM properties p
         INNER JOIN users u ON u.user_id = p.listed_by_user_id
         ORDER BY p.created_at DESC, p.property_id DESC
         LIMIT 5'
    );
    $recentProperties = $propertiesStmt->fetchAll();
    $ownListingUnread = message_unread_count($pdo, (int) ($user['user_id'] ?? 0), 'ADMIN');
    $supportUnread = support_unread_count_for_admin($pdo, (int) ($user['user_id'] ?? 0));
} catch (Throwable $e) {
    $loadError = 'Unable to load dashboard data right now. Please try again later.';
}

function admin_format_lkr($amount): string
{
    return 'LKR ' . number_format((float) $amount, 2);
}

function admin_format_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '—';
    }

    return date('d M Y', $timestamp);
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <div class="admin-hero">
            <p class="admin-eyebrow">Administration</p>
            <h1 class="admin-title">Admin Dashboard</h1>
            <p class="admin-welcome">
                Welcome back, <?php echo e($user['full_name'] ?? 'Admin'); ?>.
                Review marketplace activity and prepare for upcoming management modules.
            </p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <section class="admin-section" aria-labelledby="overview-heading">
                <div class="admin-section-header">
                    <h2 id="overview-heading" class="admin-section-title">Overview</h2>
                    <p class="admin-section-text">Live counts from the RealEstateAI database.</p>
                </div>
                <div class="row g-3">
                    <div class="col-6 col-lg-4 col-xl-2">
                        <div class="stat-card">
                            <span class="stat-label">Total Users</span>
                            <strong class="stat-value"><?php echo e((string) $stats['total_users']); ?></strong>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4 col-xl-2">
                        <div class="stat-card">
                            <span class="stat-label">Buyers</span>
                            <strong class="stat-value"><?php echo e((string) $stats['buyers']); ?></strong>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4 col-xl-2">
                        <div class="stat-card">
                            <span class="stat-label">Sellers</span>
                            <strong class="stat-value"><?php echo e((string) $stats['sellers']); ?></strong>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4 col-xl-2">
                        <div class="stat-card">
                            <span class="stat-label">Properties</span>
                            <strong class="stat-value"><?php echo e((string) $stats['total_properties']); ?></strong>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4 col-xl-2">
                        <div class="stat-card">
                            <span class="stat-label">Available</span>
                            <strong class="stat-value"><?php echo e((string) $stats['available']); ?></strong>
                        </div>
                    </div>
                    <div class="col-6 col-lg-4 col-xl-2">
                        <div class="stat-card">
                            <span class="stat-label">AI Predictions</span>
                            <strong class="stat-value"><?php echo e((string) $stats['ai_predictions']); ?></strong>
                            <a class="btn btn-sm btn-outline-secondary mt-2" href="<?php echo e(url('admin/predictions.php')); ?>">View AI Predictions</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="admin-section" aria-labelledby="summaries-heading">
                <div class="admin-section-header">
                    <h2 id="summaries-heading" class="admin-section-title">Account &amp; Property Summary</h2>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="summary-panel h-100">
                            <h3 class="summary-title">Users by Role</h3>
                            <ul class="summary-list list-unstyled mb-0">
                                <li><span>BUYER</span><strong><?php echo e((string) $stats['buyers']); ?></strong></li>
                                <li><span>SELLER</span><strong><?php echo e((string) $stats['sellers']); ?></strong></li>
                                <li><span>ADMIN</span><strong><?php echo e((string) $stats['admins']); ?></strong></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="summary-panel h-100">
                            <h3 class="summary-title">Properties by Status</h3>
                            <ul class="summary-list list-unstyled mb-0">
                                <li><span>Total</span><strong><?php echo e((string) $stats['total_properties']); ?></strong></li>
                                <li><span>Available</span><strong><?php echo e((string) $stats['available']); ?></strong></li>
                                <li><span>Pending</span><strong><?php echo e((string) $stats['pending']); ?></strong></li>
                                <li><span>Sold</span><strong><?php echo e((string) $stats['sold']); ?></strong></li>
                                <li><span>Inactive</span><strong><?php echo e((string) $stats['inactive']); ?></strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section class="admin-section" aria-labelledby="shortcuts-heading">
                <div class="admin-section-header">
                    <h2 id="shortcuts-heading" class="admin-section-title">Management Shortcuts</h2>
                    <p class="admin-section-text">User, property and AI prediction monitoring tools.</p>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-xl">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('admin/users.php')); ?>">
                            <h3 class="shortcut-title">Manage Users</h3>
                            <p class="shortcut-text">Review and manage buyer, seller and admin accounts.</p>
                            <span class="shortcut-badge">Open module</span>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('admin/properties.php')); ?>">
                            <h3 class="shortcut-title">Manage Properties</h3>
                            <p class="shortcut-text">Moderate listings and property publication status.</p>
                            <span class="shortcut-badge">Open module</span>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('admin/messages.php')); ?>">
                            <h3 class="shortcut-title">My Listing Messages</h3>
                            <p class="shortcut-text">Private buyer conversations for properties you listed — not other listers' inboxes.</p>
                            <span class="shortcut-badge"><?php echo $ownListingUnread > 0 ? e((string) $ownListingUnread) . ' unread' : 'Open inbox'; ?></span>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('admin/support.php')); ?>">
                            <h3 class="shortcut-title">Support Inbox</h3>
                            <p class="shortcut-text">View and reply to buyer and seller support inquiries assigned to you.</p>
                            <span class="shortcut-badge"><?php echo $supportUnread > 0 ? e((string) $supportUnread) . ' unread' : 'Open support'; ?></span>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('admin/predictions.php')); ?>">
                            <h3 class="shortcut-title">AI Predictions</h3>
                            <p class="shortcut-text">Monitor house-price estimates recorded by all users.</p>
                            <span class="shortcut-badge">View AI Predictions</span>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('admin/reports.php')); ?>">
                            <h3 class="shortcut-title">System Reports</h3>
                            <p class="shortcut-text">View system usage summaries for users, listings, AI and messages.</p>
                            <span class="shortcut-badge">Open reports</span>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl">
                        <a class="shortcut-card shortcut-card-link" href="<?php echo e(url('ai/estimate.php')); ?>">
                            <h3 class="shortcut-title">AI Price Estimator</h3>
                            <p class="shortcut-text">Generate an AI-powered house price estimate for Sri Lankan properties.</p>
                            <span class="shortcut-badge">Get estimate</span>
                        </a>
                    </div>
                </div>
            </section>

            <section class="admin-section" aria-labelledby="recent-users-heading">
                <div class="admin-section-header">
                    <h2 id="recent-users-heading" class="admin-section-title">Recent Users</h2>
                    <p class="admin-section-text">Latest registered accounts.</p>
                </div>
                <div class="table-panel">
                    <?php if ($recentUsers === []): ?>
                        <p class="empty-state mb-0">No users found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Role</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $recentUser): ?>
                                        <tr>
                                            <td><?php echo e((string) ($recentUser['full_name'] ?? '')); ?></td>
                                            <td><?php echo e((string) ($recentUser['email'] ?? '')); ?></td>
                                            <td><span class="status-pill"><?php echo e((string) ($recentUser['role'] ?? '')); ?></span></td>
                                            <td><?php echo e((string) ($recentUser['status'] ?? '')); ?></td>
                                            <td><?php echo e(admin_format_date($recentUser['created_at'] ?? null)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="admin-section" aria-labelledby="recent-properties-heading">
                <div class="admin-section-header">
                    <h2 id="recent-properties-heading" class="admin-section-title">Recent Properties</h2>
                    <p class="admin-section-text">Latest marketplace listings.</p>
                </div>
                <div class="table-panel">
                    <?php if ($recentProperties === []): ?>
                        <p class="empty-state mb-0">No properties have been listed yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Title</th>
                                        <th scope="col">Listed By</th>
                                        <th scope="col">District</th>
                                        <th scope="col">Asking Price</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentProperties as $property): ?>
                                        <tr>
                                            <td><?php echo e((string) ($property['title'] ?? '')); ?></td>
                                            <td><?php echo e((string) ($property['lister_name'] ?? '')); ?></td>
                                            <td><?php echo e((string) ($property['district'] ?? '')); ?></td>
                                            <td><?php echo e(admin_format_lkr($property['asking_price_lkr'] ?? 0)); ?></td>
                                            <td><span class="status-pill"><?php echo e((string) ($property['status'] ?? '')); ?></span></td>
                                            <td><?php echo e(admin_format_date($property['created_at'] ?? null)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
