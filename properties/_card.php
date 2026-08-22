<?php
/**
 * Shared marketplace property card partial.
 *
 * Expects: $property (array)
 */
declare(strict_types=1);

$cardId = (int) ($property['property_id'] ?? 0);
$cardTitle = (string) ($property['title'] ?? '');
$cardDistrict = (string) ($property['district'] ?? '');
$cardArea = trim((string) ($property['area'] ?? ''));
$cardType = (string) ($property['property_type'] ?? '');
$cardBeds = $property['bedrooms'] ?? null;
$cardBaths = $property['bathrooms'] ?? null;
$cardPerch = $property['perch'] ?? null;
$cardPrice = $property['asking_price_lkr'] ?? 0;
$cardLister = (string) ($property['lister_name'] ?? '');
$cardRole = (string) ($property['lister_role'] ?? '');
$cardThumb = admin_image_url($property['primary_image'] ?? null);
$locationLabel = $cardArea !== '' ? ($cardDistrict . ', ' . $cardArea) : $cardDistrict;
?>
<article class="property-card">
    <div class="property-media">
        <?php if ($cardThumb !== null): ?>
            <img
                class="property-image"
                src="<?php echo e($cardThumb); ?>"
                alt=""
                width="900"
                height="600"
                loading="lazy"
            >
        <?php else: ?>
            <div class="property-image property-image-placeholder" aria-hidden="true">No image</div>
        <?php endif; ?>
    </div>
    <div class="property-body">
        <p class="property-type-badge mb-1"><?php echo e($cardType); ?></p>
        <h3 class="property-title"><?php echo e($cardTitle); ?></h3>
        <p class="property-location"><?php echo e($locationLabel !== '' ? $locationLabel : 'Sri Lanka'); ?></p>
        <p class="property-price"><?php echo e(admin_format_lkr($cardPrice)); ?></p>
        <ul class="property-meta list-unstyled">
            <li><span><?php echo e($cardBeds !== null ? (string) $cardBeds : '—'); ?></span> Bedrooms</li>
            <li><span><?php echo e($cardBaths !== null ? (string) $cardBaths : '—'); ?></span> Bathrooms</li>
            <li><span><?php echo e($cardPerch !== null ? (string) $cardPerch : '—'); ?></span> Perches</li>
        </ul>
        <?php if ($cardLister !== ''): ?>
            <p class="property-lister text-muted small mb-3">
                Listed by <?php echo e($cardLister); ?>
                <?php if ($cardRole !== ''): ?>
                    <span>(<?php echo e($cardRole); ?>)</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <a class="btn btn-property" href="<?php echo e(url('properties/view.php?id=' . $cardId)); ?>">View Details</a>
    </div>
</article>
