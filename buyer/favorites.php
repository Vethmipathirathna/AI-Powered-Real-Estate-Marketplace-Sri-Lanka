<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../buyer/_helpers.php';

require_role('BUYER');

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$flash = flash_get();
$favorites = [];
$loadError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_favorite') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid request. Please try again.');
    } else {
        $propertyId = (int) ($_POST['property_id'] ?? 0);
        try {
            [$ok, $message] = buyer_remove_favorite(db(), $userId, $propertyId);
            flash_set($ok ? 'success' : 'error', $message);
        } catch (Throwable $e) {
            flash_set('error', 'Unable to remove favorite right now.');
        }
    }
    redirect('buyer/favorites.php');
}

try {
    $favorites = buyer_fetch_favorites(db(), $userId);
} catch (Throwable $e) {
    $loadError = 'Unable to load your favorites right now. Please try again later.';
}

$flash = flash_get();
$page_title = 'My Favorites | RealEstateAI';
$page_description = 'Your saved property listings on RealEstateAI.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="marketplace-page">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url('buyer/index.php')); ?>">Buyer Dashboard</a>
            <span aria-hidden="true">/</span>
            <span>My Favorites</span>
        </nav>

        <div class="marketplace-hero">
            <p class="admin-eyebrow">Buyer area</p>
            <h1 class="admin-title">My Favorites</h1>
            <p class="admin-welcome">Properties you have saved for later. Unavailable listings remain saved but details are hidden.</p>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php elseif ($favorites === []): ?>
            <div class="summary-panel empty-state-panel">
                <p class="empty-state">You have not saved any properties yet.</p>
                <a class="btn btn-auth" href="<?php echo e(url('properties/index.php')); ?>">Browse Properties</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($favorites as $favorite): ?>
                    <?php
                    $propertyId = (int) ($favorite['property_id'] ?? 0);
                    $status = strtoupper((string) ($favorite['status'] ?? ''));
                    $isAvailable = $status === 'AVAILABLE';
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <?php if ($isAvailable): ?>
                            <?php
                            $property = $favorite;
                            $hideFavoriteButton = true;
                            $favoriteReturnPath = 'buyer/favorites.php';
                            ?>
                            <div class="favorite-card-wrap">
                                <?php include __DIR__ . '/../properties/_card.php'; ?>
                                <form method="post" action="" class="favorite-remove-form mt-2">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="remove_favorite">
                                    <input type="hidden" name="property_id" value="<?php echo e((string) $propertyId); ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">♥ Remove from Favorites</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <article class="summary-panel favorite-unavailable h-100 d-flex flex-column">
                                <p class="property-type-badge mb-2">Unavailable</p>
                                <h3 class="property-title">Saved property no longer available</h3>
                                <p class="text-muted flex-grow-1 mb-3">
                                    This saved listing is not currently on the marketplace. Details are hidden for your privacy and security.
                                </p>
                                <form method="post" action="">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="remove_favorite">
                                    <input type="hidden" name="property_id" value="<?php echo e((string) $propertyId); ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">♥ Remove from Favorites</button>
                                </form>
                            </article>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
