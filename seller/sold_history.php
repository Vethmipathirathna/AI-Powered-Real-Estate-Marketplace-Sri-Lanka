<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';

require_role('SELLER');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$page_title = 'Sold History | RealEstateAI';
$page_description = 'Your sold property listings on RealEstateAI.';

$properties = [];
$loadError = null;

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT p.property_id, p.title, p.district, p.property_type, p.asking_price_lkr, p.status, p.updated_at,
                (
                    SELECT pi.image_path
                    FROM property_images pi
                    WHERE pi.property_id = p.property_id
                    ORDER BY pi.is_primary DESC, pi.image_id ASC
                    LIMIT 1
                ) AS primary_image
         FROM properties p
         WHERE p.listed_by_user_id = ? AND p.status = ?
         ORDER BY p.updated_at DESC, p.property_id DESC'
    );
    $stmt->execute([$userId, 'SOLD']);
    $properties = $stmt->fetchAll();
} catch (Throwable $e) {
    $loadError = 'Unable to load your sold property history right now.';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('seller/index.php')); ?>">Seller Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>Sold History</span>
        </nav>

        <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="admin-eyebrow">Seller area</p>
                <h1 class="admin-title">Sold History</h1>
                <p class="admin-welcome mb-0">Properties you have marked as sold. Dates reflect the listing&apos;s last update time.</p>
            </div>
            <a class="btn btn-outline-secondary" href="<?php echo e(url('seller/properties.php')); ?>">Back to My Properties</a>
        </div>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <div class="table-panel">
                <?php if ($properties === []): ?>
                    <p class="empty-state mb-0">No sold properties have been recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Property</th>
                                    <th scope="col">District</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Asking Price</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Sold/Updated Date</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($properties as $row): ?>
                                    <?php
                                    $rowId = (int) ($row['property_id'] ?? 0);
                                    $rowStatus = strtoupper((string) ($row['status'] ?? ''));
                                    $thumb = admin_image_url($row['primary_image'] ?? null);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="property-cell">
                                                <?php if ($thumb !== null): ?>
                                                    <img class="property-thumb" src="<?php echo e($thumb); ?>" alt="<?php echo e((string) ($row['title'] ?? 'Property')); ?>" width="56" height="42" loading="lazy">
                                                <?php else: ?>
                                                    <div class="property-thumb property-thumb-placeholder" aria-hidden="true">No image</div>
                                                <?php endif; ?>
                                                <span class="property-cell-title"><?php echo e((string) ($row['title'] ?? '')); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo e((string) ($row['district'] ?? '')); ?></td>
                                        <td><?php echo e((string) ($row['property_type'] ?? '')); ?></td>
                                        <td><?php echo e(admin_format_lkr($row['asking_price_lkr'] ?? 0)); ?></td>
                                        <td><span class="status-pill <?php echo e(admin_property_status_badge_class($rowStatus)); ?>"><?php echo e($rowStatus); ?></span></td>
                                        <td><?php echo e(admin_format_joined($row['updated_at'] ?? null)); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(url('seller/property_view.php?id=' . $rowId)); ?>">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
