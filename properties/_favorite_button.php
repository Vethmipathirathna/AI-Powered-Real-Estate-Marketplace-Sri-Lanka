<?php
/**
 * Favorite save/remove button partial.
 *
 * Expects:
 * - $favoritePropertyId (int)
 * - $isFavorited (bool)
 * - $favoriteReturnPath (string)
 * Optional:
 * - $favoriteCompact (bool) — compact heart-only control
 * - $favoriteInline (bool) — inline action-row control for property cards
 */
declare(strict_types=1);

$favoritePropertyId = (int) ($favoritePropertyId ?? 0);
$isFavorited = (bool) ($isFavorited ?? false);
$favoriteReturnPath = buyer_safe_return_path($favoriteReturnPath ?? '');
$favoriteCompact = (bool) ($favoriteCompact ?? false);
$favoriteInline = (bool) ($favoriteInline ?? false);

$favoriteHeart = $isFavorited ? '♥' : '♡';
$favoriteLabel = $isFavorited ? 'Added to Favorites' : 'Add to Favorites';
$favoriteButtonText = $favoriteCompact
    ? $favoriteHeart
    : ($favoriteHeart . ' ' . $favoriteLabel);

$formClass = 'favorite-form';
if ($favoriteInline) {
    $formClass .= ' favorite-form-inline';
} elseif ($favoriteCompact) {
    $formClass .= ' favorite-form-compact';
}

$buttonClass = 'btn favorite-heart-btn ' . ($isFavorited ? 'btn-auth is-favorited' : 'btn-outline-secondary');
if ($favoriteInline) {
    $buttonClass .= ' btn-sm favorite-btn-inline';
} elseif ($favoriteCompact) {
    $buttonClass .= ' btn-sm favorite-btn-card';
} else {
    $buttonClass .= ' w-100';
}
?>
<form
    class="<?php echo e($formClass); ?>"
    method="post"
    action="<?php echo e(url('buyer/favorite_toggle.php')); ?>"
>
    <?php echo csrf_field(); ?>
    <input type="hidden" name="property_id" value="<?php echo e((string) $favoritePropertyId); ?>">
    <input type="hidden" name="return" value="<?php echo e($favoriteReturnPath); ?>">
    <button
        type="submit"
        class="<?php echo e($buttonClass); ?>"
        aria-pressed="<?php echo $isFavorited ? 'true' : 'false'; ?>"
        aria-label="<?php echo e($favoriteLabel); ?>"
        title="<?php echo e($favoriteLabel); ?>"
    >
        <?php echo e($favoriteButtonText); ?>
    </button>
</form>
