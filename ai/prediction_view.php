<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ai_helpers.php';
require_once __DIR__ . '/../admin/_helpers.php';

ai_require_estimator_access();

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$predictionId = (int) ($_GET['id'] ?? 0);
$prediction = null;
$notFound = false;
$loadError = null;

try {
    if ($predictionId <= 0) {
        $notFound = true;
    } else {
        $prediction = ai_fetch_user_prediction(db(), $userId, $predictionId);
        if ($prediction === null) {
            $notFound = true;
        }
    }
} catch (Throwable $e) {
    $loadError = 'Unable to load this prediction right now. Please try again later.';
}

$page_title = $notFound ? 'Prediction Not Found | RealEstateAI' : 'Prediction Details | RealEstateAI';
$page_description = 'View details of a saved AI house price estimate.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard ai-prediction-detail-page">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url(dashboard_path_for_role($user['role'] ?? 'BUYER'))); ?>">Dashboard</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo e(url('ai/estimate.php')); ?>">AI House Price Estimator</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo e(url('ai/history.php')); ?>">Prediction History</a>
            <span aria-hidden="true">/</span>
            <span>Details</span>
        </nav>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
            <a class="btn btn-outline-secondary" href="<?php echo e(url('ai/history.php')); ?>">Back to Prediction History</a>
        <?php elseif ($notFound || $prediction === null): ?>
            <div class="summary-panel">
                <h1 class="admin-title h3">Prediction not found</h1>
                <p class="empty-state">This prediction is unavailable or you do not have access.</p>
                <a class="btn btn-auth" href="<?php echo e(url('ai/history.php')); ?>">Back to Prediction History</a>
            </div>
        <?php else: ?>
            <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="admin-eyebrow">AI tools</p>
                    <h1 class="admin-title">Prediction Details</h1>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?php echo e(url('ai/history.php')); ?>">Back to Prediction History</a>
                    <a class="btn btn-auth" href="<?php echo e(url('ai/estimate.php')); ?>">New Estimate</a>
                </div>
            </div>

            <section class="ai-result-card summary-panel mb-4" aria-labelledby="prediction-value-heading">
                <p class="admin-eyebrow mb-2">Estimated Property Value</p>
                <h2 id="prediction-value-heading" class="ai-result-price mb-0">
                    <?php echo e(admin_format_lkr($prediction['predicted_price_lkr'] ?? 0)); ?>
                </h2>
            </section>

            <section class="summary-panel">
                <h2 class="summary-title">Property details</h2>
                <dl class="detail-grid ai-prediction-detail-grid mb-0">
                    <div><dt>District</dt><dd><?php echo e((string) ($prediction['district'] ?? '—')); ?></dd></div>
                    <div><dt>Area</dt><dd><?php echo e((string) (($prediction['area'] ?? '') !== '' ? $prediction['area'] : '—')); ?></dd></div>
                    <div><dt>Land size</dt><dd><?php echo e($prediction['perch'] !== null ? number_format((float) $prediction['perch'], 2) . ' perch' : '—'); ?></dd></div>
                    <div><dt>Bedrooms</dt><dd><?php echo e($prediction['bedrooms'] !== null ? (string) $prediction['bedrooms'] : '—'); ?></dd></div>
                    <div><dt>Bathrooms</dt><dd><?php echo e($prediction['bathrooms'] !== null ? (string) $prediction['bathrooms'] : '—'); ?></dd></div>
                    <div><dt>Kitchen area</dt><dd><?php echo e($prediction['kitchen_area_sqft'] !== null ? number_format((float) $prediction['kitchen_area_sqft'], 0) . ' sq.ft' : '—'); ?></dd></div>
                    <div><dt>Parking spots</dt><dd><?php echo e((string) ($prediction['parking_spots'] ?? 0)); ?></dd></div>
                    <div><dt>Garden</dt><dd><?php echo e(admin_yes_no($prediction['has_garden'] ?? 0)); ?></dd></div>
                    <div><dt>Air conditioning</dt><dd><?php echo e(admin_yes_no($prediction['has_ac'] ?? 0)); ?></dd></div>
                    <div><dt>Water supply</dt><dd><?php echo e(ai_decode_water_supply((int) ($prediction['water_supply'] ?? 0))); ?></dd></div>
                    <div><dt>Electricity</dt><dd><?php echo e(ai_decode_electricity((int) ($prediction['electricity'] ?? 0))); ?></dd></div>
                    <div><dt>Floors</dt><dd><?php echo e($prediction['floors'] !== null ? (string) $prediction['floors'] : '—'); ?></dd></div>
                    <div><dt>Year built</dt><dd><?php echo e($prediction['year_built'] !== null ? (string) $prediction['year_built'] : '—'); ?></dd></div>
                    <div class="detail-span"><dt>Estimated on</dt><dd><?php echo e(ai_format_prediction_datetime($prediction['created_at'] ?? null)); ?></dd></div>
                </dl>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
