<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';

require_role('SELLER');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$propertyId = (int) ($_GET['id'] ?? 0);
$property = null;
$images = [];
$notFound = false;
$loadError = null;

try {
    $pdo = db();
    if ($propertyId <= 0) {
        $notFound = true;
    } else {
        $property = seller_find_own_property($pdo, $propertyId, $userId);
        if ($property === null) {
            $notFound = true;
        } else {
            $images = admin_property_images($pdo, $propertyId);
        }
    }
} catch (Throwable $e) {
    $loadError = 'Unable to load this property right now.';
}

$flash = flash_get();
$page_title = $notFound || $property === null
    ? 'Property Not Found | RealEstateAI'
    : ('My Property: ' . (string) $property['title'] . ' | RealEstateAI');
$page_description = 'Seller property details.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('seller/index.php')); ?>">Seller Dashboard</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo e(url('seller/properties.php')); ?>">My Properties</a>
            <span aria-hidden="true">/</span>
            <span>Details</span>
        </nav>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php elseif ($notFound || $property === null): ?>
            <div class="admin-hero">
                <h1 class="admin-title">Property not found</h1>
                <p class="admin-welcome">You can only view listings that you own.</p>
            </div>
            <a class="btn btn-auth" href="<?php echo e(url('seller/properties.php')); ?>">Back to My Properties</a>
        <?php else: ?>
            <?php $status = (string) ($property['status'] ?? ''); ?>
            <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="admin-eyebrow">Listing #<?php echo e((string) $property['property_id']); ?></p>
                    <h1 class="admin-title"><?php echo e((string) $property['title']); ?></h1>
                    <p class="admin-welcome mb-2">
                        <span class="status-pill <?php echo e(admin_property_status_badge_class($status)); ?>"><?php echo e($status); ?></span>
                        <span class="ms-2 text-muted"><?php echo e((string) $property['property_type']); ?> · <?php echo e((string) $property['district']); ?></span>
                    </p>
                    <p class="mb-0 fw-semibold"><?php echo e(admin_format_lkr($property['asking_price_lkr'] ?? 0)); ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-auth" href="<?php echo e(url('seller/property_edit.php?id=' . (int) $property['property_id'])); ?>">Edit</a>
                    <a class="btn btn-outline-secondary" href="<?php echo e(url('seller/properties.php')); ?>">Back to list</a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <section class="summary-panel listing-details-panel mb-4">
                        <h2 class="summary-title">Listing details</h2>
                        <dl class="detail-grid mb-0">
                            <div class="detail-span"><dt>Description</dt><dd class="detail-description"><?php echo nl2br(e((string) (($property['description'] ?? '') !== '' ? $property['description'] : '—'))); ?></dd></div>
                            <div><dt>District</dt><dd><?php echo e((string) $property['district']); ?></dd></div>
                            <div><dt>Area</dt><dd><?php echo e((string) (($property['area'] ?? '') !== '' ? $property['area'] : '—')); ?></dd></div>
                            <div><dt>Address</dt><dd><?php echo e((string) (($property['address'] ?? '') !== '' ? $property['address'] : '—')); ?></dd></div>
                            <div><dt>Property type</dt><dd><?php echo e((string) $property['property_type']); ?></dd></div>
                            <div><dt>Land size (perch)</dt><dd><?php echo e($property['perch'] !== null ? (string) $property['perch'] : '—'); ?></dd></div>
                            <div><dt>Bedrooms</dt><dd><?php echo e($property['bedrooms'] !== null ? (string) $property['bedrooms'] : '—'); ?></dd></div>
                            <div><dt>Bathrooms</dt><dd><?php echo e($property['bathrooms'] !== null ? (string) $property['bathrooms'] : '—'); ?></dd></div>
                            <div><dt>Kitchen area (sq.ft)</dt><dd><?php echo e($property['kitchen_area_sqft'] !== null ? (string) $property['kitchen_area_sqft'] : '—'); ?></dd></div>
                            <div><dt>Parking spots</dt><dd><?php echo e((string) ($property['parking_spots'] ?? 0)); ?></dd></div>
                            <div><dt>Garden</dt><dd><?php echo e(admin_yes_no($property['has_garden'] ?? 0)); ?></dd></div>
                            <div><dt>Air conditioning</dt><dd><?php echo e(admin_yes_no($property['has_ac'] ?? 0)); ?></dd></div>
                            <div><dt>Water supply</dt><dd><?php echo e(admin_yes_no($property['water_supply'] ?? 0)); ?></dd></div>
                            <div><dt>Electricity</dt><dd><?php echo e(admin_yes_no($property['electricity'] ?? 0)); ?></dd></div>
                            <div><dt>Floors</dt><dd><?php echo e($property['floors'] !== null ? (string) $property['floors'] : '—'); ?></dd></div>
                            <div><dt>Year built</dt><dd><?php echo e($property['year_built'] !== null ? (string) $property['year_built'] : '—'); ?></dd></div>
                            <div><dt>Asking price</dt><dd><?php echo e(admin_format_lkr($property['asking_price_lkr'] ?? 0)); ?></dd></div>
                            <div><dt>Status</dt><dd><?php echo e($status); ?></dd></div>
                            <div><dt>Created</dt><dd><?php echo e(admin_format_joined($property['created_at'] ?? null)); ?></dd></div>
                            <div><dt>Updated</dt><dd><?php echo e(admin_format_joined($property['updated_at'] ?? null)); ?></dd></div>
                        </dl>
                    </section>
                </div>
            </div>

            <section class="summary-panel property-gallery-section" aria-labelledby="property-images-heading">
                <h2 id="property-images-heading" class="summary-title">Images</h2>
                <?php if ($images === []): ?>
                    <p class="property-gallery-empty mb-0">No images have been uploaded for this property.</p>
                <?php else: ?>
                    <div class="property-gallery<?php echo count($images) === 1 ? ' is-single' : ''; ?>">
                        <?php foreach ($images as $image): ?>
                            <?php
                            $imgUrl = admin_image_url($image['image_path'] ?? null);
                            $isPrimary = (int) ($image['is_primary'] ?? 0) === 1;
                            ?>
                            <figure class="property-gallery-item<?php echo $isPrimary ? ' is-primary' : ''; ?>">
                                <?php if ($imgUrl !== null): ?>
                                    <img src="<?php echo e($imgUrl); ?>" alt="<?php echo e($isPrimary ? 'Primary property image' : 'Property image'); ?>" width="800" height="600">
                                <?php else: ?>
                                    <div class="property-thumb-placeholder gallery-placeholder">Unavailable</div>
                                <?php endif; ?>
                                <?php if ($isPrimary): ?>
                                    <figcaption>Primary</figcaption>
                                <?php endif; ?>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
