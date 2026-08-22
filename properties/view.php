<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../buyer/_helpers.php';

$propertyId = (int) ($_GET['id'] ?? 0);
$property = null;
$images = [];
$notFound = false;
$loadError = null;
$favoritePropertyIds = [];
$isFavorited = false;

try {
    $pdo = db();
    if ($propertyId <= 0) {
        $notFound = true;
    } else {
        // AVAILABLE only — PENDING / SOLD / INACTIVE are indistinguishable from missing.
        $property = marketplace_find_available_property($pdo, $propertyId);
        if ($property === null) {
            $notFound = true;
        } else {
            $images = marketplace_property_images($pdo, $propertyId);
            $user = current_user();
            if (buyer_is_buyer($user)) {
                $favoritePropertyIds = buyer_favorite_property_ids($pdo, (int) ($user['user_id'] ?? 0));
                $isFavorited = in_array($propertyId, $favoritePropertyIds, true);
            }
        }
    }
} catch (Throwable $e) {
    $loadError = 'Unable to load this property right now. Please try again later.';
}

$detailReturnPath = 'properties/view.php?id=' . max(0, $propertyId);

$page_title = $notFound || $property === null
    ? 'Property Unavailable | RealEstateAI'
    : ((string) $property['title'] . ' | RealEstateAI');
$page_description = $notFound || $property === null
    ? 'Property not found or unavailable.'
    : ('View details for ' . (string) $property['title']);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="marketplace-page marketplace-detail">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('index.php')); ?>">Home</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo e(url('properties/index.php')); ?>">Properties</a>
            <span aria-hidden="true">/</span>
            <span>Details</span>
        </nav>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
            <a class="btn btn-auth" href="<?php echo e(url('properties/index.php')); ?>">Back to Properties</a>
        <?php elseif ($notFound || $property === null): ?>
            <div class="marketplace-hero">
                <h1 class="admin-title">Property not found or unavailable</h1>
                <p class="admin-welcome">This listing is not available on the marketplace.</p>
            </div>
            <a class="btn btn-auth" href="<?php echo e(url('properties/index.php')); ?>">Browse Properties</a>
        <?php else: ?>
            <div class="marketplace-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="admin-eyebrow"><?php echo e((string) $property['property_type']); ?> · <?php echo e((string) $property['district']); ?></p>
                    <h1 class="admin-title"><?php echo e((string) $property['title']); ?></h1>
                    <p class="property-price mb-0"><?php echo e(admin_format_lkr($property['asking_price_lkr'] ?? 0)); ?></p>
                </div>
                <a class="btn btn-outline-secondary" href="<?php echo e(url('properties/index.php')); ?>">Back to listings</a>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <section class="summary-panel property-gallery-section mb-4" aria-labelledby="gallery-heading">
                        <h2 id="gallery-heading" class="summary-title">Images</h2>
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
                                            <img src="<?php echo e($imgUrl); ?>" alt="<?php echo e($isPrimary ? 'Primary property image' : 'Property image'); ?>" width="800" height="600" loading="lazy">
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

                    <section class="summary-panel mb-4">
                        <h2 class="summary-title">About this property</h2>
                        <p class="detail-description mb-0">
                            <?php
                            $desc = trim((string) ($property['description'] ?? ''));
                            echo $desc !== '' ? nl2br(e($desc)) : 'No description provided.';
                            ?>
                        </p>
                    </section>

                    <section class="summary-panel mb-4">
                        <h2 class="summary-title">Property details</h2>
                        <dl class="detail-grid mb-0">
                            <div><dt>District</dt><dd><?php echo e((string) $property['district']); ?></dd></div>
                            <div><dt>Area / locality</dt><dd><?php echo e((string) (($property['area'] ?? '') !== '' ? $property['area'] : '—')); ?></dd></div>
                            <div class="detail-span"><dt>Address</dt><dd><?php echo e((string) (($property['address'] ?? '') !== '' ? $property['address'] : '—')); ?></dd></div>
                            <div><dt>Property type</dt><dd><?php echo e((string) $property['property_type']); ?></dd></div>
                            <div><dt>Land size (perch)</dt><dd><?php echo e($property['perch'] !== null ? (string) $property['perch'] : '—'); ?></dd></div>
                            <div><dt>Bedrooms</dt><dd><?php echo e($property['bedrooms'] !== null ? (string) $property['bedrooms'] : '—'); ?></dd></div>
                            <div><dt>Bathrooms</dt><dd><?php echo e($property['bathrooms'] !== null ? (string) $property['bathrooms'] : '—'); ?></dd></div>
                            <div><dt>Kitchen area (sq.ft)</dt><dd><?php echo e($property['kitchen_area_sqft'] !== null ? (string) $property['kitchen_area_sqft'] : '—'); ?></dd></div>
                            <div><dt>Parking spots</dt><dd><?php echo e((string) ($property['parking_spots'] ?? 0)); ?></dd></div>
                            <div><dt>Floors</dt><dd><?php echo e($property['floors'] !== null ? (string) $property['floors'] : '—'); ?></dd></div>
                            <div><dt>Year built</dt><dd><?php echo e($property['year_built'] !== null ? (string) $property['year_built'] : '—'); ?></dd></div>
                            <div><dt>Garden</dt><dd><?php echo e(admin_yes_no($property['has_garden'] ?? 0)); ?></dd></div>
                            <div><dt>Air conditioning</dt><dd><?php echo e(admin_yes_no($property['has_ac'] ?? 0)); ?></dd></div>
                            <div><dt>Water supply</dt><dd><?php echo e(admin_yes_no($property['water_supply'] ?? 0)); ?></dd></div>
                            <div><dt>Electricity</dt><dd><?php echo e(admin_yes_no($property['electricity'] ?? 0)); ?></dd></div>
                            <div><dt>Asking price</dt><dd><?php echo e(admin_format_lkr($property['asking_price_lkr'] ?? 0)); ?></dd></div>
                            <div><dt>Listed</dt><dd><?php echo e(admin_format_joined($property['created_at'] ?? null)); ?></dd></div>
                        </dl>
                    </section>
                </div>

                <div class="col-lg-4">
                    <?php if (buyer_is_buyer(current_user())): ?>
                        <section class="summary-panel mb-4">
                            <h2 class="summary-title">Favorites</h2>
                            <?php
                            $favoritePropertyId = $propertyId;
                            $favoriteReturnPath = $detailReturnPath;
                            $favoriteCompact = false;
                            include __DIR__ . '/_favorite_button.php';
                            ?>
                        </section>
                    <?php endif; ?>

                    <section class="summary-panel mb-4">
                        <h2 class="summary-title">Listed By</h2>
                        <p class="mb-1 fw-semibold"><?php echo e((string) ($property['lister_name'] ?? '')); ?></p>
                        <p class="mb-0 text-muted">Role: <?php echo e((string) ($property['lister_role'] ?? '')); ?></p>
                        <p class="form-text mt-2 mb-0">Contact details will be available when messaging launches.</p>
                    </section>

                    <section class="summary-panel">
                        <h2 class="summary-title">Contact lister</h2>
                        <p class="text-muted small">Direct messaging between buyers and listers is coming soon.</p>
                        <button type="button" class="btn btn-outline-secondary w-100" disabled>Contact — Coming Soon</button>
                    </section>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
