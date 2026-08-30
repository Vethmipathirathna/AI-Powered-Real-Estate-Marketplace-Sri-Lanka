<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ai_helpers.php';
require_once __DIR__ . '/../admin/_helpers.php';

ai_require_estimator_access();

$user = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$flash = flash_get();
$history = [];
$loadError = null;

try {
    $history = ai_fetch_user_history(db(), $userId);
} catch (Throwable $e) {
    $loadError = 'Unable to load your prediction history right now.';
}

$page_title = 'Prediction History | RealEstateAI';
$page_description = 'Your AI house price estimate history on RealEstateAI.';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="main-content" class="admin-dashboard">
    <div class="container">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo e(url(dashboard_path_for_role($user['role'] ?? 'BUYER'))); ?>">Dashboard</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo e(url('ai/estimate.php')); ?>">AI House Price Estimator</a>
            <span aria-hidden="true">/</span>
            <span>Prediction History</span>
        </nav>

        <div class="admin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="admin-eyebrow">AI tools</p>
                <h1 class="admin-title">Prediction History</h1>
                <p class="admin-welcome mb-0">Your saved AI house price estimates.</p>
            </div>
            <a class="btn btn-auth" href="<?php echo e(url('ai/estimate.php')); ?>">New Estimate</a>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($loadError); ?></div>
        <?php elseif ($history === []): ?>
            <div class="summary-panel empty-state-panel">
                <p class="empty-state">You have no saved predictions yet.</p>
                <a class="btn btn-auth" href="<?php echo e(url('ai/estimate.php')); ?>">Get your first estimate</a>
            </div>
        <?php else: ?>
            <div class="table-panel ai-history-panel">
                <div class="table-responsive">
                    <table class="table admin-table ai-history-table mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">District / Area</th>
                                <th scope="col">Property summary</th>
                                <th scope="col">Estimated price</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <?php $predictionId = (int) ($row['prediction_id'] ?? 0); ?>
                                <tr>
                                    <td><?php echo e(ai_format_prediction_datetime($row['created_at'] ?? null)); ?></td>
                                    <td>
                                        <?php echo e((string) ($row['district'] ?? '')); ?><br>
                                        <span class="text-muted small"><?php echo e((string) ($row['area'] ?? '')); ?></span>
                                    </td>
                                    <td><?php echo e(ai_property_summary($row)); ?></td>
                                    <td><?php echo e(admin_format_lkr($row['predicted_price_lkr'] ?? 0)); ?></td>
                                    <td>
                                        <?php if ($predictionId > 0): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(url('ai/prediction_view.php?id=' . $predictionId)); ?>">View</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
