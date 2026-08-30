<?php
/**
 * Shared marketplace property card partial.
 *
 * Expects: $property (array)
 * Optional: $favoritePropertyIds (list<int>), $favoriteReturnPath (string), $hideFavoriteButton (bool)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../buyer/_helpers.php';

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

$cardUser = current_user();
$hideFavoriteControl = (bool) ($hideFavoriteButton ?? false);
$showFavoriteButton = buyer_should_show_favorite_button($cardUser, $hideFavoriteControl);
$showGuestFavoriteLink = buyer_should_show_guest_favorite_link($cardUser);
$isFavorited = false;
if ($showFavoriteButton) {
    $favoriteIds = $favoritePropertyIds ?? [];
    $isFavorited = in_array($cardId, $favoriteIds, true);
}
$cardReturnPath = buyer_safe_return_path($favoriteReturnPath ?? 'properties/index.php');
?>
<article class="property-card">
    <div class="property-media">
        <?php if ($cardThumb !== null): ?>
            <img
                class="property-image"
                src="<?php echo e($cardThumb); ?>"
                alt="<?php echo e($cardTitle !== '' ? $cardTitle : 'Property listing'); ?>"
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
                <?php
                $cardRoleLabel = match (strtoupper($cardRole)) {
                    'SELLER' => 'Seller',
                    'ADMIN' => 'Admin',
                    'BUYER' => 'Buyer',
                    default => $cardRole,
                };
                ?>
                <?php if ($cardRoleLabel !== ''): ?>
                    <span>(<?php echo e($cardRoleLabel); ?>)</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <div class="property-card-actions">
            <a class="btn btn-property" href="<?php echo e(url('properties/view.php?id=' . $cardId)); ?>">View Details</a>
            <?php if ($showFavoriteButton): ?>
                <?php
                $favoritePropertyId = $cardId;
                $favoriteReturnPath = $cardReturnPath;
                $favoriteInline = true;
                include __DIR__ . '/_favorite_button.php';
                ?>
            <?php elseif ($showGuestFavoriteLink): ?>
                <a class="btn btn-outline-secondary favorite-guest-link" href="<?php echo e(url('auth/login.php')); ?>" title="Login to Add Favorite" aria-label="Login to Add Favorite">♡ Login to Add Favorite</a>
            <?php endif; ?>
        </div>
    </div>
</article>
