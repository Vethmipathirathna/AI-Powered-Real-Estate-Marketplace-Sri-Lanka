<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';

require_role('SELLER');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$flash = flash_get();
$page_title = 'My Properties | RealEstateAI';
$page_description = 'Your RealEstateAI property listings.';

// Soft-deactivate own listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deactivate') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid request. Please try again.');
        redirect('seller/properties.php');
    }

    $propertyId = (int) ($_POST['property_id'] ?? 0);

    try {
        $pdo = db();
        $property = $propertyId > 0 ? seller_find_own_property($pdo, $propertyId, $userId) : null;
        if ($property === null) {
            flash_set('error', 'Property not found.');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE properties SET status = ? WHERE property_id = ? AND listed_by_user_id = ?'
            );
            $stmt->execute(['INACTIVE', $propertyId, $userId]);
            flash_set('success', 'Listing marked as inactive.');
        }
    } catch (Throwable $e) {
        flash_set('error', 'Unable to update listing status right now.');
    }

    redirect('seller/properties.php');
}

$properties = [];
$loadError = null;

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT p.property_id, p.title, p.district, p.property_type, p.asking_price_lkr, p.status, p.created_at,
                (
                    SELECT pi.image_path
                    FROM property_images pi
                    WHERE pi.property_id = p.property_id
                    ORDER BY pi.is_primary DESC, pi.image_id ASC
                    LIMIT 1
                ) AS primary_image
         FROM properties p
         WHERE p.listed_by_user_id = ?
         ORDER BY p.created_at DESC, p.property_id DESC'
    );
    $stmt->execute([$userId]);
    $properties = $stmt->fetchAll();
} catch (Throwable $e) {
    $loadError = 'Unable to load your properties right now.';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('seller/index.php')); ?>">Seller Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>My Properties</span>
        </nav>

        <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="admin-eyebrow">Seller area</p>
                <h1 class="admin-title">My Properties</h1>
                <p class="admin-welcome">Only listings you own are shown here.</p>
            </div>
            <a class="btn btn-auth" href="<?php echo e(url('seller/property_create.php')); ?>">Add Property</a>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php else: ?>
            <div class="table-panel">
                <?php if ($properties === []): ?>
                    <p class="empty-state mb-0">You have not listed any properties yet.</p>
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
                                    <th scope="col">Created</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($properties as $row): ?>
                                    <?php
                                    $rowId = (int) ($row['property_id'] ?? 0);
                                    $rowStatus = (string) ($row['status'] ?? '');
                                    $thumb = admin_image_url($row['primary_image'] ?? null);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="property-cell">
                                                <?php if ($thumb !== null): ?>
                                                    <img class="property-thumb" src="<?php echo e($thumb); ?>" alt="" width="56" height="42" loading="lazy">
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
                                        <td><?php echo e(admin_format_joined($row['created_at'] ?? null)); ?></td>
                                        <td>
                                            <div class="action-stack">
                                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(url('seller/property_view.php?id=' . $rowId)); ?>">View</a>
                                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(url('seller/property_edit.php?id=' . $rowId)); ?>">Edit</a>
                                                <?php if ($rowStatus !== 'INACTIVE'): ?>
                                                    <form method="post" action="" class="d-inline">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="property_id" value="<?php echo e((string) $rowId); ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
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
